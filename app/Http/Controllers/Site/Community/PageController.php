<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\DivisionMap;
use App\Community\Feed;
use App\Community\Search;
use App\Community\Taxonomy;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Support\Lang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Server-rendered community pages: indexable, cookieless, and they work before any JS runs. */
class PageController extends Controller
{
    /** Posts per list on the home page. */
    private const HOME_LIST = 5;

    private const TICKER = 8;

    /** Top-level answers per page on a post, and replies shown under each before "show more". */
    private const ANSWERS_PAGE = 20;

    private const FIRST_REPLIES = 3;

    /** Posts or answers per page on a member's page. */
    private const PROFILE_PAGE = 20;

    /** Change when the privacy page's content changes. */
    public const PRIVACY_UPDATED = '2026-10-10';

    /** The home page: the community first (ask, the map by division, what's new / unanswered / solved), the quiz as a teaser. */
    public function home(): View
    {
        $lists = [];
        foreach (array_keys(Feed::TABS) as $tab) {
            $lists[$tab] = (new Feed($tab))->posts(self::HOME_LIST)->getCollection();
        }

        return view('community.home', [
            'lists' => $lists,
            // The "happening now" ticker under the hero: the newest posts with where they're from.
            'ticker' => Post::published()->with('area:id,slug,name_bn,name_en')->latest('id')->limit(self::TICKER)
                ->get(['id', 'title', 'type', 'area_id', 'created_at']),
            'stats' => Cache::remember('community.home_stats', 300, fn () => [
                'posts' => Post::published()->count(),
                'answers' => Answer::where('status', Post::PUBLISHED)->count(),
                'solved' => Post::published()->whereNotNull('accepted_answer_id')->count(),
            ]),
            'spots' => DivisionMap::spots(),
            'lens' => collect(DivisionMap::lens())->map(fn ($divisions) => collect($divisions)->map(fn ($d) => [
                'level' => $d['level'], 'latest' => $d['latest'], 'label' => self::areaLabel($d['count'], $d['recent']),
            ])),
            'categories' => Taxonomy::categories(),
        ]);
    }

    public function feed(Request $request): View
    {
        $feed = Feed::fromRequest($request->query());

        return view('community.feed', [
            'feed' => $feed,
            'posts' => $feed->posts(),
            'categories' => Taxonomy::categories(),
            'districts' => Taxonomy::districtsByDivision(),
            'divisions' => array_filter(Taxonomy::areas(), fn ($a) => $a['type'] === 'division'),
        ]);
    }

    /** Search results (not indexed by search engines: endless combinations of the same posts). */
    public function search(Request $request): View
    {
        $query = mb_substr(trim((string) $request->query('q')), 0, 100);

        return view('community.search', ['query' => $query, 'posts' => $query === '' ? null : Search::posts($query)]);
    }

    /** "Has this been asked?" while writing a question: a few close titles, as HTML. */
    public function similar(Request $request): JsonResponse
    {
        $posts = Search::similar(mb_substr((string) $request->query('q'), 0, 200));

        return response()->json([
            'count' => $posts->count(),
            'html' => $posts->isEmpty() ? '' : view('community.partials.similar', ['posts' => $posts])->render(),
        ]);
    }

    /** The next page of feed cards as HTML (same partial as the page), for "load more" and previews. */
    public function feedItems(Request $request): JsonResponse
    {
        $feed = Feed::fromRequest($request->query());
        $posts = $feed->posts(min(max($request->integer('limit', Feed::PER_PAGE), 1), Feed::PER_PAGE));

        return response()->json([
            'html' => view('community.partials.post-list', ['posts' => $posts, 'compact' => $request->boolean('compact')])->render(),
            'count' => $posts->count(),
            'next' => $posts->nextPageUrl() ? lroute('feed', $feed->query(['cursor' => $posts->nextCursor()->encode()])) : null,
        ]);
    }

    /**
     * The home map zoomed into one division: the box to show, its districts drawn as SVG (HTML from
     * `partials/district-map`) and their names and counts for the hover label and the chips.
     */
    public function mapDivision(Request $request, string $division): JsonResponse
    {
        $category = Taxonomy::categories()[(string) $request->query('category')]['id'] ?? null;
        $map = DivisionMap::division($division, $category);
        abort_unless($map, 404);
        $font = round($map['view'][2] / DivisionMap::WIDTH * 14, 2);

        return response()->json([
            'view' => $map['view'],
            'html' => view('community.partials.district-map', ['districts' => $map['districts'], 'font' => $font])->render(),
            'districts' => collect($map['districts'])->map(fn ($d) => [
                'slug' => $d['slug'], 'name' => __(':name জেলা', ['name' => $d['name']]), 'short' => $d['name'],
                'url' => lroute('feed', ['area' => $d['slug']], false), 'x' => $d['x'], 'y' => $d['y'],
                'label' => self::areaLabel($d['count'], $d['recent']), 'latest' => $d['latest'],
            ])->values(),
        ]);
    }

