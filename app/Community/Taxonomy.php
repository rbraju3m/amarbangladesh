<?php

namespace App\Community;

use App\Models\Area;
use App\Models\Category;
use App\Support\Lang;
use Illuminate\Support\Facades\Cache;

/**
 * Categories and areas as cached arrays (categories change in the admin's Topics page, areas only
 * through migrations; both must call forget()). Arrays, not models: the cache refuses to unserialize objects.
 */
final class Taxonomy
{
    /**
     * Topics people can pick (Ask, filters, the map's lens): the active ones, in order.
     *
     * @return array<string, array{id: int, slug: string, name: string, emoji: string, active: bool}> by slug, names in the page language
     */
    public static function categories(): array
    {
        return array_filter(self::allCategories(), fn ($c) => $c['active']);
    }

    /**
     * Every topic, including ones an admin turned off: old posts keep them and old links
     * (`/feed?category=…`) keep filtering by them.
     */
    public static function allCategories(): array
    {
        return self::localize(Cache::memo()->rememberForever('community.categories.v3', fn () => Category::orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn (Category $c) => [$c->slug => ['id' => $c->id, 'slug' => $c->slug, 'name_bn' => $c->name_bn, 'name_en' => $c->name_en ?: $c->name_bn, 'emoji' => $c->emoji, 'active' => $c->is_active]])
            ->all()));
    }

    /** Area levels from the widest down; `thana` is a city area (metropolitan thana) at the upazila level. */
    public const LEVELS = ['division' => 0, 'district' => 1, 'upazila' => 2, 'thana' => 2];

    /**
     * Every area by slug, widest first (divisions, districts, then upazilas and thanas). Each row knows its
     * division and district (`division_slug`, `district_slug`; a district is its own district) and `ids`:
     * itself plus everything under it, which is what a filter by this area should match.
     *
     * @return array<string, array{id: int, slug: string, type: string, name: string, division: ?string, district: ?string, division_slug: ?string, district_slug: ?string, ids: list<int>}>
     */
    public static function areas(): array
    {
        return self::localize(Cache::memo()->rememberForever('community.areas.v4', function () {
            $all = Area::orderBy('sort_order')->orderBy('name_bn')->get()->keyBy('id');
            // The area itself or its nearest ancestor of this type.
            $up = function (?Area $a, string $type) use ($all): ?Area {
                while ($a && $a->type !== $type) {
                    $a = $all[$a->parent_id] ?? null;
                }

                return $a;
            };
            $children = $all->groupBy('parent_id');
            $below = function (int $id) use (&$below, $children): array {
                $ids = [$id];
                foreach ($children[$id] ?? [] as $child) {
                    array_push($ids, ...$below($child->id));
                }

                return $ids;
            };

            return $all->sortBy(fn (Area $a) => self::LEVELS[$a->type] ?? 9)
                ->mapWithKeys(function (Area $a) use ($up, $below) {
                    $division = $up($a, 'division');
                    $district = $a->type === 'division' ? null : $up($a, 'district');

                    return [$a->slug => [
                        'id' => $a->id, 'slug' => $a->slug, 'type' => $a->type, 'name_bn' => $a->name_bn, 'name_en' => $a->name_en,
                        'division_bn' => $a->type === 'division' ? null : $division?->name_bn, 'division_en' => $a->type === 'division' ? null : $division?->name_en,
                        'district_bn' => self::LEVELS[$a->type] > 1 ? $district?->name_bn : null, 'district_en' => self::LEVELS[$a->type] > 1 ? $district?->name_en : null,
                        'division_slug' => $division?->slug, 'district_slug' => $district?->slug,
                        'ids' => $below($a->id),
                    ]];
                })->all();
        }));
    }

    /** The ids a filter by this area matches (itself and everything under it), or null for an unknown slug. */
    public static function areaIds(?string $slug): ?array
    {
        return self::areas()[(string) $slug]['ids'] ?? null;
    }

    /**
     * Upazilas and city thanas grouped by their district's slug, upazilas first, for the pickers.
     *
     * @return array<string, list<array>>
     */
    public static function upazilasByDistrict(): array
    {
        $groups = [];
        foreach (self::areas() as $area) {
            if (self::LEVELS[$area['type']] === 2) {
                $groups[$area['district_slug']][] = $area;
            }
        }
        foreach ($groups as &$list) {
            usort($list, fn ($a, $b) => [$a['type'] !== 'upazila'] <=> [$b['type'] !== 'upazila']);
        }

        return $groups;
    }

    /** Adds `name` (and `division`) in the page language to cached rows that hold both. */
    private static function localize(array $rows): array
    {
        $en = Lang::isEnglish();
        foreach ($rows as &$row) {
            $row['name'] = $en ? $row['name_en'] : $row['name_bn'];
            if (array_key_exists('division_bn', $row)) {
                $row['division'] = $en ? $row['division_en'] : $row['division_bn'];
                $row['district'] = $en ? $row['district_en'] : $row['district_bn'];
            }
        }

        return $rows;
    }

    /** Districts grouped under their division's name, for a <select> with optgroups. */
    public static function districtsByDivision(): array
    {
        $groups = [];
        foreach (self::areas() as $area) {
            if ($area['type'] === 'district') {
                $groups[$area['division']][] = $area;
            }
        }

        return $groups;
    }

    public static function forget(): void
    {
        Cache::memo()->forget('community.categories.v3');
        Cache::memo()->forget('community.areas.v4');
    }
}
