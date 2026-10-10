<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Moderation;
use App\Community\Notifier;
use App\Community\Photos;
use App\Community\RichText;
use App\Community\Taxonomy;
use App\Community\Text;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Answer;
use App\Models\Member;
use App\Models\Post;
use App\Support\Bangla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PostController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        [$body, $html] = RichText::input($request, headings: true);
        $request->merge(['title' => Text::line($request->input('title')), 'body' => $body]);
        $data = $request->validate([
            'type' => ['nullable', Rule::in(array_keys(Post::TYPES))],
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(array_keys(Taxonomy::categories()))],
            'area' => ['nullable', Rule::in(array_keys(Taxonomy::areas()))],
            'name' => ['nullable', 'string', 'max:40'],
            'anonymous' => ['nullable', 'boolean'],
        ], self::messages());

        $photos = Photos::input($request, anonymous: (bool) ($data['anonymous'] ?? false));
        $member = self::named($request->attributes->get('member'), $data['name'] ?? null)->rememberLocale();
        self::checkLinks($data['title'], $data['body'] ?? null, $html);

        // A double tap or a retry after a slow network should not post twice. Photos that can't be
        // attached undo the post, so a retry starts clean.
        $post = DB::transaction(function () use ($member, $data, $html, $photos) {
            $post = Post::where('member_id', $member->id)->where('title', $data['title'])->where('created_at', '>=', now()->subMinutes(10))->first()
                ?? Post::create([
                    'member_id' => $member->id,
                    'is_anonymous' => (bool) ($data['anonymous'] ?? false),
                    'type' => $data['type'] ?? 'question',
                    'title' => $data['title'],
                    'body' => $data['body'] ?? null,
                    'body_html' => $html,
                    'category_id' => Taxonomy::categories()[$data['category'] ?? '']['id'] ?? null,
                    'area_id' => Taxonomy::areas()[$data['area'] ?? '']['id'] ?? null,
                ]);
            if ($photos !== null) {
                Photos::attach($member, $post, $photos);
            }

            return $post;
        });

        if ($post->wasRecentlyCreated) {
            AnalyticsEvent::server('post_created', $request, ['type' => $post->type, 'anon' => $post->is_anonymous ? 1 : 0, 'rich' => RichText::isFormatted($post->body_html) ? 1 : 0, 'photos' => count($photos ?? [])]);
        }

        return response()->json(['post' => ['id' => $post->id, 'url' => $post->url()], 'member' => MemberController::present($member)], 201);
    }

    /** The author edits the title and details; the type, topic and area stay. Returns them re-rendered. */
    public function update(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->member_id === $request->attributes->get('member')->id, 403);
        abort_unless($post->isPublished(), 404);
        [$body, $html] = RichText::input($request, headings: true);
        $request->merge(['title' => Text::line($request->input('title')), 'body' => $body]);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'body' => ['nullable', 'string', 'max:5000'],
        ], self::messages());
        self::checkLinks($data['title'], $data['body'] ?? null, $html);
        $photos = Photos::input($request, anonymous: $post->is_anonymous);

        DB::transaction(function () use ($request, $post, $data, $html, $photos) {
            $changed = $data['title'] !== $post->title || ($data['body'] ?? null) !== $post->body || $html !== $post->body_html;
            if ($photos !== null && array_map('intval', $photos) !== Photos::idsOf($post)) {
                Photos::attach($request->attributes->get('member'), $post, $photos);
                $changed = true;
            }
            if ($changed) {
                $post->forceFill(['title' => $data['title'], 'body' => $data['body'] ?? null, 'body_html' => $html, 'edited_at' => now()])->save();
            }
        });

        return response()->json([
            'title' => $post->title,
            'body' => $post->body,
            'body_html' => $post->body ? RichText::render($post)->toHtml() : '',
            'raw_html' => $post->body_html,
            'photos' => $post->photos()->get()->map->present()->all(),
        ]);
    }

    /** At most Text::MAX_LINKS links in the title and details together; formatted details count their <a> tags. */
    private static function checkLinks(string $title, ?string $body, ?string $html): void
    {
        $links = Text::linkCount($title) + ($html !== null ? RichText::linkCount($html) : Text::linkCount($body));
        if ($links > Text::MAX_LINKS) {
            throw ValidationException::withMessages(['body' => __('একটা পোস্টে দুটোর বেশি লিংক দেওয়া যাবে না।')]);
        }
        if ($html !== null && mb_strlen($html) > RichText::MAX_HTML) {
            throw ValidationException::withMessages(['body' => __('বিস্তারিত অংশটা একটু ছোট করুন।')]);
        }
    }

    private static function messages(): array
    {
        return [
            'title.required' => __('কী জানতে চান, সেটা লিখুন।'),
            'title.min' => __('আরেকটু খুলে লিখুন, যাতে সবাই বুঝতে পারে।'),
            'title.max' => __('মূল কথাটা ছোট করে লিখুন (২০০ অক্ষরের মধ্যে), বাকিটা বিস্তারিত অংশে।'),
            'body.max' => __('বিস্তারিত অংশটা একটু ছোট করুন।'),
        ];
    }

    /** The author takes their own post down. */
    public function destroy(Request $request, Post $post): Response
    {
        abort_unless($post->member_id === $request->attributes->get('member')->id, 403);
        Moderation::setStatus($post, Post::DELETED); // its photos go too

        return response()->noContent();
    }

    /** The author marks the answer that solved it (or clears the mark with answer: null). */
    public function accept(Request $request, Post $post): JsonResponse
    {
        abort_unless($post->member_id === $request->attributes->get('member')->id, 403);
        $data = $request->validate(['answer' => ['nullable', 'integer']]);

        $answer = isset($data['answer']) ? $post->answers()->published()->whereNull('parent_id')->find($data['answer']) : null; // replies can't be the solution
        abort_if(isset($data['answer']) && (! $answer || $answer->member_id === $post->member_id), 422);
        $post->update(['accepted_answer_id' => $answer?->id]);
        if ($answer) {
            Notifier::accepted($answer->setRelation('post', $post));
        }

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
            throw ValidationException::withMessages(['name' => __('আপনার নামটা লিখুন, সবাই যাতে চিনতে পারে।')]);
        }

        return $member;
    }
}
