<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Videos;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoTest extends TestCase
{
    use RefreshDatabase;

    /** Test clips, made once per run with the real ffmpeg. */
    private static array $clips = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (! Process::run(['ffmpeg', '-version'])->successful()) {
            $this->markTestSkipped('ffmpeg is not installed');
        }
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('public');
        Storage::fake('local');
        Cache::memo()->forget('videos.available');
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$clips as $file) {
            @unlink($file);
            @unlink(str_replace('-turned.mov', '.mov', $file));
        }
        self::$clips = [];
        parent::tearDownAfterClass();
    }

    /** A clip made by ffmpeg: `phone` has GPS, a date and a 90° turn like a phone's portrait video. */
    private function clip(string $kind): string
    {
        if (isset(self::$clips[$kind])) {
            return self::$clips[$kind];
        }
        $file = sys_get_temp_dir().'/bd-video-test-'.$kind.'-'.getmypid().match ($kind) {
            'phone' => '.mov', 'avi' => '.avi', default => '.mp4'
        };
        $args = match ($kind) {
            'phone' => ['-f', 'lavfi', '-i', 'testsrc=duration=2:size=320x240:rate=15', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
                '-c:v', 'libx264', '-c:a', 'aac', '-shortest', '-metadata', 'location=+23.8103+090.4125/', '-metadata', 'creation_time=2024-05-01T10:00:00Z',
                '-metadata', 'make=TestPhone'],
            'long' => ['-f', 'lavfi', '-i', 'testsrc=duration=62:size=64x48:rate=1', '-c:v', 'libx264'],
            'avi' => ['-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x48:rate=5', '-c:v', 'mjpeg'],
        };
        $result = Process::run(['ffmpeg', '-y', '-loglevel', 'error', ...$args, $file]);
        $this->assertTrue($result->successful(), $result->errorOutput());
        if ($kind === 'phone') {
            // ffmpeg 4 drops a rotation set while encoding; a copy keeps it (as phones store portrait video).
            $turned = str_replace('.mov', '-turned.mov', $file);
            $result = Process::run(['ffmpeg', '-y', '-loglevel', 'error', '-i', $file, '-c', 'copy', '-metadata:s:v:0', 'rotate=90', $turned]);
            $this->assertTrue($result->successful(), $result->errorOutput());
            $file = $turned;
        }

        return self::$clips[$kind] = $file;
    }

    private function token(): string
    {
        return Accounts::signIn('email', Str::random(8).'@example.test', 'Rashed')->issueToken();
    }

    private function start(string $token, int $size)
    {
        return $this->withHeader('X-Member-Token', $token)->postJson('/api/videos', ['size' => $size]);
    }

    private function piece(string $token, int $id, int $offset, string $bytes)
    {
        return $this->call('PUT', "/api/videos/{$id}/chunk?offset={$offset}", [], [], [], [
            'HTTP_X_MEMBER_TOKEN' => $token, 'CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json',
        ], $bytes);
    }

    /** Uploads a file in `$pieces` pieces; returns the last response. */
    private function upload(string $token, string $bytes, int $pieces = 3)
    {
        // The browser starts sending from what this says, so it must carry the state, not just the id.
        $id = $this->start($token, strlen($bytes))->assertCreated()
            ->assertJson(['status' => 'uploading', 'received' => 0, 'size' => strlen($bytes), 'chunk' => Videos::CHUNK_BYTES])->json('id');
        $size = (int) ceil(strlen($bytes) / $pieces);
        for ($offset = 0; $offset < strlen($bytes); $offset += $size) {
            $response = $this->piece($token, $id, $offset, substr($bytes, $offset, $size));
        }

        return $response;
    }

    private function probe(string $file): array
    {
        return json_decode(Process::run(['ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $file])->output(), true);
    }

    public function test_a_video_uploaded_in_pieces_comes_out_as_a_clean_mp4_with_posters(): void
    {
        $this->assertStringContainsString('23.8103', json_encode($this->probe($this->clip('phone'))), 'the test clip carries a location');
        $token = $this->token();
        $response = $this->upload($token, file_get_contents($this->clip('phone')))->assertOk()
            ->assertJson(['status' => 'waiting', 'width' => 240, 'height' => 320]);
        $video = Video::findOrFail($response->json('id'));
        Storage::disk('local')->assertExists($video->partPath());

        $this->artisan('community:process-videos')->assertSuccessful()->expectsOutputToContain('Converted 1');

        $video->refresh();
        $this->assertSame('ready', $video->status);
        $this->assertSame([240, 320], [$video->width, $video->height], 'turned upright, not just tagged');
        $this->assertEqualsWithDelta(2000, $video->duration_ms, 150);
        Storage::disk('local')->assertMissing($video->partPath());
        Storage::disk('public')->assertExists(Videos::files($video->path));

        $out = $this->probe(Storage::disk('public')->path("{$video->path}.mp4"));
        $streams = collect($out['streams'])->keyBy('codec_type');
        $this->assertSame(['h264', 'aac'], [$streams['video']['codec_name'], $streams['audio']['codec_name']]);
        $tags = json_encode([$out['format']['tags'] ?? [], $streams['video']['tags'] ?? [], $streams['audio']['tags'] ?? []]);
        foreach (['location', '23.8103', 'creation_time', '2024-05-01', 'TestPhone', 'rotate'] as $leak) {
            $this->assertStringNotContainsString($leak, $tags, "the converted file still carries {$leak}");
        }

        $this->withHeader('X-Member-Token', $token)->getJson("/api/videos/{$video->id}")->assertOk()
            ->assertJson(['status' => 'ready', 'duration' => 2.0])->assertJsonPath('url', Storage::disk('public')->url("{$video->path}.mp4"));
    }

    public function test_an_interrupted_upload_continues_where_it_stopped(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $id = $this->start($token, strlen($bytes))->json('id');
        $half = intdiv(strlen($bytes), 2);

        $this->piece($token, $id, 0, substr($bytes, 0, $half))->assertOk()->assertJson(['received' => $half]);
        // The connection dropped after the piece arrived; the browser sends it again.
        $this->piece($token, $id, 0, substr($bytes, 0, $half))->assertStatus(409)->assertJson(['received' => $half]);
        $this->piece($token, $id, $half + 10, 'gap')->assertStatus(409)->assertJson(['received' => $half]);
        $this->withHeader('X-Member-Token', $token)->getJson("/api/videos/{$id}")->assertJson(['received' => $half, 'status' => 'uploading']);

        $this->piece($token, $id, $half, substr($bytes, $half))->assertOk()->assertJson(['status' => 'waiting']);
        $this->assertSame(md5($bytes), md5(Storage::disk('local')->get(Video::find($id)->partPath())), 'pieces put together exactly once');
    }

    public function test_files_that_are_not_short_videos_are_refused_and_thrown_away(): void
    {
        $token = $this->token();

        $response = $this->upload($token, str_repeat("not a video\n", 500), 2)->assertUnprocessable()->assertJsonValidationErrors('video');
        $video = Video::latest('id')->first();
        $this->assertSame(['failed', 'not_video'], [$video->status, $video->error]);
        Storage::disk('local')->assertMissing($video->partPath());

        $this->upload($token, file_get_contents($this->clip('long')), 1)->assertUnprocessable();
        $this->assertSame('too_long', Video::latest('id')->first()->error);

        $this->start($token, Videos::MAX_BYTES + 1)->assertUnprocessable()->assertJsonValidationErrors('video');
        $id = $this->start($token, Videos::CHUNK_BYTES * 2)->json('id');
        $this->piece($token, $id, 0, str_repeat('x', Videos::CHUNK_BYTES + 1))->assertUnprocessable();
    }

    public function test_playlists_and_avi_are_refused(): void
    {
        $token = $this->token();
        // Playlists renamed to .mp4: ffprobe is limited to local files in phone containers, so it doesn't
        // open what they name (ffmpeg 4.2's HLS reader already refused http from a local playlist).
        $hls = "#EXTM3U\n#EXT-X-TARGETDURATION:1\n#EXTINF:1.0,\n".$this->clip('phone')."\n#EXT-X-ENDLIST\n";
        $concat = "ffconcat version 1.0\nfile '".$this->clip('phone')."'\n";

        foreach ([$hls, $concat, file_get_contents($this->clip('avi'))] as $bytes) {
            $this->upload($token, $bytes, 1)->assertUnprocessable()->assertJsonValidationErrors('video');
            $this->assertSame('not_video', Video::latest('id')->first()->error);
        }
        $this->assertNull(Videos::probe($this->clip('avi')), 'AVI is no longer read at all');
    }

    public function test_uploads_belong_to_their_member_and_need_an_account(): void
    {
        $token = $this->token();
        $id = $this->start($token, 100)->json('id');
        $other = $this->token();

        $this->withHeader('X-Member-Token', $other)->getJson("/api/videos/{$id}")->assertNotFound();
        $this->piece($other, $id, 0, 'x')->assertNotFound();
        $this->flushHeaders()->postJson('/api/videos', ['size' => 100])->assertStatus(401);
    }

    public function test_a_member_can_hold_only_a_few_unposted_videos(): void
    {
        $token = $this->token();
        foreach (range(1, Videos::MAX_UNATTACHED) as $_) {
            $this->start($token, 100)->assertCreated();
        }

        $this->start($token, 100)->assertUnprocessable()->assertJsonValidationErrors('video');
    }

    public function test_a_video_that_cannot_be_converted_is_retried_in_later_runs_then_marked_failed(): void
    {
        $token = $this->token();
        $id = $this->upload($token, file_get_contents($this->clip('phone')))->json('id');
        Storage::disk('local')->put(Video::find($id)->partPath(), 'broken after the check');

        // One attempt per run, so a passing fault can't use up all of them at once.
        for ($run = 1; $run < Videos::MAX_ATTEMPTS; $run++) {
            $this->artisan('community:process-videos')->expectsOutputToContain('failed 0');
            $this->assertSame(['waiting', $run], [Video::find($id)->status, Video::find($id)->attempts]);
        }
        $this->artisan('community:process-videos')->expectsOutputToContain('failed 1');

        $video = Video::find($id);
        $this->assertSame(['failed', 'convert', Videos::MAX_ATTEMPTS], [$video->status, $video->error, $video->attempts]);
        $this->assertSame([], Storage::disk('public')->allFiles('videos'), 'no half-written files left');
    }

    public function test_a_failed_attempt_ends_the_run_and_leaves_the_other_videos_waiting(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $broken = $this->upload($token, $bytes)->json('id');
        $fine = $this->upload($token, $bytes)->json('id');
        Storage::disk('local')->put(Video::find($broken)->partPath(), 'broken after the check');

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 0, failed 0');

        $this->assertSame(['waiting', 1], [Video::find($broken)->status, Video::find($broken)->attempts]);
        $this->assertSame(['waiting', 0], [Video::find($fine)->status, Video::find($fine)->attempts]);
    }

    public function test_nothing_is_tried_while_ffmpeg_is_missing(): void
    {
        $id = $this->upload($this->token(), file_get_contents($this->clip('phone')))->json('id');
        config(['services.ffmpeg.ffmpeg' => '/nonexistent/ffmpeg']);

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 0, failed 0');

        $this->assertSame(['waiting', 0], [Video::find($id)->status, Video::find($id)->attempts]);
        $this->assertFalse(Videos::available(), 'uploads stop too');
    }

    public function test_a_video_deleted_while_it_was_converted_leaves_no_files(): void
    {
        $id = $this->upload($this->token(), file_get_contents($this->clip('phone')))->json('id');
        $video = Video::find($id);
        // Its post was deleted (or the upload cancelled) while ffmpeg worked: the row went, the copy didn't know.
        Video::whereKey($id)->delete();

        $this->assertFalse(Videos::convert($video));

        $this->assertNull(Video::find($id));
        $this->assertSame([], Storage::disk('public')->allFiles('videos'), 'the converted copy and posters are deleted');
    }

    public function test_a_conversion_left_unfinished_by_a_crash_is_picked_up_again(): void
    {
        $token = $this->token();
        $id = $this->upload($token, file_get_contents($this->clip('phone')))->json('id');
        Video::whereKey($id)->update(['status' => 'processing', 'attempts' => 1]);

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 1');
        $this->assertSame('ready', Video::find($id)->status);
    }

    /** Sends all but the last piece, then the last one while ffprobe can't run: every byte is in, never checked. */
    private function unchecked(string $token, string $bytes): int
    {
        $id = $this->start($token, strlen($bytes))->json('id');
        $half = intdiv(strlen($bytes), 2);
        $this->piece($token, $id, 0, substr($bytes, 0, $half))->assertOk();
        $probe = config('services.ffmpeg.ffprobe');
        config(['services.ffmpeg.ffprobe' => '/nonexistent/ffprobe']);
        $this->piece($token, $id, $half, substr($bytes, $half))->assertServerError();
        config(['services.ffmpeg.ffprobe' => $probe]);
        $this->assertSame(['uploading', strlen($bytes)], [Video::find($id)->status, Video::find($id)->received]);

        return $id;
    }

    public function test_an_upload_whose_check_could_not_run_is_checked_again_by_an_empty_last_piece(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $id = $this->unchecked($token, $bytes);

        // ffprobe missing is not "this isn't a video": the file stays.
        Storage::disk('local')->assertExists(Video::find($id)->partPath());
        // The browser's retry of the last piece is told everything arrived, then sends the empty piece at the end.
        $this->piece($token, $id, intdiv(strlen($bytes), 2), substr($bytes, intdiv(strlen($bytes), 2)))->assertStatus(409)->assertJson(['received' => strlen($bytes)]);
        $this->piece($token, $id, strlen($bytes), '')->assertOk()->assertJson(['status' => 'waiting']);
        $this->piece($token, $id, strlen($bytes), '')->assertStatus(409)->assertJson(['status' => 'waiting']);
    }

    public function test_the_converter_checks_uploads_the_browser_left_unchecked(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $old = $this->unchecked($token, $bytes);
        $recent = $this->unchecked($token, $bytes);
        touch(Storage::disk('local')->path(Video::find($old)->partPath()), time() - Videos::UNCHECKED_MINUTES * 60 - 1);

        $this->artisan('community:process-videos')->expectsOutputToContain('checked 1');

        $this->assertSame('ready', Video::find($old)->status, 'checked, then converted in the same run');
        $this->assertSame('uploading', Video::find($recent)->status, 'its own request may still be checking it');
    }

    public function test_an_unchecked_upload_that_still_cannot_be_checked_stops_counting_against_the_cap(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $ids = array_map(fn () => $this->unchecked($token, $bytes), range(1, Videos::MAX_UNATTACHED));
        $this->start($token, 100)->assertUnprocessable();
        foreach ($ids as $id) {
            touch(Storage::disk('local')->path(Video::find($id)->partPath()), time() - Videos::UNCHECKED_MINUTES * 60 - 1);
        }
        // ffprobe runs (`-version`), but can't check these files.
        $wrapper = sys_get_temp_dir().'/bd-ffprobe-'.getmypid();
        file_put_contents($wrapper, "#!/bin/sh\ncase \"\$*\" in *.part*) exit 127;; esac\nexec ffprobe \"\$@\"\n");
        chmod($wrapper, 0755);
        config(['services.ffmpeg.ffprobe' => $wrapper]);

        try {
            $this->artisan('community:process-videos')->expectsOutputToContain('checked '.Videos::MAX_UNATTACHED);
        } finally {
            @unlink($wrapper);
        }

        foreach ($ids as $id) {
            $this->assertSame(['failed', 'check'], [Video::find($id)->status, Video::find($id)->error]);
            Storage::disk('local')->assertMissing(Video::find($id)->partPath());
        }
        $this->start($token, 100)->assertCreated();
    }

    public function test_a_crashed_conversions_half_written_files_are_deleted_before_it_is_tried_again(): void
    {
        $id = $this->upload($this->token(), file_get_contents($this->clip('phone')))->json('id');
        // What a run killed half way through leaves: the row "processing", its output started.
        Video::whereKey($id)->update(['status' => 'processing', 'attempts' => 1, 'path' => 'videos/2026/10/crashed']);
        Storage::disk('public')->put('videos/2026/10/crashed.mp4', 'half an mp4');

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 1');

        Storage::disk('public')->assertMissing('videos/2026/10/crashed.mp4');
        $video = Video::find($id);
        $this->assertSame(['ready', 2], [$video->status, $video->attempts]);
        $this->assertNotSame('videos/2026/10/crashed', $video->path);
    }

    public function test_a_video_that_crashes_the_converter_every_time_fails_instead_of_holding_up_the_others(): void
    {
        $token = $this->token();
        $bytes = file_get_contents($this->clip('phone'));
        $poison = $this->upload($token, $bytes)->json('id');
        $next = $this->upload($token, $bytes)->json('id');
        // Its last attempt was the one that crashed (the attempt is counted when it starts).
        Video::whereKey($poison)->update(['status' => 'processing', 'attempts' => Videos::MAX_ATTEMPTS, 'path' => 'videos/2026/10/crashed']);

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 1, failed 1');

        $video = Video::find($poison);
        $this->assertSame(['failed', 'convert', null], [$video->status, $video->error, $video->path]);
        Storage::disk('local')->assertMissing($video->partPath());
        $this->assertSame('ready', Video::find($next)->status);
    }

    public function test_a_video_has_no_address_until_it_is_ready(): void
    {
        $id = $this->upload($this->token(), file_get_contents($this->clip('phone')))->json('id');
        Video::whereKey($id)->update(['status' => 'processing', 'path' => 'videos/2026/10/started']);

        $this->assertSame([null, null], [Video::find($id)->url(), Video::find($id)->posterUrl()]);
    }

    public function test_uploads_are_refused_while_ffmpeg_is_missing(): void
    {
        config(['services.ffmpeg.ffprobe' => '/nonexistent/ffprobe']);

        $this->start($this->token(), 100)->assertUnprocessable()->assertJsonValidationErrors('video');
    }
}
