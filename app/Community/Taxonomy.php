<?php

namespace App\Community;

use App\Models\Area;
use App\Models\Category;
use App\Support\Lang;
use Illuminate\Support\Facades\Cache;

/**
 * Categories and areas as cached arrays (they change only through migrations or a future admin
 * editor, which must call forget()). Arrays, not models: the cache refuses to unserialize objects.
 */
final class Taxonomy
{
    /** @return array<string, array{id: int, slug: string, name: string, emoji: string}> by slug, names in the page language */
    public static function categories(): array
    {
        return self::localize(Cache::rememberForever('community.categories.v2', fn () => Category::where('is_active', true)->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Category $c) => [$c->slug => ['id' => $c->id, 'slug' => $c->slug, 'name_bn' => $c->name_bn, 'name_en' => $c->name_en ?: $c->name_bn, 'emoji' => $c->emoji]])
            ->all()));
    }

    /** @return array<string, array{id: int, slug: string, name: string, division: ?string}> by slug, divisions first */
    public static function areas(): array
    {
        return self::localize(Cache::rememberForever('community.areas.v3', function () {
            $all = Area::orderBy('sort_order')->orderBy('name_bn')->get();
            $divisions = $all->where('type', 'division')->keyBy('id');

            return $all->sortBy(fn (Area $a) => $a->type === 'division' ? 0 : 1)
                ->mapWithKeys(fn (Area $a) => [$a->slug => [
                    'id' => $a->id, 'slug' => $a->slug, 'type' => $a->type, 'name_bn' => $a->name_bn, 'name_en' => $a->name_en,
                    'division_bn' => $divisions[$a->parent_id]->name_bn ?? null, 'division_en' => $divisions[$a->parent_id]->name_en ?? null,
                    'division_slug' => $a->type === 'division' ? $a->slug : ($divisions[$a->parent_id]->slug ?? null),
                ]])->all();
        }));
    }

    /** Adds `name` (and `division`) in the page language to cached rows that hold both. */
    private static function localize(array $rows): array
    {
        $en = Lang::isEnglish();
        foreach ($rows as &$row) {
            $row['name'] = $en ? $row['name_en'] : $row['name_bn'];
            if (array_key_exists('division_bn', $row)) {
                $row['division'] = $en ? $row['division_en'] : $row['division_bn'];
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
        Cache::forget('community.categories.v2');
        Cache::forget('community.areas.v3');
    }
}
