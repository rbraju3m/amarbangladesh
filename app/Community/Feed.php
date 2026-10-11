<?php

namespace App\Community;

use App\Models\Member;
use App\Models\Post;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

/**
 * The community feed: newest first, keyset-paginated (cheap at any depth, stable while new posts
 * arrive). Tabs and filters only narrow the query, so "popular", "near you" or "for you" can be
 * added later as new tabs without touching the cards or the endpoint.
 */
final class Feed
{
    public const TABS = ['latest' => 'নতুন', 'unanswered' => 'উত্তর দরকার', 'solved' => 'সমাধান হয়েছে'];

    public const PER_PAGE = 15;

    public function __construct(
        public readonly string $tab = 'latest',
        public readonly ?array $category = null,
        public readonly ?array $area = null,
    ) {}

    public static function fromRequest(array $query): self
    {
        $categories = Taxonomy::allCategories(); // turned-off topics still filter (old links)
        $areas = Taxonomy::areas();

        return new self(
            array_key_exists($query['tab'] ?? '', self::TABS) ? $query['tab'] : 'latest',
            $categories[$query['category'] ?? ''] ?? null,
            $areas[$query['area'] ?? ''] ?? null,
        );
    }

    public function posts(int $perPage = self::PER_PAGE): CursorPaginator
    {
        $areaIds = $this->areaIds();

        return Post::published()->open() // lost & found and jobs that ran out keep their page, not a place here
            ->with(['member:'.Member::AUTHOR_COLUMNS, 'category:id,slug,name_bn,name_en,emoji', 'area:id,slug,name_bn,name_en', 'photos'])
            ->when($this->tab === 'unanswered', fn ($q) => $q->where('answers_count', 0)->whereIn('type', ['question', 'help']))
            ->when($this->tab === 'solved', fn ($q) => $q->whereNotNull('accepted_answer_id'))
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category['id']))
            ->when($areaIds, fn ($q) => $q->whereIn('area_id', $areaIds))
            ->orderByDesc('id')
            ->cursorPaginate($perPage)
            ->withQueryString();
    }

    /**
     * Urgent help posts that haven't run out, in this view's area and topic (the tab doesn't apply),
     * newest first: the strip on the home page and above the map's area panel.
     */
    public function urgent(int $limit = 6): Collection
    {
        $areaIds = $this->areaIds();

        return Post::published()->urgent()
            ->with(['member:'.Member::AUTHOR_COLUMNS, 'area:id,slug,name_bn,name_en'])
            ->when($this->category, fn ($q) => $q->where('category_id', $this->category['id']))
            ->when($areaIds, fn ($q) => $q->whereIn('area_id', $areaIds))
            ->orderByDesc('id')->limit($limit)->get();
    }

    /** The area's ids (a division covers its districts), or null for the whole country. */
    private function areaIds(): ?array
    {
        if (! $this->area) {
            return null;
        }

        return $this->area['type'] === 'division'
            ? array_merge([$this->area['id']], array_column(array_filter(Taxonomy::areas(), fn ($a) => $a['division_bn'] === $this->area['name_bn'] && $a['type'] === 'district'), 'id'))
            : [$this->area['id']];
    }

    /** The query string for this view with some keys replaced (null drops one), for tab/filter links. */
    public function query(array $override = []): array
    {
        return array_filter(array_merge([
            'tab' => $this->tab === 'latest' ? null : $this->tab,
            'category' => $this->category['slug'] ?? null,
            'area' => $this->area['slug'] ?? null,
        ], $override), fn ($v) => $v !== null);
    }

    public function isFiltered(): bool
    {
        return $this->category || $this->area || $this->tab !== 'latest';
    }
}
