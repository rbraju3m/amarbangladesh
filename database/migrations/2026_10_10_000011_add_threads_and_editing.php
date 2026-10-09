<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replies and editing. A reply is an answer with a parent: `parent_id` is what it replies to (an
 * answer or another reply), `thread_id` the top-level answer it sits under (so a thread loads in one
 * query); `replies_count` counts a thread's published replies. `edited_at` marks edited posts and answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('post_id')->constrained('answers')->cascadeOnDelete();
            $table->unsignedBigInteger('thread_id')->nullable()->after('parent_id');
            $table->unsignedInteger('replies_count')->default(0)->after('helpful_count');
            $table->timestamp('edited_at')->nullable()->after('reports_count');
            $table->index(['thread_id', 'status', 'id']);
        });

        // Adding a column may rebuild `posts`, and with it the FULLTEXT index: keep it without stopwords
        // (see 2026_10_10_000010_add_search_index_to_posts).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->timestamp('edited_at')->nullable()->after('accepted_answer_id'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('edited_at'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');

        Schema::table('answers', function (Blueprint $table) {
            $table->dropIndex(['thread_id', 'status', 'id']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['thread_id', 'replies_count', 'edited_at']);
        });
    }
};
