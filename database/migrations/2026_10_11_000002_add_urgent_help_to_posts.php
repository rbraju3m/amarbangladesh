<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urgent help (feature plan chunk 9): a help post can be marked urgent for 24, 48 or 72 hours
 * (`urgent_until`; it simply stops being urgent by query, nothing runs at expiry) and name the blood
 * group it needs. See Post::URGENT_HOURS and Post::BLOOD_GROUPS.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adding columns may rebuild `posts` and its FULLTEXT indexes: keep them without stopwords (see 000010).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('urgent_until')->nullable()->after('accepted_answer_id');
            $table->string('blood_group', 3)->nullable()->after('urgent_until');
            $table->index('urgent_until');
        });
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex(['urgent_until']);
            $table->dropColumn(['urgent_until', 'blood_group']);
        });
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }
};
