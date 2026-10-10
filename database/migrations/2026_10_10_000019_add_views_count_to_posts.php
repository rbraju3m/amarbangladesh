<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many people read a post (feature plan chunk 5): once per browser per post per day, see
 * PostController::view. Shown on the post from PostController::SHOW_VIEWS_FROM views.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adding a column may rebuild `posts` and its FULLTEXT indexes: keep them without stopwords (see 000010).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->unsignedInteger('views_count')->default(0)->after('helpful_count'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('views_count'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }
};
