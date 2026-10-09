<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each quiz place points at a community area (a district, or a division for places that span
 * several), so the result page can show and invite questions about that place. Editable in admin.
 */
return new class extends Migration
{
    private const AREAS = [
        'bandarban' => 'bandarban', 'barishal' => 'barishal', 'chattogram' => 'chattogram',
        'coxs-bazar' => 'coxs-bazar', 'puran-dhaka' => 'dhaka', 'rajshahi' => 'rajshahi',
        'rangamati' => 'rangamati', 'sundarbans' => 'khulna-division', 'sylhet' => 'sylhet',
    ];

    public function up(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->string('area_slug', 60)->nullable()->after('map_y'));

        $known = DB::table('areas')->pluck('slug')->flip();
        foreach (self::AREAS as $place => $area) {
            if (isset($known[$area])) {
                DB::table('locations')->where('slug', $place)->whereNull('area_slug')->update(['area_slug' => $area]);
            }
        }
        Cache::forget('quiz.boot.bn');
        Cache::forget('quiz.boot.en');
    }

    public function down(): void
    {
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('area_slug'));
    }
};
