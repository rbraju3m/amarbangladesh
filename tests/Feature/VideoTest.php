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
        Cache::forget('videos.available');
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
        $file = sys_get_temp_dir().'/bd-video-test-'.$kind.'-'.getmypid().($kind === 'phone' ? '.mov' : '.mp4');
        $args = match ($kind) {
            'phone' => ['-f', 'lavfi', '-i', 'testsrc=duration=2:size=320x240:rate=15', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2',
                '-c:v', 'libx264', '-c:a', 'aac', '-shortest', '-metadata', 'location=+23.8103+090.4125/', '-metadata', 'creation_time=2024-05-01T10:00:00Z',
                '-metadata', 'make=TestPhone'],
            'long' => ['-f', 'lavfi', '-i', 'testsrc=duration=62:size=64x48:rate=1', '-c:v', 'libx264'],
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
        Storage::disk('public')->assertExists(["{$video->path}.mp4", "{$video->path}-poster.jpg", "{$video->path}-poster.webp"]);

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

    public function test_a_video_that_cannot_be_converted_is_retried_then_marked_failed(): void
    {
        $token = $this->token();
        $id = $this->upload($token, file_get_contents($this->clip('phone')))->json('id');
        Storage::disk('local')->put(Video::find($id)->partPath(), 'broken after the check');

        $this->artisan('community:process-videos')->expectsOutputToContain('failed 1');

        $video = Video::find($id);
        $this->assertSame(['failed', 'convert', Videos::MAX_ATTEMPTS], [$video->status, $video->error, $video->attempts]);
        $this->assertSame([], Storage::disk('public')->allFiles('videos'), 'no half-written files left');
    }

    public function test_a_conversion_left_unfinished_by_a_crash_is_picked_up_again(): void
    {
        $token = $this->token();
        $id = $this->upload($token, file_get_contents($this->clip('phone')))->json('id');
        Video::whereKey($id)->update(['status' => 'processing', 'attempts' => 1]);

        $this->artisan('community:process-videos')->expectsOutputToContain('Converted 1');
        $this->assertSame('ready', Video::find($id)->status);
    }

    public function test_uploads_are_refused_while_ffmpeg_is_missing(): void
    {
        config(['services.ffmpeg.ffprobe' => '/nonexistent/ffprobe']);

        $this->start($this->token(), 100)->assertUnprocessable()->assertJsonValidationErrors('video');
    }
}
