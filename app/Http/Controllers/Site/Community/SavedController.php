<?php

namespace App\Http\Controllers\Site\Community;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\SavedPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saved posts: a private reading list. The /saved page is a cacheable shell; its list comes from
 * here, rendered with the feed's own partials, with the member token in a header (like notifications).
 */
class SavedController extends Controller
{
    public const PER_PAGE = 20;

    /** Saves (`saved` true) or unsaves a post; repeating either is harmless. Only published posts can be saved. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['post' => ['required', 'integer'], 'saved' => ['required', 'boolean']]);
        $member = $request->attributes->get('member');

        if ($data['saved']) {
            $post = Post::published()->find($data['post']);
            abort_unless($post, 404, __('পোস্টটা আর নেই।'));
            SavedPost::firstOrCreate(['member_id' => $member->id, 'post_id' => $post->id]);
        } else {
            $member->savedPosts()->where('post_id', $data['post'])->delete();
        }

        return response()->json(['saved' => $data['saved']]);
    }

    /**
     * Newest saved first, keyset by the saved row (`before`). Posts that were taken down drop out
     * (and come back if restored); the row itself stays until the member unsaves it.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['before' => ['nullable', 'integer']]);
        $member = $request->attributes->get('member');

        $rows = $member->savedPosts()
            ->whereHas('post', fn ($q) => $q->where('status', Post::PUBLISHED))
            ->when($data['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->with(['post' => fn ($q) => $q->with(['member:id,code,name,deleted_at', 'category:id,slug,name_bn,name_en,emoji', 'area:id,slug,name_bn,name_en', 'photos'])])
            ->latest('id')->limit(self::PER_PAGE + 1)->get();
        $more = $rows->count() > self::PER_PAGE;
        $rows = $rows->take(self::PER_PAGE);

        return response()->json([
            'html' => view('community.partials.post-list', ['posts' => $rows->pluck('post')])->render(),
            'ids' => $rows->pluck('post_id'),
            'next' => $more ? $rows->last()->id : null,
        ]);
    }
}
