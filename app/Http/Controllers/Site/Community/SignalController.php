<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Moderation;
use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** The two signals readers send about a post or answer: "this helped" and "this is a problem". */
class SignalController extends Controller
{
    public function helpful(Request $request): JsonResponse
    {
        $item = $this->target($request);
        $member = $request->attributes->get('member');
        abort_if($item->member_id === $member->id, 422, 'নিজের লেখায় দেওয়া যায় না 🙂');

        return response()->json(Moderation::toggleHelpful($member, $item));
    }

    public function report(Request $request): Response
    {
        $request->validate(['reason' => ['required', Rule::in(array_keys(Report::REASONS))]]);
        Moderation::report($request->attributes->get('member'), $this->target($request), $request->input('reason'));

        return response()->noContent();
    }

    private function target(Request $request): mixed
    {
        $data = $request->validate(['type' => ['required', Rule::in(array_keys(Moderation::TYPES))], 'id' => ['required', 'integer']]);
        $item = Moderation::find($data['type'], $data['id']);
        abort_unless($item?->isPublished(), 404);

        return $item;
    }
}
