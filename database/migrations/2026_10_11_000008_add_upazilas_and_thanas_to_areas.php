<?php

use App\Community\Taxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Upazilas (and the metropolitan thanas of Dhaka and Chattogram) under their districts, from
 * resources/data/bd-upazilas.php (scripts/build-upazilas.py). Only adds rows, so it can run again; a
 * later name fix is another small migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $districts = DB::table('areas')->where('type', 'district')->pluck('id', 'slug');
        foreach (require resource_path('data/bd-upazilas.php') as $district => $rows) {
            if (! isset($districts[$district])) {
                continue;
            }
            DB::table('areas')->insertOrIgnore(array_map(fn ($r) => [
                'parent_id' => $districts[$district], 'slug' => $r[0], 'type' => $r[1], 'name_bn' => $r[2], 'name_en' => $r[3], 'sort_order' => $r[4],
            ], $rows));
        }
        Taxonomy::forget();
    }

    public function down(): void
    {
        $ids = DB::table('areas')->whereIn('type', ['upazila', 'thana'])->pluck('id');
        DB::table('posts')->whereIn('area_id', $ids)->update(['area_id' => null]);
        DB::table('helplines')->whereIn('area_id', $ids)->update(['area_id' => null]);
        DB::table('locations')->whereIn('area_slug', DB::table('areas')->whereIn('id', $ids)->pluck('slug'))->update(['area_slug' => null]);
        DB::table('areas')->whereIn('id', $ids)->delete();
        Taxonomy::forget();
    }
};
