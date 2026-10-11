<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posts that run out (feature plan chunk 10): lost & found and job posts get `expires_at`
 * (Post::EXPIRES_DAYS). After it they leave the feed and the home lists but keep their page, marked
 * closed. The author can close one early. Nothing runs at expiry: it is a query condition.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Adding a column may rebuild `posts` and its FULLTEXT indexes: keep them without stopwords (see 000010).
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('blood_group');
        });
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }

    public function down(): void
    {
        DB::statement('SET SESSION innodb_ft_enable_stopword = OFF');
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('expires_at'));
        DB::statement('SET SESSION innodb_ft_enable_stopword = ON');
    }
};
