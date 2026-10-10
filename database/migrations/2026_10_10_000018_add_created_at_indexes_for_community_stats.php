<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard's community numbers (App\Analytics\CommunityStats) count members, posts and answers
 * by when they were created, over a date range.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->index('created_at'));
        Schema::table('answers', fn (Blueprint $table) => $table->index('created_at'));
        // Keep the posts FULLTEXT indexes without stopwords if this rebuilds the table (see 000010).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->index('created_at'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        Schema::table('members', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('answers', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }
};
