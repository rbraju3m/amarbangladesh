<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formatted text: `body_html` holds the cleaned HTML from the editor (App\Community\RichText), beside the
 * plain `body` that excerpts, search and emails keep using. NULL = plain text only (old rows, no JS, replies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', fn (Blueprint $table) => $table->mediumText('body_html')->nullable()->after('body'));

        // Adding a column may rebuild `posts`, and with it the FULLTEXT index: keep it without stopwords
        // (see 2026_10_10_000010_add_search_index_to_posts).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->mediumText('body_html')->nullable()->after('body'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('body_html'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');

        Schema::table('answers', fn (Blueprint $table) => $table->dropColumn('body_html'));
    }
};
