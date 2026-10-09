<?php

namespace App\Community;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * The eight divisions as hotspots on the home page's Bangladesh map. Positions are % of the map
 * (`partials/bd-map`), in the same Mercator projection as the outline and the quiz places: each is
 * the division's headquarters city.
 */
final class DivisionMap
{
    public const COUNTS_KEY = 'community.division_counts';

    /** [x %, y %] by division slug. */
    public const POSITIONS = [
        'rangpur-division' => [27.8, 15.4],
        'mymensingh-division' => [51.9, 32.2],
        'sylhet-division' => [82.3, 29.7],
        'rajshahi-division' => [13.7, 38.5],
        'dhaka-division' => [51.7, 47.9],
        'khulna-division' => [33.4, 64.0],
        'barishal-division' => [50.5, 66.4],
        'chattogram-division' => [80.5, 72.0],
    ];

    /** @return list<array{slug: string, name: string, x: float, y: float, count: int}> in the page language */
    public static function spots(): array
    {
        $areas = Taxonomy::areas();
        $counts = self::counts();
        $spots = [];
        foreach (self::POSITIONS as $slug => [$x, $y]) {
            if (isset($areas[$slug])) {
                $spots[] = ['slug' => $slug, 'name' => $areas[$slug]['name'], 'x' => $x, 'y' => $y, 'count' => $counts[$slug] ?? 0];
            }
        }

        return $spots;
    }

    /** Published posts per division (districts included); a few minutes stale is fine. */
    public static function counts(): array
    {
        return Cache::remember(self::COUNTS_KEY, 300, function () {
            $divisionOf = array_column(Taxonomy::areas(), 'division_slug', 'id');
            $counts = [];
            foreach (Post::published()->whereNotNull('area_id')->groupBy('area_id')->selectRaw('area_id, COUNT(*) AS n')->pluck('n', 'area_id') as $area => $n) {
                if ($division = $divisionOf[$area] ?? null) {
                    $counts[$division] = ($counts[$division] ?? 0) + $n;
                }
            }

            return $counts;
        });
    }
}
