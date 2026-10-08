<?php

namespace App\Http\Controllers\Site\Community;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Support\Bangla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json(['mine' => array_merge(
            Post::whereIn('id', $ids['post'])->where('member_id', $member->id)->pluck('id')->map(fn ($id) => "post:{$id}")->all(),
            Answer::whereIn('id', $ids['answer'])->where('member_id', $member->id)->pluck('id')->map(fn ($id) => "answer:{$id}")->all(),
        )]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:40']]);
        $member = $request->attributes->get('member');
        $name = Bangla::cleanName($data['name']);
        abort_unless($name, 422, __('নামটা ঠিকমতো লিখুন।'));
        $member->update(['name' => $name]);

        return response()->json(['member' => self::present($member)]);
    }

    public static function present(Member $member): array
    {
        return [
            'code' => $member->code,
            'name' => $member->name,
            'url' => lroute('members.show', $member),
            'account' => $member->hasAccount(),
        ];
    }
}
