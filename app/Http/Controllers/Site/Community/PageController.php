<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Feed;
use App\Community\Taxonomy;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Server-rendered community pages: indexable, cookieless, and they work before any JS runs. */
class PageController extends Controller
{
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

    public function show(Post $post): View
    {
        abort_unless($post->isPublished(), 404);
        $post->load(['member:id,code,name', 'category', 'area']);

        $answers = $post->answers()->published()->with('member:id,code,name')
            ->orderByRaw('id = ? desc', [(int) $post->accepted_answer_id])
            ->orderByDesc('helpful_count')->orderBy('id')
            ->limit(200)->get();

        $related = Post::published()->whereKeyNot($post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->with(['member:id,code,name', 'category', 'area'])
            ->latest('id')->limit(3)->get();

        return view('community.post', ['post' => $post, 'answers' => $answers, 'related' => $related]);
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
            ],
        ]);
    }

    /** A member's public page. Anonymous posts and answers are left out entirely, counts included. */
    public function member(Member $member): View
    {
        $posts = fn () => $member->posts()->published()->where('is_anonymous', false);
        $answers = fn () => $member->answers()->published()->where('is_anonymous', false);

        return view('community.member', [
            'member' => $member,
            'posts' => $posts()->with(['member:id,code,name', 'category', 'area'])->latest('id')->limit(30)->get(),
            'answers' => $answers()->whereHas('post', fn ($q) => $q->published())
                ->with('post:id,title,type,accepted_answer_id')->latest('id')->limit(30)->get(),
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
