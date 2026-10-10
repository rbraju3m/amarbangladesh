<?php

namespace Tests\Feature;

use App\Community\DemoContent;
use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Photo;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public'); // demo content includes photos
    }

    protected $seeder = QuizContentSeeder::class;

    public function test_seeding_is_repeatable_and_consistent(): void
    {
        [$members, $posts, $answers] = DemoContent::seed();
        DemoContent::seed();

        $this->assertSame($members, Member::where('is_demo', true)->count());
        $this->assertSame($posts, Post::count());
        $this->assertSame($answers, Answer::count());
        $this->assertGreaterThanOrEqual(61, $posts); // more than four feed pages, for infinite scroll
        $this->assertGreaterThan(3, Answer::whereNotNull('thread_id')->groupBy('thread_id')->selectRaw('COUNT(*) AS n')->pluck('n')->max()); // a thread long enough for "show more"
        $this->assertTrue(Answer::whereColumn('parent_id', '!=', 'thread_id')->exists()); // and a reply to a reply

        // Some posts and answers are formatted; their plain text drops the markers.
        $this->assertGreaterThanOrEqual(6, Post::whereNotNull('body_html')->count());
        $this->assertSame(3, Answer::whereNotNull('body_html')->count());
        $passport = Answer::where('body_html', 'like', '%<strong>মূল NID</strong>%')->first();
        $this->assertStringContainsString('<ul><li>পুরোনো পাসপোর্ট (মূল ও ফটোকপি)</li>', $passport->body_html);
        $this->assertStringStartsWith("যা নিয়ে যাবেন:\n\n• পুরোনো পাসপোর্ট", $passport->body);
        $this->assertStringNotContainsString('*', Answer::pluck('body')->implode(' ').Post::pluck('body')->implode(' '));
        $this->assertTrue(Post::where('body_html', 'like', '%<ol><li>Gboard%')->exists());

        // Counts match the rows, accepted answers belong to their post, nobody marks their own work.
        foreach (Post::with('answers')->get() as $post) {
            $this->assertSame($post->answers->whereNull('parent_id')->count(), $post->answers_count);
            foreach ($post->answers->whereNull('parent_id') as $answer) {
                $this->assertSame($post->answers->where('thread_id', $answer->id)->count(), $answer->replies_count);
            }
            $this->assertSame(HelpfulMark::where(['markable_type' => 'post', 'markable_id' => $post->id])->count(), $post->helpful_count);
            if ($post->accepted_answer_id) {
                $this->assertTrue($post->answers->contains('id', $post->accepted_answer_id));
            }
            $this->assertTrue($post->created_at->lte(now()));
        }
        $this->assertSame(0, HelpfulMark::join('answers', fn ($j) => $j->on('answers.id', '=', 'helpful_marks.markable_id')->where('markable_type', 'answer'))
            ->whereColumn('answers.member_id', 'helpful_marks.member_id')->count());

        $this->get('/feed')->assertOk()->assertSee(Post::latest('id')->value('title'));

        // Drawn photos on a few threads, through the real upload path; a second seed replaced the first.
        $this->assertSame(39, Photo::count());
        $this->assertSame(18, Photo::where('photoable_type', 'post')->distinct()->count('photoable_id'));
        $this->assertSame(9, Photo::where('photoable_type', 'answer')->count());
        $this->assertCount(39 * 2 + 18, Storage::disk('public')->allFiles('photos')); // two sizes each + a share copy per post
    }

    public function test_purging_keeps_real_content_and_fixes_its_counts(): void
    {
        $token = $this->postJson('/api/auth/email/register', ['name' => 'Real', 'email' => 'real@example.com', 'password' => 'secret-pass'])->json('token');
        $real = Post::findOrFail($this->withHeader('X-Member-Token', $token)->postJson('/api/posts', ['title' => 'A real question from a real person'])->json('post.id'));

        DemoContent::seed();
        $demo = Member::where('is_demo', true)->first();
        Answer::forceCreate(['post_id' => $real->id, 'member_id' => $demo->id, 'body' => 'A demo answer', 'status' => Post::PUBLISHED]);
        HelpfulMark::forceCreate(['member_id' => $demo->id, 'markable_type' => 'post', 'markable_id' => $real->id]);
        $real->forceFill(['answers_count' => 1, 'helpful_count' => 1])->save();

        $this->artisan('community:purge-demo', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, Photo::count());
        $this->assertSame([], Storage::disk('public')->allFiles('photos')); // files too

        $this->assertSame(0, Member::where('is_demo', true)->count());
        $this->assertSame([$real->id], Post::pluck('id')->all());
        $this->assertSame(0, $real->fresh()->answers_count);
        $this->assertSame(0, $real->fresh()->helpful_count);
    }

    public function test_never_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->expectException(RuntimeException::class);
        DemoContent::seed();
    }
}
