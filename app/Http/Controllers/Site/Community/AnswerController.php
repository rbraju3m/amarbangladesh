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
    /** Returns the answer rendered by the same partial as the page, so the client never re-implements it. */
    public function store(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->isPublished(), 404);
        $request->merge(['body' => Text::clean($request->input('body'))]);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'name' => ['nullable', 'string', 'max:40'],
            'anonymous' => ['nullable', 'boolean'],
        ], [
            'body.required' => __('উত্তরটা লিখুন।'),
            'body.max' => __('উত্তরটা একটু ছোট করুন।'),
        ]);

        $member = PostController::named($request->attributes->get('member'), $data['name'] ?? null)->rememberLocale();
        if (Text::linkCount($data['body']) > Text::MAX_LINKS) {
            throw ValidationException::withMessages(['body' => __('একটা উত্তরে দুটোর বেশি লিংক দেওয়া যাবে না।')]);
        }

        $answer = Answer::where(['post_id' => $post->id, 'member_id' => $member->id, 'body' => $data['body']])->where('created_at', '>=', now()->subMinutes(10))->first()
            ?? Answer::create(['post_id' => $post->id, 'member_id' => $member->id, 'is_anonymous' => (bool) ($data['anonymous'] ?? false), 'body' => $data['body']]);

        if ($answer->wasRecentlyCreated) {
            $post->refreshAnswerCount();
            Notifier::answered($answer);
            AnalyticsEvent::server('answer_created', $request, ['type' => $post->type]);
        }
        $answer->setRelation('member', $member);

        return response()->json([
            'answer' => ['id' => $answer->id],
            'answers_count' => $post->answers_count,
            'html' => view('community.partials.answer', ['answer' => $answer, 'post' => $post])->render(),
            'member' => MemberController::present($member),
        ], 201);
    }

    public function destroy(Request $request, Answer $answer): Response
    {
        abort_unless($answer->member_id === $request->attributes->get('member')->id, 403);
        Moderation::setStatus($answer, Post::DELETED);

        return response()->noContent();
    }
}
