<?php

namespace Tests\Feature;

use App\Community\DemoContent;
use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Post;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DemoContentTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_seeding_is_repeatable_and_consistent(): void
    {
        [$members, $posts, $answers] = DemoContent::seed();
        DemoContent::seed();

        $this->assertSame($members, Member::where('is_demo', true)->count());
        $this->assertSame($posts, Post::count());
        $this->assertSame($answers, Answer::count());
        $this->assertGreaterThanOrEqual(61, $posts); // more than four feed pages, for infinite scroll

        // Counts match the rows, accepted answers belong to their post, nobody marks their own work.
        foreach (Post::with('answers')->get() as $post) {
            $this->assertSame($post->answers->count(), $post->answers_count);
            $this->assertSame(HelpfulMark::where(['markable_type' => 'post', 'markable_id' => $post->id])->count(), $post->helpful_count);
            if ($post->accepted_answer_id) {
                $this->assertTrue($post->answers->contains('id', $post->accepted_answer_id));
            }
            $this->assertTrue($post->created_at->lte(now()));
        }
        $this->assertSame(0, HelpfulMark::join('answers', fn ($j) => $j->on('answers.id', '=', 'helpful_marks.markable_id')->where('markable_type', 'answer'))
            ->whereColumn('answers.member_id', 'helpful_marks.member_id')->count());

        $this->get('/feed')->assertOk()->assertSee(Post::latest('id')->value('title'));
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
