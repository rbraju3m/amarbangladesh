<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Search over post titles and bodies. MySQL's ngram parser indexes runs of characters rather than
 * space-separated words, so it works for Bangla (and English) without a dictionary. See App\Community\Search.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        DB::statement('ALTER TABLE posts ADD FULLTEXT INDEX posts_search (title, body) WITH PARSER ngram');
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        Schema::table('posts', fn ($table) => $table->dropIndex('posts_search'));
    }
};
