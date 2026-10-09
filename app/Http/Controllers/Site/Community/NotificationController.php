<?php

namespace App\Http\Controllers\Site\Community;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * The signed-in member's notifications. The /notifications page is a cacheable shell; its list
 * comes from here, rendered by the same Blade partial, with the member token in a header.
 */
class NotificationController extends Controller
{
    public const PER_PAGE = 20;

    /** For the dot on "আমি": one indexed count per page load. */
    public function unread(Request $request): JsonResponse
    {
        return response()->json(['count' => $request->attributes->get('member')->notifications()->unread()->visible()->count()]);
    }

    /** Newest first, keyset by id (`before`). Opening the list doesn't mark it read; the page does that next. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['before' => ['nullable', 'integer']]);
        $member = $request->attributes->get('member');

        $items = $member->notifications()->visible()
            ->when($data['before'] ?? null, fn ($q, $before) => $q->where('id', '<', $before))
            ->with(['post:id,title,type', 'answer:id,post_id,thread_id,member_id,is_anonymous,body', 'answer.member:id,code,name,deleted_at'])
            ->latest('id')->limit(self::PER_PAGE + 1)->get();
        $more = $items->count() > self::PER_PAGE;
        $items = $items->take(self::PER_PAGE);

        return response()->json([
            'html' => view('community.partials.notification-list', ['notifications' => $items])->render(),
            'next' => $more ? $items->last()->id : null,
            'email' => ['address' => (bool) $member->email, 'on' => $member->email_notifications],
        ]);
    }

    public function read(Request $request): Response
    {
        $request->attributes->get('member')->notifications()->unread()->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function unsubscribe(Request $request, Member $member): View
    {
        if ($request->isMethod('post')) {
            $member->update(['email_notifications' => false]);
        }

        return view('community.unsubscribe', ['done' => $request->isMethod('post'), 'action' => $request->fullUrl()]);
    }

    public function settings(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'boolean']]);
        $member = $request->attributes->get('member');
        $member->update(['email_notifications' => $data['email']]);

        return response()->json(['email' => ['address' => (bool) $member->email, 'on' => $member->email_notifications]]);
    }
}
