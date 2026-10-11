<?php

use App\Quiz\Scorer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Until 2026-10-11 Scorer::friendMatch() compared the two trait vectors by position, but the
 * referrer's vector comes back from MySQL JSON with its keys reordered, so every stored friend %
 * was misaligned. Recompute them from the stored vectors (matched by trait key now).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('quiz_results as f')
            ->join('quiz_results as r', 'r.id', '=', 'f.referrer_result_id')
            ->whereNotNull('f.friend_match_pct')
            ->select('f.id', 'f.trait_vector', 'f.location_id', 'f.friend_match_pct', 'r.trait_vector as ref_vector', 'r.location_id as ref_location_id')
            ->orderBy('f.id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    $pct = Scorer::friendMatch(
                        json_decode($row->ref_vector, true) ?? [],
                        json_decode($row->trait_vector, true) ?? [],
                        (int) $row->ref_location_id === (int) $row->location_id,
                    );
                    if ($pct !== (int) $row->friend_match_pct) {
                        DB::table('quiz_results')->where('id', $row->id)->update(['friend_match_pct' => $pct]);
                    }
                }
            });
    }

    /** The old numbers were wrong, so there is nothing worth restoring. */
    public function down(): void {}
};
