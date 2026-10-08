<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** English names for the community categories (the site is bilingual; areas already have name_en). */
return new class extends Migration
{
    private const NAMES = [
        'education' => 'Education', 'career' => 'Jobs & career', 'health' => 'Health', 'technology' => 'Technology',
        'business' => 'Business', 'lifestyle' => 'Lifestyle', 'travel' => 'Travel', 'local-issues' => 'Local issues',
        'shopping' => 'Shopping & products', 'government' => 'Government services', 'other' => 'Other',
    ];

    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->string('name_en', 60)->nullable()->after('name_bn'));
        foreach (self::NAMES as $slug => $name) {
            DB::table('categories')->where('slug', $slug)->update(['name_en' => $name]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('name_en'));
    }
};
