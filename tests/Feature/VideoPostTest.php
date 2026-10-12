<?php

namespace Tests\Feature;

use App\Analytics\Funnel;
use App\Community\Accounts;
use App\Community\Moderation;
use App\Community\Photos;
use App\Community\Videos;
use App\Models\Member;
use App\Models\Photo;
use App\Models\Post;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Videos on posts and answers (attach, show, moderation, clean-up). Conversion itself is in VideoTest. */
class VideoPostTest extends TestCase
{
    use RefreshDatabase;

    private const TITLE = 'রাস্তার এই অবস্থা কবে ঠিক হবে?';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** @return array{Member, string} */
    private function member(string $name = 'রাশেদ'): array
    {
        $member = Accounts::signIn('email', Str::random(10).'@example.test', $name);

        return [$member, $member->issueToken()];
    }

    private function as(string $token): static
    {
        return $this->withHeader('X-Member-Token', $token);
    }

    /** A video as if uploaded and converted (files on the fake disk), or at another status. */
    private function video(Member $member, string $status = Video::READY): Video
    {
        $path = 'videos/2026/10/'.Str::random(32);
        if ($status === Video::READY) {
            Storage::disk('public')->put("{$path}.mp4", 'mp4');
            Storage::disk('public')->put("{$path}-poster.jpg", 'jpg');
            Storage::disk('public')->put("{$path}-poster.webp", 'webp');
        }

        return Video::create([
            'member_id' => $member->id, 'status' => $status, 'size' => 1000, 'received' => $status === Video::UPLOADING ? 10 : 1000,
            'path' => $status === Video::READY ? $path : null, 'duration_ms' => 45_000, 'width' => 720, 'height' => 1280,
        ]);
    }

    private function files(Video $video): array
    {
        return ["{$video->path}.mp4", "{$video->path}-poster.jpg", "{$video->path}-poster.webp"];
    }

    public function test_a_post_with_a_video_plays_on_its_page_and_shows_a_poster_in_lists(): void
    {
        [$member, $token] = $this->member();
        $video = $this->video($member);

        $id = $this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->assertCreated()->json('post.id');
        $post = Post::find($id);
        $this->assertSame($video->id, $post->video->id);

        $this->get($post->url())->assertOk()
            ->assertSee('<video controls preload="none" playsinline', false)
            ->assertSee($video->url(), false)->assertSee($video->posterUrl(), false)
            ->assertSee('ভিডিও (০:৪৫)', false)
            ->assertSee('<meta property="og:image" content="'.$video->posterUrl('jpg').'">', false)
            ->assertSee('<meta property="og:image:width" content="720">', false);

        $this->get('/feed')->assertOk()->assertSee('card-thumb-play', false)->assertSee($video->posterUrl(), false)->assertSee('০:৪৫');
        $this->get('/en/feed')->assertSee('0:45');
        $this->getJson('/api/feed')->assertOk()->assertSee('card-thumb-play', false);
    }

    public function test_a_post_can_go_up_while_its_video_converts_and_the_page_picks_it_up(): void
    {
        [$member, $token] = $this->member();
        $video = $this->video($member, Video::WAITING);
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->assertCreated()->json('post.id'));

        $this->get($post->url())->assertSee('data-video-pending="'.$video->id.'"', false)->assertDontSee('<video controls', false);
        $this->get('/feed')->assertDontSee('card-thumb-play', false);
        $this->getJson("/api/videos/{$video->id}/player")->assertOk()->assertJson(['status' => 'waiting', 'html' => null, 'video' => null]);

