<?php

namespace App\Community;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;

/**
 * The home page's interactive map: the eight divisions, and the districts of one division once the map
 * zooms into it. Shapes come from geoBoundaries (CC0), projected into the same 400×552 viewBox and
 * Mercator projection as `partials/bd-map` by `scripts/build-division-map.py` (→
 * `resources/data/bd-divisions.php`) and `scripts/build-district-map.py` (→ `bd-districts.php`).
 * Division label positions are % of the map: the centre of each division's main shape.
 */
final class DivisionMap
{
    public const COUNTS_KEY = 'community.area_counts.v3';

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

    public const WIDTH = 400;

    public const HEIGHT = 552;

    /**
     * Every division with its shape, label position, discussion count, recent activity and a shade
     * level 0–4 (relative to the busiest division), names in the page language.
     *
     * @return list<array{slug: string, name: string, path: string, x: float, y: float, count: int, recent: int, level: int}>
     */
    public static function spots(?int $category = null): array
    {
        $areas = Taxonomy::areas();
        $shapes = require resource_path('data/bd-divisions.php');
        ['all' => $counts, 'recent' => $recent] = self::counts('division', $category);
        $spots = [];
        foreach (self::POSITIONS as $slug => [$x, $y]) {
            if (isset($areas[$slug], $shapes[$slug])) {
                $spots[] = [
                    'slug' => $slug, 'name' => $areas[$slug]['name'], 'path' => $shapes[$slug], 'x' => $x, 'y' => $y,
                    'count' => $counts[$slug] ?? 0, 'recent' => $recent[$slug] ?? 0, 'level' => self::level($counts[$slug] ?? 0, $counts),
                ];
            }
        }

        return $spots;
    }

    /**
     * One division zoomed in: the box to show ([x, y, width, height] in the map's viewBox, with some room
     * around it and the map's own proportions, so % positions map straight onto it) and its districts
     * like spots(), label positions in viewBox units. Null for an unknown division.
     *
     * @return array{view: list<float>, districts: list<array{slug: string, name: string, path: string, x: float, y: float, count: int, recent: int, level: int}>}|null
     */
    public static function division(string $division): ?array
    {
        $areas = Taxonomy::areas();
        if (($areas[$division]['type'] ?? null) !== 'division') {
            return null;
        }
        $shapes = require resource_path('data/bd-districts.php');
        ['all' => $counts, 'recent' => $recent] = self::counts('district');
        $mine = array_filter($areas, fn ($a) => $a['type'] === 'district' && $a['division_slug'] === $division && isset($shapes[$a['slug']]));
        $mineCounts = array_intersect_key($counts, $mine);

        $districts = [];
        $box = [INF, INF, -INF, -INF];
        foreach ($mine as $slug => $area) {
            [$path, [$bx, $by, $bw, $bh], [$x, $y]] = $shapes[$slug];
            $box = [min($box[0], $bx), min($box[1], $by), max($box[2], $bx + $bw), max($box[3], $by + $bh)];
            $districts[] = [
                'slug' => $slug, 'name' => $area['name'], 'path' => $path, 'x' => $x, 'y' => $y,
                'count' => $counts[$slug] ?? 0, 'recent' => $recent[$slug] ?? 0, 'level' => self::level($counts[$slug] ?? 0, $mineCounts),
            ];
        }
        if (! $districts) {
            return null;
        }

        return ['view' => self::fit($box), 'districts' => $districts];
    }

    /**
     * Published posts per division or district (a division's include its districts'), all time and in
     * the last RECENT_DAYS, optionally for one category.
     *
     * @return array{all: array<string, int>, recent: array<string, int>}
     */
    public static function counts(string $level = 'division', ?int $category = null): array
    {
        $areas = array_column(Taxonomy::areas(), null, 'id');
        $all = $recent = [];
        foreach (self::rows() as [$areaId, $categoryId, $n, $new]) {
            $area = $areas[$areaId] ?? null;
            if (! $area || ($category && $categoryId !== $category)) {
                continue;
            }
            $slug = $level === 'division' ? $area['division_slug'] : ($area['type'] === 'district' ? $area['slug'] : null);
            if ($slug) {
                $all[$slug] = ($all[$slug] ?? 0) + $n;
                $recent[$slug] = ($recent[$slug] ?? 0) + $new;
            }
        }

        return ['all' => $all, 'recent' => array_filter($recent)];
    }

    /** Published posts grouped by area and category: [area id, category id, all, recent]; a few minutes stale is fine. */
    private static function rows(): array
    {
        return Cache::remember(self::COUNTS_KEY, 300, fn () => Post::published()->whereNotNull('area_id')
            ->groupBy('area_id', 'category_id')
            ->selectRaw('area_id, category_id, COUNT(*) AS n, SUM(created_at >= ?) AS recent', [now()->subDays(self::RECENT_DAYS)])
            ->get()->map(fn ($r) => [(int) $r->area_id, (int) $r->category_id, (int) $r->n, (int) $r->recent])->all());
    }

    /** Shade 0–4: 0 = nothing yet, then 1–4 relative to the busiest of the group. */
    private static function level(int $n, array $counts): int
    {
        return $n ? min(4, 1 + (int) floor(3 * $n / max([1, ...array_values($counts)]))) : 0;
    }

    /** A box around [minX, minY, maxX, maxY] with a margin, widened or heightened to the map's proportions. */
    private static function fit(array $box): array
    {
        [$x0, $y0, $x1, $y1] = $box;
        $w = ($x1 - $x0) * 1.12;
        $h = ($y1 - $y0) * 1.12;
        $ratio = self::WIDTH / self::HEIGHT;
        $w / $h > $ratio ? $h = $w / $ratio : $w = $h * $ratio;
        $cx = ($x0 + $x1) / 2;
        $cy = ($y0 + $y1) / 2;

        return array_map(fn ($v) => round($v, 1), [$cx - $w / 2, $cy - $h / 2, $w, $h]);
    }
}
