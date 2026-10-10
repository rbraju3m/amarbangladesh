<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Has this been asked?" compares titles only (App\Community\Search::similar()), so it gets its own
 * FULLTEXT index on the title: far fewer matches than title + body, so faster, and a common word in
 * a long body no longer crowds out posts whose titles really match. Built without stopwords, like
 * posts_search (2026_10_10_000010).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        DB::statement('ALTER TABLE posts ADD FULLTEXT INDEX posts_title_search (title) WITH PARSER ngram');
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        Schema::table('posts', fn ($table) => $table->dropIndex('posts_title_search'));
    }
};
