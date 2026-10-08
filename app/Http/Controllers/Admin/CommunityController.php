<?php

namespace App\Http\Controllers\Admin;

use App\Community\Moderation;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Member;
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

        $posts = Post::with(['member', 'category', 'area'])
            ->when($show === 'attention', $needsLook)
            ->when($show === 'all', fn ($q) => $q->whereIn('status', [Post::PUBLISHED, Post::HIDDEN, Post::REMOVED]))
            ->orderByDesc($show === 'attention' ? 'reports_count' : 'id')->orderByDesc('id')
            ->paginate(30, ['*'], 'posts')->withQueryString();

        $answers = Answer::with(['member', 'post:id,title'])
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
        ]);
    }

    public function moderate(string $type, int $id, string $action): RedirectResponse
    {
        $item = Moderation::find($type, $id);
        abort_unless($item, 404);

        if ($action === 'dismiss') {
            Moderation::dismissReports($item);
        } else {
            Moderation::setStatus($item, self::ACTIONS[$action]);
        }

        return back()->with('status', ucfirst($type).' '.($action === 'dismiss' ? 'kept, reports cleared' : $action.'d').'.');
    }

    public function block(Member $member, string $action): RedirectResponse
    {
        $member->update(['blocked_at' => $action === 'block' ? now() : null]);

        return back()->with('status', ($member->name ?: 'Member').' '.($action === 'block' ? 'blocked from posting' : 'unblocked').'.');
    }
}
