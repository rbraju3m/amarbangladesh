<?php

namespace Tests\Feature;

use App\Community\Accounts;
use App\Community\Taxonomy;
use App\Models\AdminAction;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\QuizContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = QuizContentSeeder::class;

    public function test_add_rename_and_reorder_a_topic_with_a_log_line(): void
    {
        $admin = User::factory()->create();
        $this->get('/admin/categories')->assertRedirect(); // admins only

        $this->actingAs($admin)->post('/admin/categories', ['emoji' => '🌾', 'name_bn' => 'কৃষি', 'name_en' => 'Farming'])->assertRedirect();
        $farming = Category::where('slug', 'farming')->sole();
        $this->assertTrue($farming->is_active);
        $this->assertSame(Category::max('sort_order'), $farming->sort_order); // at the end
        $this->assertArrayHasKey('farming', Taxonomy::categories()); // the cached list was refreshed
        $this->get('/ask')->assertSee('কৃষি');
        $this->get('/en/ask')->assertSee('Farming');

        // Bad or taken URL names are refused.
        $this->actingAs($admin)->post('/admin/categories', ['emoji' => '🌾', 'name_bn' => 'আবার কৃষি', 'name_en' => 'Farming'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/admin/categories', ['emoji' => '🌾', 'name_bn' => 'x', 'slug' => 'Not OK'])->assertSessionHasErrors('slug');
        $this->actingAs($admin)->post('/admin/categories', ['emoji' => '🐟', 'name_bn' => 'মাছ চাষ', 'slug' => 'fish'])->assertRedirect();
        $this->assertSame('মাছ চাষ', Category::where('slug', 'fish')->value('name_bn'));

        $this->actingAs($admin)->put("/admin/categories/{$farming->id}", ['emoji' => '🚜', 'name_bn' => 'কৃষি ও খামার', 'name_en' => 'Farming', 'sort_order' => 0, 'is_active' => 1])->assertRedirect();
        $this->assertSame('কৃষি ও খামার', $farming->fresh()->name_bn);
        $this->assertSame(['education', 'farming'], array_slice(array_keys(Taxonomy::categories()), 0, 2)); // order 0, after education's 0 (lower id)
        $edit = AdminAction::where('action', 'topic_edit')->sole();
        $this->assertEquals(['from' => '🌾', 'to' => '🚜'], $edit->meta['changes']['emoji']);
        $this->assertArrayNotHasKey('name_en', $edit->meta['changes']); // only what changed

        $this->actingAs($admin)->put("/admin/categories/{$farming->id}", ['emoji' => '🚜', 'name_bn' => 'কৃষি ও খামার', 'name_en' => 'Farming', 'sort_order' => 0, 'is_active' => 1])
            ->assertSessionHas('status', 'Nothing changed.');
        $this->assertSame(2, AdminAction::where('action', 'topic_add')->count());
        $this->actingAs($admin)->get('/admin/community/log')->assertSee('Topic changed')->assertSee('emoji: 🌾 → 🚜')->assertSee('#topic-'.$farming->id, false);
        $this->actingAs($admin)->get('/admin/categories')->assertOk()->assertSee('কৃষি ও খামার');
    }

    public function test_a_topic_turned_off_leaves_ask_and_filters_but_its_posts_and_links_stay(): void
    {
        $admin = User::factory()->create();
        $member = Accounts::signIn('email', 'a@example.test', 'Rashed');
        $health = Category::where('slug', 'health')->sole();
        $post = Post::create(['member_id' => $member->id, 'title' => 'Which hospital for a fever?', 'category_id' => $health->id]);
        Post::create(['member_id' => $member->id, 'title' => 'Unrelated travel post']);

        $this->actingAs($admin)->put("/admin/categories/{$health->id}", ['emoji' => $health->emoji, 'name_bn' => $health->name_bn, 'name_en' => $health->name_en, 'sort_order' => $health->sort_order])->assertRedirect(); // no is_active = off
        $this->assertFalse($health->fresh()->is_active);
        $this->assertSame('topic_off', AdminAction::latest('id')->value('action'));

        $this->assertArrayNotHasKey('health', Taxonomy::categories());
        $this->assertArrayHasKey('health', Taxonomy::allCategories());
        $this->get('/ask')->assertDontSee('value="health"', false);
        $this->get('/')->assertDontSee("setTopic('health')", false);
        $this->withHeader('X-Member-Token', $member->issueToken())
            ->postJson('/api/posts', ['title' => 'আরেকটা প্রশ্ন, স্বাস্থ্য নিয়ে কিছু', 'category' => 'health'])->assertUnprocessable();

        // Old posts keep the tag; an old link still filters by it.
        $this->get($post->url())->assertOk()->assertSee($health->name_bn);
        $this->get('/feed?category=health')->assertOk()->assertSee('Which hospital for a fever?')->assertDontSee('Unrelated travel post');

        $this->actingAs($admin)->put("/admin/categories/{$health->id}", ['emoji' => $health->emoji, 'name_bn' => $health->name_bn, 'name_en' => $health->name_en, 'sort_order' => $health->sort_order, 'is_active' => 1]);
        $this->assertSame('topic_on', AdminAction::latest('id')->value('action'));
        $this->assertArrayHasKey('health', Taxonomy::categories());
    }
}
