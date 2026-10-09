<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Moderation;
use App\Community\Notifier;
use App\Community\Text;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Answer;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AnswerController extends Controller
{
    /** Replies per "show more" in a thread. */
    public const REPLIES_PAGE = 20;

    /** Returns the answer rendered by the same partial as the page, so the client never re-implements it. */
    public function store(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->isPublished(), 404);
        $request->merge(['body' => Text::clean($request->input('body'))]);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'name' => ['nullable', 'string', 'max:40'],
            'anonymous' => ['nullable', 'boolean'],
            'parent' => ['nullable', 'integer'],
        ], [
            'body.required' => __('উত্তরটা লিখুন।'),
            'body.max' => __('উত্তরটা একটু ছোট করুন।'),
        ]);

        $member = PostController::named($request->attributes->get('member'), $data['name'] ?? null)->rememberLocale();
        if (Text::linkCount($data['body']) > Text::MAX_LINKS) {
            throw ValidationException::withMessages(['body' => __('একটা উত্তরে দুটোর বেশি লিংক দেওয়া যাবে না।')]);
        }

        // A reply keeps what it answers (`parent_id`) and the top-level answer it sits under (`thread_id`).
        $parent = isset($data['parent']) ? $post->answers()->published()->findOrFail($data['parent']) : null;
        $thread = $parent ? ($parent->thread_id ?? $parent->id) : null;

        $answer = Answer::where(['post_id' => $post->id, 'parent_id' => $parent?->id, 'member_id' => $member->id, 'body' => $data['body']])->where('created_at', '>=', now()->subMinutes(10))->first()
            ?? Answer::create(['post_id' => $post->id, 'parent_id' => $parent?->id, 'thread_id' => $thread, 'member_id' => $member->id, 'is_anonymous' => (bool) ($data['anonymous'] ?? false), 'body' => $data['body'], 'status' => Post::PUBLISHED]);

        if ($answer->wasRecentlyCreated) {
            if ($parent) {
                $answer->thread->refreshReplyCount();
                Notifier::replied($answer->setRelation('parent', $parent));
                AnalyticsEvent::server('reply_created', $request, ['type' => $post->type]);
            } else {
                $post->refreshAnswerCount();
                Notifier::answered($answer);
                AnalyticsEvent::server('answer_created', $request, ['type' => $post->type]);
            }
        }
        $answer->setRelation('member', $member);

        return response()->json([
            'answer' => ['id' => $answer->id, 'thread' => $thread],
            'answers_count' => $post->answers_count,
            'html' => $parent
                ? view('community.partials.reply', ['reply' => $answer->setRelation('parent', $parent->loadMissing('member')), 'post' => $post])->render()
                : view('community.partials.thread', ['answer' => $answer, 'post' => $post, 'replies' => collect()])->render(),
            'member' => MemberController::present($member),
        ], 201);
    }

    /** The author edits an answer or reply; returns it re-rendered. */
    public function update(Request $request, Answer $answer): JsonResponse
    {
        abort_unless($answer->member_id === $request->attributes->get('member')->id, 403);
        abort_unless($answer->isPublished() && $answer->post->isPublished(), 404);
        $request->merge(['body' => Text::clean($request->input('body'))]);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        if (Text::linkCount($data['body']) > Text::MAX_LINKS) {
            throw ValidationException::withMessages(['body' => __('একটা উত্তরে দুটোর বেশি লিংক দেওয়া যাবে না।')]);
        }

        if ($data['body'] !== $answer->body) {
            $answer->forceFill(['body' => $data['body'], 'edited_at' => now()])->save();
        }
        $answer->load(['member:id,code,name,deleted_at', 'parent.member:id,code,name,deleted_at']);

        return response()->json(['html' => view($answer->isReply() ? 'community.partials.reply' : 'community.partials.answer', [
            'answer' => $answer, 'reply' => $answer, 'post' => $answer->post,
        ])->render()]);
    }

    /** More replies in a thread (after the first few shown on the page), as HTML. */
    public function replies(Request $request, Answer $answer): JsonResponse
    {
        abort_unless($answer->thread_id === null && $answer->post->isPublished(), 404);
        $replies = Answer::where('thread_id', $answer->id)->published()
            ->where('id', '>', $request->integer('after'))
            ->with(['member:id,code,name,deleted_at', 'parent.member:id,code,name,deleted_at'])
            ->orderBy('id')->limit(self::REPLIES_PAGE + 1)->get();
        $more = $replies->count() > self::REPLIES_PAGE;
        $replies = $replies->take(self::REPLIES_PAGE);

        return response()->json([
            'html' => $replies->map(fn ($reply) => view('community.partials.reply', ['reply' => $reply, 'post' => $answer->post])->render())->join(''),
            'last' => $replies->last()?->id,
            'more' => $more,
        ]);
    }

    public function destroy(Request $request, Answer $answer): Response
    {
        abort_unless($answer->member_id === $request->attributes->get('member')->id, 403);
        Moderation::setStatus($answer, Post::DELETED);

        return response()->noContent();
    }
}
