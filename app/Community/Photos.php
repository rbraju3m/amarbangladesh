<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\Member;
use App\Models\Photo;
use App\Models\Post;
use GdImage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Photos on posts and top-level answers. The browser shrinks a photo before uploading it; here it is
 * always decoded and written again as WebP (a large and a small size), so no metadata (GPS, camera,
 * dates) and nothing hidden in the file survives, and the original is never kept. A photo is uploaded
 * first and attached when its post or answer is saved; unattached ones are pruned after a day.
 */
final class Photos
{
    public const MAX_PER_ITEM = 4;

    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Decoding needs ~4 bytes a pixel; the browser sends at most ~2.5 MP, so this is only a guard. */
    public const MAX_PIXELS = 20_000_000;

    /** Longest side of the two sizes written. */
    public const SIZES = ['full' => 1600, 'thumb' => 480];

    /**
     * A post's first photo also gets a JPEG copy for link previews (`og:image`): WebP isn't accepted
     * by every app that shows previews. Written when it becomes the first photo, deleted with the rest.
     */
    public const SHARE = ['suffix' => 'share.jpg', 'side' => 1200];

    /** Uploaded but never posted: kept this long (drafts, slow typing). */
    public const UNATTACHED_HOURS = 24;

    /** Unattached photos one member may hold at once (stops a script filling the disk). */
    public const MAX_UNATTACHED = 20;

    /** After an admin removes an item, its files stay this long in case it is restored. */
    public const REMOVED_DAYS = 30;

    private const DISK = 'public';

