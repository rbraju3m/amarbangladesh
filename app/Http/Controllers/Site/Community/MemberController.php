<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Accounts;
use App\Community\Taxonomy;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Support\Bangla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    /** Who this browser is signed in as. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['member' => self::present($request->attributes->get('member'))]);
    }

    /**
     * Which of these items ("post:12", "answer:40") are the signed-in member's. Pages never print an
     * author's code for anonymous items, so owner controls ask here instead.
     */
    public function mine(Request $request): JsonResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:200'], 'items.*' => ['string', 'regex:/^(post|answer):\d+$/']]);
        $ids = ['post' => [], 'answer' => []];
        foreach ($data['items'] as $item) {
            [$type, $id] = explode(':', $item);
            $ids[$type][] = (int) $id;
        }
        $member = $request->attributes->get('member');

        return response()->json([
            'mine' => array_merge(
                Post::whereIn('id', $ids['post'])->where('member_id', $member->id)->pluck('id')->map(fn ($id) => "post:{$id}")->all(),
                Answer::whereIn('id', $ids['answer'])->where('member_id', $member->id)->pluck('id')->map(fn ($id) => "answer:{$id}")->all(),
            ),
            // Which of these posts the member saved (the bookmark buttons' state).
            'saved' => $ids['post'] ? $member->savedPosts()->whereIn('post_id', $ids['post'])->pluck('post_id')->map(fn ($id) => "post:{$id}")->all() : [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $districts = array_keys(array_filter(Taxonomy::areas(), fn ($a) => $a['type'] === 'district'));
        $data = $request->validate([
            'name' => [Rule::requiredIf(! $request->exists('district')), 'string', 'max:40'],
            'district' => ['nullable', 'string', Rule::in($districts)], // '' or null clears it
        ]);
        $member = $request->attributes->get('member');
        if (array_key_exists('name', $data)) {
            $name = Bangla::cleanName($data['name']);
            abort_unless($name, 422, __('নামটা ঠিকমতো লিখুন।'));
            $member->name = $name;
        }
        if ($request->exists('district')) {
            $member->district_id = filled($data['district'] ?? null) ? Taxonomy::areas()[$data['district']]['id'] : null;
        }
        $member->save();

        return response()->json(['member' => self::present($member)]);
    }

    /** Deletes the signed-in account; `confirm` must be its code, so a stray request can't do it. */
    public function destroy(Request $request): Response
    {
        $member = $request->attributes->get('member');
        $data = $request->validate(['confirm' => ['required', 'string', Rule::in([$member->code])], 'content' => ['nullable', 'boolean']]);

        Accounts::delete($member, (bool) ($data['content'] ?? false));
        AnalyticsEvent::server('account_deleted', $request, ['content' => (int) ($data['content'] ?? false)]);

        return response()->noContent();
    }

    public static function present(Member $member): array
    {
        return [
            'code' => $member->code,
            'name' => $member->name,
            'url' => lroute('members.show', $member),
            'account' => $member->hasAccount(),
            // Private: only ever sent to the member themselves (this endpoint and sign-in answers).
            'district' => self::district($member->district_id),
        ];
    }

    /** The home district for the browser: slug, its division's slug and both names (the page language can change). */
    private static function district(?int $id): ?array
    {
        $area = $id ? collect(Taxonomy::areas())->firstWhere('id', $id) : null;

        return $area ? [
            'slug' => $area['slug'], 'division' => $area['division_slug'], 'name_bn' => $area['name_bn'], 'name_en' => $area['name_en'],
        ] : null;
    }
}
