<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Videos on posts and top-level answers. The browser uploads a video in pieces (so a dropped mobile
 * connection resumes, and each request stays under nginx's default 1 MB body limit) into a private part
 * file; the last piece is checked with ffprobe. `community:process-videos` (every minute, one at a time)
 * then converts it with ffmpeg to an H.264/AAC MP4 of at most 1280×720 with "fast start", dropping every
 * piece of metadata (location, device, dates), writes posters, and deletes the original. Nothing a member
 * uploaded is ever served as it was sent.
 */
final class Videos
{
    public const MAX_BYTES = 50 * 1024 * 1024;

    public const MAX_SECONDS = 60;

    /** Per request; under nginx's default `client_max_body_size 1m` with room for headers. */
    public const CHUNK_BYTES = 960 * 1024;

    /** Unattached videos (uploading, waiting, converted but not posted) one member may hold at once. */
    public const MAX_UNATTACHED = 3;

    /** Longest side of the converted video (720p either way round). */
    public const MAX_SIDE = 1280;

    /** Conversion attempts before a video is marked failed. */
    public const MAX_ATTEMPTS = 3;

    /** How long one `community:process-videos` run keeps taking new videos. */
    public const RUN_SECONDS = 240;

    /** An upload whose last piece came this long ago but was never checked is checked by the converter. */
    public const UNCHECKED_MINUTES = 5;

    /** Uploaded but never posted (or never finished uploading): kept this long. */
    public const UNATTACHED_HOURS = 24;

    /** Private disk folder for part files. */
    public const UPLOAD_DIR = 'video-uploads';

    /** Containers phones and cameras record (ffprobe's format names). */
    private const FORMATS = ['mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2', 'matroska', 'webm', 'avi'];

    private const DISK = 'public';

    private const PRIVATE_DISK = 'local';

    /** Starts an upload of `$size` bytes; the pieces follow. */
    public static function start(Member $member, int $size): Video
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['video' => $message]);

        if (! self::available()) {
            $fail(__('এই মুহূর্তে ভিডিও নেওয়া যাচ্ছে না। একটু পরে চেষ্টা করুন।'));
        }
        if ($size > self::MAX_BYTES) {
            $fail(__('ভিডিওটা অনেক বড়। ৫০ MB-এর ছোট ভিডিও দিন।'));
        }
        if (Video::where('member_id', $member->id)->whereNull('videoable_id')->whereIn('status', [Video::UPLOADING, Video::WAITING, Video::PROCESSING, Video::READY])->count() >= self::MAX_UNATTACHED) {
            $fail(__('অনেক ভিডিও আপলোড হয়ে আছে, আগে পোস্টটা করুন।'));
        }

        // Status and offset spelled out: the browser starts sending from what this returns.
        $video = Video::create(['member_id' => $member->id, 'size' => $size, 'status' => Video::UPLOADING, 'received' => 0]);
        if (! Storage::disk(self::PRIVATE_DISK)->put($video->partPath(), '')) {
            $video->delete();
            throw new \RuntimeException('Could not create a video part file on the local disk (permissions or space?)');
        }

        return $video;
    }

    /**
     * Adds one piece at `$offset`. Returns false when the offset isn't where the upload stands (a retry
     * of a piece that already arrived, or a gap): the browser then continues from `received`. The last
     * piece checks the whole file; a file that isn't a usable video is thrown away with a message.
     */
    public static function append(Video $video, int $offset, string $data): bool
    {
        $length = strlen($data);
        // Every piece is here but the check never finished (it timed out, or the request died): the
        // browser sends an empty piece at the end, which checks it again.
        if ($video->status === Video::UPLOADING && $length === 0 && $offset === $video->size && $video->received === $video->size) {
            self::finishUpload($video, Storage::disk(self::PRIVATE_DISK)->path($video->partPath()));

            return true;
        }
        if ($video->status !== Video::UPLOADING || $offset !== $video->received) {
            return false;
        }
        if ($length === 0 || $length > self::CHUNK_BYTES || $offset + $length > $video->size) {
            throw ValidationException::withMessages(['video' => __('ভিডিওর একটা অংশ ঠিকমতো আসেনি। আবার চেষ্টা করুন।')]);
        }

        $file = Storage::disk(self::PRIVATE_DISK)->path($video->partPath());
        // Only one request may add the piece at this offset (a double send must not write it twice).
        if (Video::whereKey($video->id)->where('received', $offset)->update(['received' => $offset + $length]) !== 1) {
            return false;
        }
        if (! is_file($file) || file_put_contents($file, $data, FILE_APPEND | LOCK_EX) !== $length) {
            Video::whereKey($video->id)->update(['received' => $offset]);
            throw new \RuntimeException("Could not append to {$file}");
        }
        $video->received = $offset + $length;

        if ($video->received === $video->size) {
            self::finishUpload($video, $file);
        }

        return true;
    }

    /**
     * The whole file is here: keep it for conversion only if ffprobe sees a short enough video. A check
     * that couldn't run (ffprobe timed out or is missing) throws and leaves the upload as it was, so it
     * can be checked again (the browser's empty last piece, or checkUnchecked()).
     */
    private static function finishUpload(Video $video, string $file): void
    {
        $info = self::probe($file);
        $error = match (true) {
            $info === null => 'not_video',
            $info['duration'] > self::MAX_SECONDS + 0.5 => 'too_long',
            default => null,
        };
        if ($error) {
            self::fail($video, $error);
            throw ValidationException::withMessages(['video' => $error === 'too_long'
                ? __('ভিডিও ১ মিনিটের মধ্যে হতে হবে। ছোট করে আবার দিন।')
                : __('এটা ভিডিও হিসেবে খোলা গেলো না। অন্য একটা ভিডিও দিন।')]);
        }

        self::settle($video, [
            'status' => Video::WAITING,
            'duration_ms' => (int) round($info['duration'] * 1000),
            'width' => $info['width'],
            'height' => $info['height'],
        ]);
    }

    /** An upload refused at the check: marked failed (no longer counts against the cap) and its file deleted. */
    private static function fail(Video $video, string $error): void
    {
        if (self::settle($video, ['status' => Video::FAILED, 'error' => $error])) {
            Storage::disk(self::PRIVATE_DISK)->delete($video->partPath());
        }
    }

    /**
     * Ends an upload's check, only if it's still uploading: two checks of one upload can run at once (the
     * browser's retry and the converter's), and only the first to finish decides.
     */
    private static function settle(Video $video, array $values): bool
    {
        if (Video::whereKey($video->id)->where('status', Video::UPLOADING)->update($values) !== 1) {
            $video->refresh();

            return false;
        }
        $video->forceFill($values)->syncOriginal();

        return true;
    }

    /**
     * Uploads whose last piece came over UNCHECKED_MINUTES ago and were never checked (the browser went
     * away after the check failed to run): checked now, and one that still can't be checked is marked
     * failed (`check`), so it doesn't hold one of the member's unposted uploads for a day. Returns how many.
     */
    public static function checkUnchecked(): int
    {
        $count = 0;
        Video::where('status', Video::UPLOADING)->whereColumn('received', 'size')->orderBy('id')
            ->each(function (Video $video) use (&$count) {
                $file = Storage::disk(self::PRIVATE_DISK)->path($video->partPath());
                // The part file's time is when its last piece came; a check may still be running in that request.
                if (is_file($file) && filemtime($file) > time() - self::UNCHECKED_MINUTES * 60) {
                    return;
                }
                $count++;
                try {
                    self::finishUpload($video, $file);
                } catch (ValidationException) {
                    // refused: finishUpload marked it failed
                } catch (Throwable $e) {
                    report($e);
                    self::fail($video, 'check');
                }
            });

        return $count;
    }

    /**
     * What ffprobe sees: duration (s) and display size of the first real video stream, or null when the
     * file isn't a video in a container phones record (an image, audio, a renamed document…).
     *
     * @return array{duration: float, width: int, height: int}|null
     */
    public static function probe(string $file): ?array
    {
        $result = Process::timeout(30)->run([self::binary('ffprobe'), '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $file]);
        // 126/127: ffprobe itself couldn't be run, which says nothing about the file.
        if (in_array($result->exitCode(), [126, 127], true)) {
            throw new \RuntimeException('ffprobe could not be run: '.self::binary('ffprobe'));
        }
        $data = $result->successful() ? json_decode($result->output(), true) : null;
        if (! is_array($data)) {
            return null;
        }
        $formats = explode(',', (string) ($data['format']['format_name'] ?? ''));
        $stream = collect($data['streams'] ?? [])->first(fn ($s) => ($s['codec_type'] ?? '') === 'video' && empty($s['disposition']['attached_pic']));
        $duration = (float) ($data['format']['duration'] ?? $stream['duration'] ?? 0);
        [$width, $height] = [(int) ($stream['width'] ?? 0), (int) ($stream['height'] ?? 0)];
        if (! $stream || ! array_intersect($formats, self::FORMATS) || $duration <= 0 || $width < 16 || $height < 16 || $width > 8192 || $height > 8192) {
            return null;
        }
        // Phones store portrait video as landscape plus a rotation; report what viewers see.
        if (self::rotation($stream) % 180 === 90) {
            [$width, $height] = [$height, $width];
        }

        return ['duration' => $duration, 'width' => $width, 'height' => $height];
    }

    /** Degrees a stream is turned for display: the old `rotate` tag or a display-matrix side data entry. */
    private static function rotation(array $stream): int
    {
        if (isset($stream['tags']['rotate'])) {
            return abs((int) $stream['tags']['rotate']);
        }
        foreach ($stream['side_data_list'] ?? [] as $side) {
            if (isset($side['rotation'])) {
                return abs((int) $side['rotation']);
            }
        }

        return 0;
    }

    /**
     * Converts waiting videos, oldest first, until none are left or RUN_SECONDS have passed. A video left
     * "processing" by a run that died (the scheduler never lets two runs overlap) goes back in the queue.
     * A failed attempt ends the run, so a video's attempts fall in separate minute-apart runs: a passing
     * fault (a full disk, ffmpeg being replaced) costs one attempt of one video, not every video's last.
     * Nothing is tried while ffmpeg/ffprobe can't be run at all.
     *
     * @return array{converted: int, failed: int, checked: int}
     */
    public static function processWaiting(int $seconds = self::RUN_SECONDS): array
    {
        $done = ['converted' => 0, 'failed' => self::recoverInterrupted(), 'checked' => 0];
        if (! self::canRun()) {
            Cache::put('videos.available', false, 600); // and stop taking uploads meanwhile

            return $done;
        }
        $done['checked'] = self::checkUnchecked();
        $until = microtime(true) + $seconds;
        while (microtime(true) < $until && $video = Video::where('status', Video::WAITING)->orderBy('id')->first()) {
            if (self::convert($video)) {
                $done['converted']++;

                continue;
            }
            if ($video->status === Video::FAILED) {
                $done['failed']++;
            }
            // Deleted while it was being converted: nothing went wrong, carry on.
            if (Video::whereKey($video->id)->exists()) {
                break;
            }
        }

        return $done;
    }

    /**
     * Conversions a crashed run left "processing" (PHP killed, out of memory, the server restarted): the
     * crash already counted as an attempt, so their half-written files are deleted and they wait again,
     * or fail once they've had MAX_ATTEMPTS, so a video that kills the converter every time can't hold up
     * every newer one. Runs never overlap, so nothing else is converting. Returns how many failed.
     */
    private static function recoverInterrupted(): int
    {
        $failed = 0;
        Video::where('status', Video::PROCESSING)->orderBy('id')->each(function (Video $video) use (&$failed) {
            report(new \RuntimeException("Video {$video->id}: a conversion was interrupted (attempt {$video->attempts})"));
            if ($video->path) {
                Storage::disk(self::DISK)->delete(self::files($video->path));
            }
            $failing = $video->attempts >= self::MAX_ATTEMPTS;
            if (self::finish($video, ['status' => $failing ? Video::FAILED : Video::WAITING, 'error' => $failing ? 'convert' : null, 'path' => null]) && $failing) {
                Storage::disk(self::PRIVATE_DISK)->delete($video->partPath());
                $failed++;
            }
        });

        return $failed;
    }

    /** One conversion: MP4 + posters on the public disk, original deleted. False if it didn't work this time. */
    public static function convert(Video $video): bool
    {
        $path = 'videos/'.now()->format('Y/m').'/'.Str::random(32);
        // The path is kept from the start (pages only use it once ready), so a run that crashes half way
        // leaves a record of its files for the next run to delete.
        $video->update(['status' => Video::PROCESSING, 'attempts' => $video->attempts + 1, 'path' => $path]);
        $source = Storage::disk(self::PRIVATE_DISK)->path($video->partPath());
        $disk = Storage::disk(self::DISK);
        $disk->makeDirectory(dirname($path));
        $files = self::files($path);

        try {
            $mp4 = $disk->path($files[0]);
            self::run([
                '-i', $source,
                '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn',
                // Nothing of the original's metadata (location, device, dates, chapters) is copied.
                '-map_metadata', '-1', '-map_chapters', '-1',
                // At most 1280 on the longest side (720p either way round), even sizes for H.264.
                '-vf', 'scale=w=\'if(gte(iw,ih),min('.self::MAX_SIDE.',iw),-2)\':h=\'if(gte(iw,ih),-2,min('.self::MAX_SIDE.',ih))\',scale=trunc(iw/2)*2:trunc(ih/2)*2',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '26', '-maxrate', '1800k', '-bufsize', '3600k',
                '-pix_fmt', 'yuv420p', '-profile:v', 'main',
                '-c:a', 'aac', '-b:a', '96k', '-ac', '2',
                '-movflags', '+faststart', '-threads', '2',
                $mp4,
            ], 900);

            $out = self::probe($mp4);
            if (! $out) {
                throw new \RuntimeException('converted file does not probe');
            }
            // Poster: a frame a second in (or the middle of a very short clip).
            self::run(['-ss', (string) min(1, $out['duration'] / 2), '-i', $mp4, '-frames:v', '1', '-q:v', '3', $disk->path($files[1])], 60);
            $image = @imagecreatefromjpeg($disk->path($files[1]));
            if (! $image || ! imagewebp($image, $disk->path($files[2]), 78)) {
                throw new \RuntimeException('poster could not be written');
            }
            foreach ($files as $file) {
                @chmod($disk->path($file), 0664); // group-writable like the rest of the public disk
            }

            // Only if the video is still there: one deleted meanwhile (its post, an edit, a cancel) never
            // had a path, so its delete couldn't reach these files.
            if (! self::finish($video, [
                'status' => Video::READY, 'path' => $path, 'error' => null, 'processed_at' => now(),
                'duration_ms' => (int) round($out['duration'] * 1000), 'width' => $out['width'], 'height' => $out['height'],
                'bytes' => filesize($mp4),
            ])) {
                $disk->delete($files);

                return false;
            }
            Storage::disk(self::PRIVATE_DISK)->delete($video->partPath());

            return true;
        } catch (Throwable $e) {
            report($e);
            $disk->delete($files);
            $failed = $video->attempts >= self::MAX_ATTEMPTS;
            if ($failed) {
                Storage::disk(self::PRIVATE_DISK)->delete($video->partPath());
            }
            self::finish($video, ['status' => $failed ? Video::FAILED : Video::WAITING, 'error' => $failed ? 'convert' : null, 'path' => null]);

            return false;
        }
    }

    /** A converted video's files on the public disk: the MP4 and its two posters. */
    public static function files(string $path): array
    {
        return ["{$path}.mp4", "{$path}-poster.jpg", "{$path}-poster.webp"];
    }

    /** Ends a conversion if the row is still being converted (not deleted meanwhile); the model follows. */
    private static function finish(Video $video, array $values): bool
    {
        if (Video::whereKey($video->id)->where('status', Video::PROCESSING)->update($values) !== 1) {
            return false;
        }
        $video->forceFill($values)->syncOriginal();

        return true;
    }

    /**
     * The `video` sent with a post or answer: null when not sent (editing leaves it as it is), 0 to take
     * it off, else the id. Anonymous items and replies can't have one.
     */
    public static function input(Request $request, bool $anonymous, bool $allowed = true): ?int
    {
        if (! $request->has('video')) {
            return null;
        }
        $id = $request->input('video');
        if (in_array($id, [null, '', 0, '0'], true)) {
            return 0;
        }
        $fail = fn (string $message) => throw ValidationException::withMessages(['video' => $message]);
        if (! is_numeric($id)) {
            $fail(__('ভিডিওটা আবার দিন।'));
        }
        if ($anonymous) {
            $fail(__('বেনামে লিখলে ভিডিও দেওয়া যায় না। ভিডিও দিতে চাইলে বেনামী বন্ধ করুন।'));
        }
        if (! $allowed) {
            $fail(__('জবাবে ভিডিও দেওয়া যায় না।'));
        }

        return (int) $id;
    }

    /** The id of an item's video, 0 if it has none. */
    public static function idOf(Post|Answer $item): int
    {
        return (int) Video::where(['videoable_type' => Moderation::typeOf($item), 'videoable_id' => $item->id])->value('id');
    }

    /**
     * Makes video `$id` (0 = none) the one on a post or answer: the member's own upload, unattached or
     * already on this item, fully uploaded (it may still be converting: the item shows "being made"
     * until it's ready). A video the item had before is deleted.
     */
    public static function attach(Member $member, Post|Answer $item, int $id): void
    {
        $type = Moderation::typeOf($item);
        $current = Video::where(['videoable_type' => $type, 'videoable_id' => $item->id])->get();
        if ($id === 0) {
            self::delete($current);

            return;
        }
        $video = Video::whereKey($id)->where('member_id', $member->id)
            ->where(fn ($q) => $q->whereNull('videoable_id')->orWhere(fn ($q) => $q->where('videoable_type', $type)->where('videoable_id', $item->id)))
            ->first();
        $problem = match ($video?->status) {
            null => __('ভিডিওটা আবার দিন।'),
            Video::UPLOADING => __('ভিডিও আপলোড এখনো শেষ হয়নি। একটু অপেক্ষা করুন।'),
            Video::FAILED => __('ভিডিওটা চালানো গেলো না। অন্য একটা ভিডিও দিন।'),
            default => null,
        };
        if ($problem) {
            throw ValidationException::withMessages(['video' => $problem]);
        }

        self::delete($current->where('id', '!=', $video->id));
        $video->update(['videoable_type' => $type, 'videoable_id' => $item->id]);
    }

    /**
     * Files (converted video, posters, a part file still waiting) and rows of these videos. Inside a
     * transaction the files go only once it commits, so a refused edit that rolls back keeps them.
     */
    public static function delete(Collection $videos): int
    {
        foreach ($videos as $video) {
            $files = $video->path ? self::files($video->path) : [];
            $part = $video->partPath();
            $video->delete();
            DB::afterCommit(function () use ($files, $part) {
                Storage::disk(self::DISK)->delete($files);
                Storage::disk(self::PRIVATE_DISK)->delete($part);
            });
        }

        return $videos->count();
    }

    public static function deleteFor(Post|Answer $item): int
    {
        return self::delete(Video::where(['videoable_type' => Moderation::typeOf($item), 'videoable_id' => $item->id])->get());
    }

    /**
     * Uploads never posted or never finished (after a day), videos of items removed or deleted
     * Photos::REMOVED_DAYS ago, and videos whose item no longer exists. Returns how many were deleted.
     */
    public static function prune(): int
    {
        ['post' => $deadPosts, 'answer' => $deadAnswers] = Moderation::goneBefore(now()->subDays(Photos::REMOVED_DAYS));

        $count = 0;
        foreach ([
            Video::whereNull('videoable_id')->where('created_at', '<', now()->subHours(self::UNATTACHED_HOURS)),
            Video::where('videoable_type', 'post')->whereIn('videoable_id', $deadPosts),
            Video::where('videoable_type', 'answer')->whereIn('videoable_id', $deadAnswers),
            Video::where('videoable_type', 'post')->whereNotIn('videoable_id', Post::select('id')),
            Video::where('videoable_type', 'answer')->whereNotIn('videoable_id', Answer::select('id')),
        ] as $query) {
            $query->chunkById(100, function ($videos) use (&$count) {
                $count += self::delete($videos);
            });
        }

        return $count;
    }

    /** Whether ffmpeg and ffprobe can be run here (checked every 10 minutes). */
    public static function available(): bool
    {
        return Cache::remember('videos.available', 600, fn () => self::canRun());
    }

    /** The same, asked now (the converter must not trust a 10-minute-old answer). */
    public static function canRun(): bool
    {
        return Process::timeout(10)->run([self::binary('ffmpeg'), '-version'])->successful()
            && Process::timeout(10)->run([self::binary('ffprobe'), '-version'])->successful();
    }

    /** "ffmpeg version 4.2.7-0ubuntu0.1 …" → "4.2.7-0ubuntu0.1"; null when it can't run (for /admin/system). */
    public static function version(): ?string
    {
        $run = Process::timeout(10)->run([self::binary('ffmpeg'), '-version']);

        return $run->successful() && preg_match('/version (\S+)/', $run->output(), $m) ? $m[1] : null;
    }

    /** ffmpeg (or ffprobe) from config, so a server with them elsewhere can point at them. */
    public static function binary(string $name): string
    {
        return (string) config("services.ffmpeg.{$name}", $name);
    }

    /** Runs ffmpeg at low priority so pages stay quick while it works; throws if it fails. */
    private static function run(array $args, int $timeout): void
    {
        $command = [self::binary('ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y', ...$args];
        if (is_executable('/usr/bin/nice')) {
            array_unshift($command, '/usr/bin/nice', '-n', '10');
        }
        $result = Process::timeout($timeout)->run($command);
        if (! $result->successful()) {
            throw new \RuntimeException('ffmpeg failed: '.Str::limit(trim($result->errorOutput()), 300));
        }
    }
}
