<?php

namespace App\Community;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * The eight divisions on the home page's interactive map. Shapes come from geoBoundaries (CC0),
 * projected into the same 400×552 viewBox and Mercator projection as `partials/bd-map` by
 * `scripts/build-division-map.py` (→ `resources/data/bd-divisions.php`). Label positions are % of
 * the map: the centre of each division's main shape (its centroid), where the name sits.
 */
final class DivisionMap
{
    public const COUNTS_KEY = 'community.division_counts.v2';

    /** [x %, y %] by division slug: where the name is written. */
    public const POSITIONS = [
        'rangpur-division' => [23.1, 15.0],
        'mymensingh-division' => [51.6, 30.5],
        'sylhet-division' => [78.1, 32.5],
        'rajshahi-division' => [23.2, 35.1],
        'dhaka-division' => [48.3, 47.6],
        'khulna-division' => [28.4, 63.3],
        'barishal-division' => [48.4, 70.3],
        'chattogram-division' => [80.3, 66.1],
    ];

    /** Posts this recent make a division pulse ("people are talking here now"). */
    public const RECENT_DAYS = 7;

    /**
     * Every division with its shape, label position, discussion count, recent activity and a shade
     * level 0–4 (relative to the busiest division), names in the page language.
     *
     * @return list<array{slug: string, name: string, path: string, x: float, y: float, count: int, recent: int, level: int}>
     */
    public static function spots(): array
    {
        $areas = Taxonomy::areas();
        $shapes = require resource_path('data/bd-divisions.php');
        ['all' => $counts, 'recent' => $recent] = self::counts();
        $max = max([1, ...array_values($counts)]);
        $spots = [];
        foreach (self::POSITIONS as $slug => [$x, $y]) {
            if (isset($areas[$slug], $shapes[$slug])) {
                $n = $counts[$slug] ?? 0;
                $spots[] = [
                    'slug' => $slug, 'name' => $areas[$slug]['name'], 'path' => $shapes[$slug], 'x' => $x, 'y' => $y,
                    'count' => $n, 'recent' => $recent[$slug] ?? 0, 'level' => $n ? 1 + (int) floor(3 * $n / $max) : 0,
                ];
            }
        }

        return $spots;
    }

    /** Published posts per division (districts included), all time and recent; a few minutes stale is fine. */
    public static function counts(): array
    {
        return Cache::remember(self::COUNTS_KEY, 300, function () {
            $divisionOf = array_column(Taxonomy::areas(), 'division_slug', 'id');
            $sum = function ($query) use ($divisionOf) {
                $counts = [];
                foreach ($query->whereNotNull('area_id')->groupBy('area_id')->selectRaw('area_id, COUNT(*) AS n')->pluck('n', 'area_id') as $area => $n) {
                    if ($division = $divisionOf[$area] ?? null) {
                        $counts[$division] = ($counts[$division] ?? 0) + $n;
                    }
                }

                return $counts;
            };

            return [
                'all' => $sum(Post::published()),
                'recent' => $sum(Post::published()->where('created_at', '>=', now()->subDays(self::RECENT_DAYS))),
            ];
        });
    }
}