    /** "12 discussions · 3 this week", for the map's hover labels. */
    private static function areaLabel(int $count, int $recent): string
    {
        return Lang::choice(':nটি আলোচনা', $count).($recent ? ' · '.__('এই সপ্তাহে :n', ['n' => Lang::num($recent)]) : '');
    }

    /**
     * A post with its discussion: top-level answers 20 at a time (the solution first, then the most
     * helpful), each with its first replies; `?thread=ID` (from a reply notification) shows a whole thread.
     * An answer that was taken down stays as a placeholder while it has replies, so they keep their context.
     */
    public function show(Request $request, Post $post): View
    {
        abort_unless($post->isPublished(), 404);
        $post->load(['member:id,code,name,deleted_at', 'category', 'area', 'photos']);
        $member = 'member:id,code,name,deleted_at';

        $answers = $post->answers()->whereNull('parent_id')
            ->where(fn ($q) => $q->where('status', Post::PUBLISHED)->orWhere('replies_count', '>', 0))
            ->with([$member, 'photos'])
            ->orderByRaw('id = ? desc', [(int) $post->accepted_answer_id])
            ->orderByDesc('helpful_count')->orderBy('id')
            ->paginate(self::ANSWERS_PAGE)->withQueryString();

        // The first replies of every thread on this page in one query (a window per thread).
        $open = $request->integer('thread');
        $ranked = Answer::query()->select('answers.*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY thread_id ORDER BY id) AS position')
            ->whereIn('thread_id', $answers->pluck('id'))->where('status', Post::PUBLISHED);
        $replies = Answer::query()->fromSub($ranked, 'answers')
            ->where(fn ($q) => $q->where('position', '<=', self::FIRST_REPLIES)->orWhere('thread_id', $open))
            ->with([$member, 'parent.'.$member])->orderBy('id')->get()->groupBy('thread_id');

        $related = Post::published()->whereKeyNot($post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->with(['member:id,code,name,deleted_at', 'category', 'area', 'photos'])
            ->latest('id')->limit(3)->get();

        return view('community.post', ['post' => $post, 'answers' => $answers, 'replies' => $replies, 'related' => $related]);
    }

    public function ask(Request $request): View
    {
        return view('community.ask', [
            'categories' => Taxonomy::categories(),
            'districts' => Taxonomy::districtsByDivision(),
            'types' => Post::TYPES,
            'prefill' => [
                'type' => array_key_exists($request->query('type'), Post::TYPES) ? $request->query('type') : 'question',
                'category' => array_key_exists($request->query('category'), Taxonomy::categories()) ? $request->query('category') : '',
                'area' => array_key_exists($request->query('area'), Taxonomy::areas()) ? $request->query('area') : '',
                'title' => mb_substr(trim((string) $request->query('title')), 0, 200), // from search: "ask this"
            ],
        ]);
    }

    /** A member's public page. Anonymous posts and answers are left out entirely, counts included. */
    public function member(Request $request, Member $member): View
    {
        abort_if($member->isDeleted(), 404);
        $posts = fn () => $member->posts()->published()->where('is_anonymous', false);
        $answers = fn () => $member->answers()->published()->where('is_anonymous', false);
        $tab = $request->query('tab') === 'answers' ? 'answers' : 'posts';

        // One list at a time, keyset-paginated like the feed (endless with JavaScript).
        $items = $tab === 'answers'
            ? $answers()->whereHas('post', fn ($q) => $q->published())->with('post:id,title,type,accepted_answer_id')
            : $posts()->with(['member:id,code,name,deleted_at', 'category', 'area', 'photos']);

        return view('community.member', [
            'member' => $member,
            'tab' => $tab,
            'items' => $items->orderByDesc('id')->cursorPaginate(self::PROFILE_PAGE)->withQueryString(),
            'stats' => [
                'posts' => $posts()->count(),
                'answers' => $answers()->count(),
                'helpful' => (int) $posts()->sum('helpful_count') + (int) $answers()->sum('helpful_count'),
                'solved' => Post::published()->whereIn('accepted_answer_id', $answers()->select('id'))->count(),
            ],
        ]);
    }

    /** "Me": the browser knows who it is, so this page just forwards there (or explains). */
    public function me(): View
    {
        return view('community.me');
    }

    /** What we keep and why. The long text lives in one view per language (`community/privacy/{bn,en}`). */
    public function privacy(): View
    {
        return view('community.privacy', [
            'email' => config('admin.privacy_email'),
            'updated' => Carbon::parse(self::PRIVACY_UPDATED),
        ]);
    }

    /** A shell: the list is fetched with the member token (`NotificationController`). */
    public function notifications(): View
    {
        return view('community.notifications');
    }

    /** Google/Facebook land here with the token in the #fragment; the page stores it and returns. */
    public function authDone(): View
    {
        return view('community.auth-done');
    }

    /** The link from the password-reset email; the token is in the #fragment. */
    public function resetPassword(): View
    {
        return view('community.reset-password');
    }
}
