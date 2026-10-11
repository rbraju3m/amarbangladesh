<?php

namespace App\Models;

use App\Community\Taxonomy;
use App\Models\Concerns\Bilingual;
use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * A public helpline number (feature plan chunk 12), managed at /admin/helplines and listed at /helplines.
 * Only admins add them; one can be switched on only once someone has checked the number (`last_checked_at`).
 */
class Helpline extends Model
{
    use Bilingual;

    /** kind => [label (Bangla text as the translation key, or a context key), emoji], in display order */
    public const KINDS = [
        'emergency' => ['helpline.kind.emergency', '🚨'], // a context key: "জরুরি" alone means "Urgent" elsewhere
        'women_children' => ['নারী ও শিশু', '🧒'],
        'health' => ['স্বাস্থ্য', '🩺'],
        'legal' => ['আইনি সহায়তা', '⚖️'],
        'disaster' => ['দুর্যোগ', '🌀'],
        'government' => ['সরকারি সেবা', '🏛️'],
        'other' => ['অন্যান্য', '📞'],
    ];

    private const CACHE_KEY = 'helplines.live.v1';

    protected $fillable = ['name_bn', 'name_en', 'number', 'kind', 'area_id', 'note_bn', 'note_en', 'last_checked_at', 'source', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['last_checked_at' => 'date', 'is_active' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Every switched-on helpline as arrays (the cache refuses objects), in kind order, then the admin's order.
     *
     * @return list<array{id: int, name_bn: string, name_en: ?string, number: string, kind: string, area_id: ?int, note_bn: ?string, note_en: ?string, last_checked_at: ?string}>
     */
    public static function live(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::where('is_active', true)->get()
            ->sortBy(fn (self $h) => [array_search($h->kind, array_keys(self::KINDS), true), $h->sort_order, $h->id])
            ->map(fn (self $h) => $h->only(['id', 'name_bn', 'name_en', 'number', 'kind', 'area_id', 'note_bn', 'note_en']) + [
                'last_checked_at' => $h->last_checked_at?->toDateString(),
            ])->values()->all());
    }

    /**
     * The live list for one place: nationwide numbers plus the area's own. A division also brings its
     * districts' numbers; a district brings its division's.
     *
     * @param  ?array  $area  a row from Taxonomy::areas()
     */
    public static function forArea(?array $area): array
    {
        $rows = self::live();
        if (! $area) {
            return $rows;
        }
        $areas = Taxonomy::areas();
        $ids = collect($areas)->filter(fn ($a) => $a['slug'] === $area['slug']
            || ($area['type'] === 'division' && $a['division_slug'] === $area['slug'])
            || ($area['type'] === 'district' && $a['slug'] === $area['division_slug']))->pluck('id')->all();

        return array_values(array_filter($rows, fn ($h) => $h['area_id'] === null || in_array((int) $h['area_id'], $ids, true)));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function kindLabel(string $kind): string
    {
        return __(self::KINDS[$kind][0] ?? self::KINDS['other'][0]);
    }

    /** `tel:` target: digits and a leading + only. */
    public static function telHref(string $number): string
    {
        return 'tel:'.preg_replace('/[^\d+]/', '', $number);
    }

    /** The number as shown: Bangla digits on Bangla pages. */
    public static function display(string $number): string
    {
        return Lang::num($number);
    }
}
