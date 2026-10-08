<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Moderation;
use App\Community\Taxonomy;
use App\Community\Text;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Support\Bangla;
use App\Support\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->merge(['title' => Text::line($request->input('title')), 'body' => Text::clean($request->input('body'))]);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Post::TYPES))],
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(array_keys(Taxonomy::categories()))],
            'area' => ['nullable', Rule::in(array_keys(Taxonomy::areas()))],
            'name' => ['nullable', 'string', 'max:40'],
        ], [
            'title.required' => 'কী জানতে চাও, সেটা লেখো।',
            'title.min' => 'আরেকটু খুলে লেখো, যাতে সবাই বুঝতে পারে।',
            'title.max' => 'মূল কথাটা ছোট করে লেখো (২০০ অক্ষরের মধ্যে), বাকিটা বিস্তারিত অংশে।',
            'body.max' => 'বিস্তারিত অংশটা একটু ছোট করো।',
        ]);

        $member = self::named($request->attributes->get('member'), $data['name'] ?? null);
        if (Text::linkCount($data['title'].' '.($data['body'] ?? '')) > Text::MAX_LINKS) {
            throw ValidationException::withMessages(['body' => 'একটা পোস্টে দুটোর বেশি লিংক দেওয়া যাবে না।']);
        }

        // A double tap or a retry after a slow network should not post twice.
        $post = Post::where('member_id', $member->id)->where('title', $data['title'])->where('created_at', '>=', now()->subMinutes(10))->first()
            ?? Post::create([
                'member_id' => $member->id,
                'type' => $data['type'] ?? 'question',
                'title' => $data['title'],
                'body' => $data['body'] ?? null,
                'category_id' => Taxonomy::categories()[$data['category'] ?? '']['id'] ?? null,
                'area_id' => Taxonomy::areas()[$data['area'] ?? '']['id'] ?? null,
            ]);

        if ($post->wasRecentlyCreated) {
            AnalyticsEvent::create(['name' => 'post_created', 'device' => Device::fromUserAgent($request->userAgent()), 'meta' => ['type' => $post->type]]);
        }

        return response()->json(['post' => ['id' => $post->id, 'url' => $post->url()], 'member' => MemberController::present($member)], 201);
    }

    /** The author takes their own post down. */
    public function destroy(Request $request, Post $post): Response
    {
        abort_unless($post->member_id === $request->attributes->get('member')->id, 403);
        Moderation::setStatus($post, Post::DELETED);

        return response()->noContent();
    }

    /** The author marks the answer that solved it (or clears the mark with answer: null). */
    public function accept(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->member_id === $request->attributes->get('member')->id, 403);
        $data = $request->validate(['answer' => ['nullable', 'integer']]);

        $answer = isset($data['answer']) ? $post->answers()->published()->find($data['answer']) : null;
        abort_if(isset($data['answer']) && (! $answer || $answer->member_id === $post->member_id), 422);
        $post->update(['accepted_answer_id' => $answer?->id]);

        return response()->json(['accepted' => $answer?->id]);
    }

    /** Posting and answering need a name; it can be given with the first post. */
    public static function named(Member $member, ?string $name): Member
    {
        $name = Bangla::cleanName($name);
        if ($name && $name !== $member->name) {
            $member->update(['name' => $name]);
        }
        if (! $member->name) {
            throw ValidationException::withMessages(['name' => 'তোমার নামটা লেখো, সবাই যাতে চিনতে পারে।']);
        }

        return $member;
    }
}
