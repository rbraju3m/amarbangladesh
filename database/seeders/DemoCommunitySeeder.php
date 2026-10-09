<?php

namespace Database\Seeders;

use App\Community\DemoContent;
use Illuminate\Database\Seeder;

/**
 * Fictional community content for development and UI review. Not part of `DatabaseSeeder`; run it on
 * purpose: `php artisan db:seed --class=DemoCommunitySeeder`. Re-running replaces the earlier demo
 * content; `php artisan community:purge-demo` removes it. Refuses to run in production.
 */
class DemoCommunitySeeder extends Seeder
{
    public function run(): void
    {
        [$members, $posts, $answers] = DemoContent::seed();
        $this->command?->info("Demo community: {$members} members, {$posts} posts, {$answers} answers.");
    }
}
