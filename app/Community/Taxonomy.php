<?php

namespace App\Community;

use App\Models\Area;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;

/**
 * Categories and areas as cached arrays (they change only through migrations or a future admin
 * editor, which must call forget()). Arrays, not models: the cache refuses to unserialize objects.
 */
final class Taxonomy
{
    /** @return array<string, array{id: int, slug: string, name: string, emoji: string}> by slug */
    public static function categories(): array
    {
        return Cache::rememberForever('community.categories', fn () => Category::where('is_active', true)->orderBy('sort_order')->get()
            ->mapWithKeys(fn (Category $c) => [$c->slug => ['id' => $c->id, 'slug' => $c->slug, 'name' => $c->name_bn, 'emoji' => $c->emoji]])
            ->all());
    }

    /** @return array<string, array{id: int, slug: string, name: string, division: ?string}> by slug, divisions first */
    public static function areas(): array
    {
        return Cache::rememberForever('community.areas', function () {
            $all = Area::orderBy('sort_order')->orderBy('name_bn')->get();
            $divisions = $all->where('type', 'division')->keyBy('id');

            return $all->sortBy(fn (Area $a) => $a->type === 'division' ? 0 : 1)
                ->mapWithKeys(fn (Area $a) => [$a->slug => [
                    'id' => $a->id, 'slug' => $a->slug, 'name' => $a->name_bn, 'type' => $a->type,
                    'division' => $divisions[$a->parent_id]->name_bn ?? null,
                ]])->all();
        });
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
        Cache::forget('community.categories');
        Cache::forget('community.areas');
    }
}
