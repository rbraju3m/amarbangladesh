<?php

namespace App\Http\Controllers\Admin;

use App\Community\Moderation;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Answer;
use App\Models\Member;
use App\Models\MemberNotification;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Moderation: what readers reported or what got hidden first, then everything, newest first. */
class CommunityController extends Controller
{
    public const ACTIONS = ['hide' => Post::HIDDEN, 'restore' => Post::PUBLISHED, 'remove' => Post::REMOVED];

    public function index(Request $request): View
    {
        $show = $request->query('show') === 'all' ? 'all' : 'attention';
        $needsLook = fn ($q) => $q->where(fn ($q) => $q->where('status', Post::HIDDEN)->orWhere(fn ($q) => $q->where('status', Post::PUBLISHED)->where('reports_count', '>', 0)));

        $posts = Post::with(['member', 'category', 'area', 'photos'])
            ->when($show === 'attention', $needsLook)
            ->when($show === 'all', fn ($q) => $q->whereIn('status', [Post::PUBLISHED, Post::HIDDEN, Post::REMOVED]))
            ->orderByDesc($show === 'attention' ? 'reports_count' : 'id')->orderByDesc('id')
            ->paginate(30, ['*'], 'posts')->withQueryString();

        $answers = Answer::with(['member', 'post:id,title', 'photos'])
            ->when($show === 'attention', $needsLook)
            ->when($show === 'all', fn ($q) => $q->whereIn('status', [Post::PUBLISHED, Post::HIDDEN, Post::REMOVED]))
            ->orderByDesc($show === 'attention' ? 'reports_count' : 'id')->orderByDesc('id')
            ->paginate(30, ['*'], 'answers')->withQueryString();

        $week = now()->subDays(7);

        return view('admin.community', [
            'show' => $show,
            'posts' => $posts,
            'answers' => $answers,
            'stats' => [
                'Posts (7 days)' => Post::where('created_at', '>=', $week)->count(),
                'Answers (7 days)' => Answer::where('created_at', '>=', $week)->count(),
                'New members (7 days)' => Member::where('created_at', '>=', $week)->count(),
                'Unanswered questions' => Post::published()->whereIn('type', ['question', 'help'])->where('answers_count', 0)->count(),
            ],
            // One email = the notices that share member, post and `emailed_at`.
            'emails' => [
                'sent' => (int) MemberNotification::where('emailed_at', '>=', $week)->selectRaw('count(distinct member_id, post_id, emailed_at) as n')->value('n'),
                'reachable' => Member::whereNotNull('email')->where('email_notifications', true)->count(),
                'members' => Member::has('identities')->count(),
            ],
        ]);
    }

    public function moderate(Request $request, string $type, int $id, string $action): RedirectResponse
    {
        $item = Moderation::find($type, $id);
        abort_unless($item, 404);
        Moderation::log($request->user(), $action, $item); // first: a "keep" deletes the reports it records

        if ($action === 'dismiss') {
            Moderation::dismissReports($item);
        } else {
            Moderation::setStatus($item, self::ACTIONS[$action]);
        }

        return back()->with('status', ucfirst($type).' '.($action === 'dismiss' ? 'kept, reports cleared' : $action.'d').'.');
    }

    /** The moderation log: every admin action and automatic hide, newest first (read-only). */
    public function log(Request $request): View
    {
        $action = array_key_exists($request->query('action'), AdminAction::ACTIONS) ? $request->query('action') : null;
        $rows = AdminAction::with('user:id,name,email')->when($action, fn ($q) => $q->where('action', $action))
            ->latest('id')->paginate(50)->withQueryString();

        // Targets in two queries per type, for links and current status.
        $ids = $rows->groupBy('target_type')->map(fn ($g) => $g->pluck('target_id')->unique());
        $targets = [
            'post' => Post::whereIn('id', $ids['post'] ?? [])->get(['id', 'title', 'status'])->keyBy('id'),
            'answer' => Answer::whereIn('id', $ids['answer'] ?? [])->get(['id', 'post_id', 'status'])->keyBy('id'),
            'member' => Member::whereIn('id', $ids['member'] ?? [])->get(['id', 'code', 'name', 'blocked_at', 'deleted_at'])->keyBy('id'),
        ];

        return view('admin.community-log', ['rows' => $rows, 'targets' => $targets, 'action' => $action]);
    }

    public function block(Request $request, Member $member, string $action): RedirectResponse
    {
        $member->update(['blocked_at' => $action === 'block' ? now() : null]);
        Moderation::log($request->user(), $action, $member);

        return back()->with('status', ($member->name ?: 'Member').' '.($action === 'block' ? 'blocked from posting' : 'unblocked').'.');
    }
}