    private const TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    public static function store(Member $member, UploadedFile $file): Photo
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['photo' => $message]);

        if (Photo::where('member_id', $member->id)->whereNull('photoable_id')->count() >= self::MAX_UNATTACHED) {
            $fail(__('অনেক ছবি আপলোড হয়ে আছে, আগে পোস্টটা করুন।'));
        }
        if ($file->getSize() > self::MAX_BYTES) {
            $fail(__('ছবিটা অনেক বড়। ৫ MB-এর ছোট ছবি দিন।'));
        }
        // What the bytes are, not what the name or the browser says.
        $info = @getimagesize($file->getRealPath());
        if (! $info || ! in_array($info[2], self::TYPES, true)) {
            $fail(__('শুধু ছবি (JPG, PNG বা WebP) দেওয়া যাবে।'));
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            $fail(__('ছবিটা অনেক বড়। ৫ MB-এর ছোট ছবি দিন।'));
        }
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if (! $image) {
            $fail(__('ছবিটা খোলা গেলো না। অন্য একটা ছবি দিন।'));
        }
        if ($info[2] === IMAGETYPE_JPEG) {
            $image = self::upright($image, $file->getRealPath());
        }

        $path = 'photos/'.now()->format('Y/m').'/'.Str::random(32);
        $bytes = 0;
        foreach (self::SIZES as $size => $max) {
            $scaled = self::fit($image, $max);
            ob_start();
            imagewebp($scaled, null, $size === 'full' ? 80 : 72);
            $data = (string) ob_get_clean();
            // The disk doesn't throw (config), so check: a row without its file would be a broken image.
            if (! Storage::disk(self::DISK)->put("{$path}-{$size}.webp", $data)) {
                Storage::disk(self::DISK)->delete(array_map(fn ($s) => "{$path}-{$s}.webp", array_keys(self::SIZES)));
                throw new RuntimeException("Could not write {$path}-{$size}.webp to the public disk (permissions or space?)");
            }
            $bytes += strlen($data);
            if ($size === 'full') {
                [$width, $height] = [imagesx($scaled), imagesy($scaled)];
            }
        }

        return Photo::create(['member_id' => $member->id, 'path' => $path, 'width' => $width, 'height' => $height, 'bytes' => $bytes]);
    }

    /**
     * The `photos` ids sent with a post or answer: null when not sent (editing leaves photos as they
     * are), else the list. Anonymous items and replies can't have photos.
     */
    public static function input(Request $request, bool $anonymous, bool $allowed = true): ?array
    {
        if (! $request->has('photos')) {
            return null;
        }
        $ids = $request->input('photos') ?? [];
        if (! is_array($ids) || array_filter($ids, fn ($id) => ! is_numeric($id))) {
            throw ValidationException::withMessages(['photos' => __('ছবিগুলো আবার দিন।')]);
        }
        if ($ids && $anonymous) {
            throw ValidationException::withMessages(['photos' => __('বেনামে লিখলে ছবি দেওয়া যায় না। ছবি দিতে চাইলে বেনামী বন্ধ করুন।')]);
        }
        if ($ids && ! $allowed) {
            throw ValidationException::withMessages(['photos' => __('জবাবে ছবি দেওয়া যায় না।')]);
        }

        return array_values($ids);
    }

    /** The ids of an item's photos, in order. */
    public static function idsOf(Post|Answer $item): array
    {
        return Photo::where(['photoable_type' => Moderation::typeOf($item), 'photoable_id' => $item->id])->orderBy('position')->pluck('id')->all();
    }

    /**
     * Makes `$ids` (in order) the photos of a post or answer: the member's own unattached uploads or
     * ones already on this item; photos it had that are not listed are deleted. Throws on anything else.
     */
    public static function attach(Member $member, Post|Answer $item, array $ids): void
    {
        $type = Moderation::typeOf($item);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > self::MAX_PER_ITEM) {
            throw ValidationException::withMessages(['photos' => __('একসাথে সর্বোচ্চ ৪টি ছবি দেওয়া যাবে।')]);
        }
        $photos = Photo::whereIn('id', $ids)->where('member_id', $member->id)
            ->where(fn ($q) => $q->whereNull('photoable_id')->orWhere(fn ($q) => $q->where('photoable_type', $type)->where('photoable_id', $item->id)))
            ->get()->keyBy('id');
        if ($photos->count() !== count($ids)) {
            throw ValidationException::withMessages(['photos' => __('ছবিগুলো আবার দিন।')]);
        }

        DB::transaction(function () use ($item, $type, $ids, $photos) {
            self::delete(Photo::where(['photoable_type' => $type, 'photoable_id' => $item->id])->whereNotIn('id', $ids)->get());
            foreach ($ids as $position => $id) {
                $photos[$id]->update(['photoable_type' => $type, 'photoable_id' => $item->id, 'position' => $position]);
            }
        });
        if ($type === 'post' && $ids) {
            self::shareCopy($photos[$ids[0]]);
        }
    }

    /** The JPEG link-preview copy of a post's first photo (see SHARE). */
    public static function shareCopy(Photo $photo): void
    {
        $disk = Storage::disk(self::DISK);
        $file = "{$photo->path}-".self::SHARE['suffix'];
        if ($disk->exists($file) || ! ($image = @imagecreatefromstring((string) $disk->get("{$photo->path}-full.webp")))) {
            return;
        }
        ob_start();
        imagejpeg(self::fit($image, self::SHARE['side']), null, 82);
        $disk->put($file, (string) ob_get_clean());
    }

    /** Files and rows of these photos. */
    public static function delete(Collection $photos): int
    {
        foreach ($photos as $photo) {
            Storage::disk(self::DISK)->delete([...array_map(fn ($size) => "{$photo->path}-{$size}.webp", array_keys(self::SIZES)), "{$photo->path}-".self::SHARE['suffix']]);
            $photo->delete();
        }

        return $photos->count();
    }

    public static function deleteFor(Post|Answer $item): int
    {
        return self::delete(Photo::where(['photoable_type' => Moderation::typeOf($item), 'photoable_id' => $item->id])->get());
    }

    /**
     * Uploads never attached (after a day), photos of items removed or deleted a while ago (including
     * answers under such a post: their page is gone), and photos whose item no longer exists at all.
     * Returns how many were deleted.
     */
    public static function prune(): int
    {
        $gone = [Post::REMOVED, Post::DELETED];
        $before = now()->subDays(self::REMOVED_DAYS);
        $deadPosts = Post::whereIn('status', $gone)->where('updated_at', '<', $before)->select('id');
        $deadAnswers = Answer::where(fn ($q) => $q->whereIn('status', $gone)->where('updated_at', '<', $before))
            ->orWhereIn('post_id', $deadPosts)->select('id');

        $count = 0;
        foreach ([
            Photo::whereNull('photoable_id')->where('created_at', '<', now()->subHours(self::UNATTACHED_HOURS)),
            Photo::where('photoable_type', 'post')->whereIn('photoable_id', $deadPosts),
            Photo::where('photoable_type', 'answer')->whereIn('photoable_id', $deadAnswers),
            Photo::where('photoable_type', 'post')->whereNotIn('photoable_id', Post::select('id')),
            Photo::where('photoable_type', 'answer')->whereNotIn('photoable_id', Answer::select('id')),
        ] as $query) {
            $query->chunkById(200, function ($photos) use (&$count) {
                $count += self::delete($photos);
            });
        }

        return $count;
    }

    /** Scaled down so the longest side is at most `$max` (never up), on a white background if it was transparent. */
    private static function fit(GdImage $image, int $max): GdImage
    {
        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $max / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $out;
    }

    /** A JPEG straight from a camera may be stored sideways with an EXIF note to rotate it. */
    private static function upright(GdImage $image, string $file): GdImage
    {
        $orientation = function_exists('exif_read_data') ? (@exif_read_data($file)['Orientation'] ?? 1) : 1;

        return match ((int) $orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