        $ready = $this->video($member);
        $video->update(['status' => Video::READY, 'path' => $ready->path]);
        $ready->delete();
        $html = $this->getJson("/api/videos/{$video->id}/player")->assertOk()->assertJson(['status' => 'ready', 'video' => ['id' => $video->id, 'status' => 'ready']])->assertJsonMissingPath('video.size')->json('html');
        $this->assertStringContainsString('<video', $html);
    }

    public function test_the_player_endpoint_only_answers_for_published_items(): void
    {
        [$member, $token] = $this->member();
        $loose = $this->video($member);
        $this->getJson("/api/videos/{$loose->id}/player")->assertNotFound();

        $video = $this->video($member);
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->json('post.id'));
        Moderation::setStatus($post, Post::HIDDEN);
        $this->getJson("/api/videos/{$video->id}/player")->assertNotFound();
    }

    public function test_unfinished_failed_foreign_and_anonymous_videos_are_refused(): void
    {
        [$member, $token] = $this->member();
        [$other] = $this->member('মিতু');

        foreach ([$this->video($member, Video::UPLOADING), $this->video($member, Video::FAILED), $this->video($other)] as $video) {
            $this->as($token)->postJson('/api/posts', ['title' => self::TITLE.' '.$video->id, 'video' => $video->id])->assertUnprocessable()->assertJsonValidationErrors('video');
        }
        $this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $this->video($member)->id, 'anonymous' => true])->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->assertSame(0, Post::count(), 'nothing posted half way');
    }

    public function test_answers_can_have_a_video_but_replies_cannot(): void
    {
        [$asker, $askerToken] = $this->member();
        $post = Post::find($this->as($askerToken)->postJson('/api/posts', ['title' => self::TITLE])->json('post.id'));
        [$helper, $token] = $this->member('মিতু');
        $video = $this->video($helper);

        $data = $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'গতকাল এমন ছিল, দেখুন', 'video' => $video->id])->assertCreated()->json();
        $this->assertStringContainsString($video->url(), $data['html']);
        $this->get($post->url())->assertSee('উত্তরের ভিডিও', false);

        $this->as($token)->postJson("/api/posts/{$post->id}/answers", ['body' => 'আরেকটা ভিডিও', 'parent' => $data['answer']['id'], 'video' => $this->video($helper)->id])
            ->assertUnprocessable()->assertJsonValidationErrors('video');
    }

    public function test_editing_keeps_replaces_or_removes_the_video(): void
    {
        [$member, $token] = $this->member();
        $first = $this->video($member);
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $first->id])->json('post.id'));

        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => self::TITLE.' (আপডেট)'])->assertOk();
        $this->assertSame($first->id, $post->video()->value('id'), 'not sent: unchanged');

        $second = $this->video($member);
        $html = $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => self::TITLE, 'video' => $second->id])->assertOk()->json('video_html');
        $this->assertStringContainsString($second->url(), $html);
        $this->assertNull(Video::find($first->id));
        Storage::disk('public')->assertMissing($this->files($first));

        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => self::TITLE, 'video' => null])->assertOk()->assertJson(['video' => null]);
        $this->assertSame(0, Video::count());
        Storage::disk('public')->assertMissing($this->files($second));
    }

    public function test_an_edit_refused_for_its_video_keeps_the_photos_it_would_have_removed(): void
    {
        [$member, $token] = $this->member();
        [$other] = $this->member('মিতু');
        $photo = $this->as($token)->post('/api/photos', ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)], ['Accept' => 'application/json'])->assertCreated()->json('id');
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'photos' => [$photo]])->json('post.id'));
        $files = Storage::disk('public')->allFiles('photos');
        $this->assertNotEmpty($files);

        // Take the photo off and add someone else's video: the video is refused, so nothing may change.
        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => self::TITLE, 'photos' => [], 'video' => $this->video($other)->id])
            ->assertUnprocessable()->assertJsonValidationErrors('video');

        $this->assertSame($post->id, Photo::find($photo)?->photoable_id);
        Storage::disk('public')->assertExists($files);

        // A save that goes through still deletes the files.
        $this->as($token)->patchJson("/api/posts/{$post->id}", ['title' => self::TITLE, 'photos' => []])->assertOk();
        Storage::disk('public')->assertMissing($files);
    }

    public function test_a_failed_video_tells_only_its_author(): void
    {
        [$member, $token] = $this->member();
        $video = $this->video($member);
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->json('post.id'));
        $video->update(['status' => Video::FAILED, 'error' => 'convert']);

        $this->get($post->url())->assertDontSee('<video controls', false)
            ->assertSee("x-show=\"owned['post:{$post->id}']\"", false)->assertSee('ভিডিওটা চালানো গেলো না');
    }

    public function test_deleting_takes_the_video_at_once_but_an_admin_removal_keeps_it_a_while(): void
    {
        [$member, $token] = $this->member();
        $deletedVideo = $this->video($member);
        $deleted = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $deletedVideo->id])->json('post.id'));
        $removedVideo = $this->video($member);
        $removed = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE.' আবার', 'video' => $removedVideo->id])->json('post.id'));

        $this->as($token)->deleteJson("/api/posts/{$deleted->id}")->assertSuccessful();
        Storage::disk('public')->assertMissing($this->files($deletedVideo));

        Moderation::setStatus($removed, Post::REMOVED);
        Videos::prune();
        Storage::disk('public')->assertExists($this->files($removedVideo));

        $this->travel(Photos::REMOVED_DAYS + 1)->days();
        $this->artisan('community:prune-photos')->assertSuccessful()->expectsOutputToContain('1 videos');
        Storage::disk('public')->assertMissing($this->files($removedVideo));
    }

    public function test_unposted_and_unfinished_uploads_are_pruned_after_a_day(): void
    {
        [$member] = $this->member();
        $ready = $this->video($member);
        $partial = $this->video($member, Video::UPLOADING);
        Storage::disk('local')->put($partial->partPath(), 'half');

        $this->assertSame(0, Videos::prune());
        $this->travel(Videos::UNATTACHED_HOURS + 1)->hours();
        $this->assertSame(2, Videos::prune());

        Storage::disk('public')->assertMissing($this->files($ready));
        Storage::disk('local')->assertMissing($partial->partPath());
    }

    public function test_moderators_can_watch_the_video_of_a_reported_item(): void
    {
        [$member, $token] = $this->member();
        $video = $this->video($member);
        $post = Post::find($this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->json('post.id'));
        $post->forceFill(['reports_count' => 1])->save();

        $this->actingAs(User::factory()->create())->get('/admin/community')->assertOk()->assertSee($video->url(), false);
    }

    public function test_an_unposted_upload_can_be_dropped_by_its_uploader_only(): void
    {
        [$member, $token] = $this->member();
        [, $other] = $this->member('মিতু');
        $video = $this->video($member, Video::UPLOADING);
        Storage::disk('local')->put($video->partPath(), 'part');

        $this->as($other)->deleteJson("/api/videos/{$video->id}")->assertNotFound();
        $this->as($token)->deleteJson("/api/videos/{$video->id}")->assertNoContent();
        $this->assertModelMissing($video);
        Storage::disk('local')->assertMissing($video->partPath());

        // One already on a post goes with an edit of that post instead.
        $posted = $this->video($member);
        $this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $posted->id])->assertCreated();
        $this->as($token)->deleteJson("/api/videos/{$posted->id}")->assertStatus(409);
        $this->assertModelExists($posted);
    }

    public function test_composers_offer_a_video_while_the_server_can_convert_and_edit_forms_start_from_the_item(): void
    {
        [$member, $token] = $this->member();
        $video = $this->video($member);
        $id = $this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $video->id])->assertCreated()->json('post.id');

        Cache::put('videos.available', true);
        $this->get('/ask')->assertOk()->assertSee('data-video', false)->assertSee('videoPicker(null, true)', false);
        $this->get(Post::find($id)->url())->assertOk()
            ->assertSee('<template data-raw-video>{"id":'.$video->id.',', false)
            ->assertSee('template[data-raw-video]', false);

        // Without ffmpeg the button hides (the picker still shows an item's existing video so it can be removed).
        Cache::put('videos.available', false);
        $this->get('/ask')->assertOk()->assertSee('videoPicker(null, false)', false);
    }

    public function test_the_dashboard_counts_posts_and_answers_with_a_video(): void
    {
        [$member, $token] = $this->member();
        $post = $this->as($token)->postJson('/api/posts', ['title' => self::TITLE, 'video' => $this->video($member)->id])->assertCreated()->json('post.id');
        $this->as($token)->postJson("/api/posts/{$post}/answers", ['body' => 'ভিডিও ছাড়া একটা উত্তর'])->assertCreated();

        $community = (new Funnel(now()->subDay()))->community();
        $this->assertSame([2, 1], [$community['writes'], $community['writes_video']]);
    }
}
