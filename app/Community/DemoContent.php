<?php

namespace App\Community;

use App\Models\Answer;
use App\Models\HelpfulMark;
use App\Models\Member;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fictional community content for development and UI review (`database/seeders/data/demo-community.php`).
 * Everything hangs off members with `is_demo`, so it can be removed without touching real people's
 * content. Never in production: both entry points refuse there.
 */
final class DemoContent
{
    /** Posts are spread over this many days before now, oldest first (the feed orders by id). */
    private const SPAN_DAYS = 45;

    public static function guard(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo community content is never added to or removed from production.');
        }
    }

    /** Replaces any earlier demo content. Returns [members, posts, answers]. */
    public static function seed(?array $data = null): array
    {
        self::guard();
        $data ??= require database_path('seeders/data/demo-community.php');
        mt_srand(2026); // same authors, marks and times on every run

        return DB::transaction(function () use ($data) {
            self::purge();

            $now = now();
            $members = [];
            foreach ($data['members'] as $i => [$name, $locale]) {
                $members[] = Member::forceCreate([
                    'code' => 'demo'.Str::lower(Str::random(6)),
                    'name' => $name,
                    'locale' => $locale,
                    'email_notifications' => false,
                    'is_demo' => true,
                    'created_at' => $now->copy()->subDays(self::SPAN_DAYS + 30 - $i),
                    'updated_at' => $now,
                ]);
            }

            $categories = Taxonomy::categories();
            $areas = Taxonomy::areas();
            $count = count($data['posts']);
            $answers = 0;

            foreach ($data['posts'] as $i => $row) {
                [$type, $category, $area, $title, $body, $replies, $accepted, $need] = $row;
                $author = $members[mt_rand(0, count($members) - 1)];
                // Evenly spread with some jitter; never in the future.
                $at = $now->copy()->subMinutes((int) (($count - $i) / $count * self::SPAN_DAYS * 1440) - mt_rand(0, 600));

                $post = Post::forceCreate([
                    'member_id' => $author->id,
                    'is_anonymous' => (bool) ($row[8] ?? false),
                    'type' => $type,
                    'title' => $title,
                    ...self::bodies($body, headings: true),
                    'category_id' => $categories[$category]['id'] ?? null,
                    'area_id' => $area ? ($areas[$area]['id'] ?? null) : null,
                    'status' => Post::PUBLISHED,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                $others = array_values(array_filter($members, fn ($m) => $m->id !== $author->id));
                $answerAt = $at->copy();
                $ids = [];
                foreach ($replies as $j => $reply) {
                    $answerAt = self::later($answerAt, $now, $j === 0 ? mt_rand(20, 600) : mt_rand(10, 900));
                    $answer = Answer::forceCreate([
                        'post_id' => $post->id,
                        'member_id' => $others[mt_rand(0, count($others) - 1)]->id,
                        'is_anonymous' => (bool) ($reply[2] ?? false),
                        ...self::bodies($reply[0]),
                        'status' => Post::PUBLISHED,
                        'created_at' => $answerAt,
                        'updated_at' => $answerAt,
                    ]);
                    self::mark($answer, $reply[1], $others, $answerAt, $now);
                    $answer->timestamps = false; // keep the back-dated updated_at
                    $answer->save();
                    $ids[] = $answer->id;
                    $answers++;
                }

                // Replies: [answer index, [[body, helpful marks, reply index it answers (null = the answer)], …]]
                foreach ($data['replies'][$i] ?? [] as [$answerIndex, $thread]) {
                    $root = Answer::find($ids[$answerIndex]);
                    $made = [];
                    foreach ($thread as [$body, $marks, $to]) {
                        $answerAt = self::later($root->created_at, $now, mt_rand(30, 900) * (count($made) + 1));
                        $parent = $to === null ? $root : $made[$to];
                        $reply = Answer::forceCreate([
                            'post_id' => $post->id, 'parent_id' => $parent->id, 'thread_id' => $root->id,
                            'member_id' => $members[mt_rand(0, count($members) - 1)]->id,
                            'body' => $body, 'status' => Post::PUBLISHED, 'created_at' => $answerAt, 'updated_at' => $answerAt,
                        ]);
                        self::mark($reply, $marks, $members, $answerAt, $now);
                        $reply->timestamps = false;
                        $reply->save();
                        $made[] = $reply;
                        $answers++;
                    }
                    $root->refreshReplyCount();
                }

                self::mark($post, $need, $others, $at, $now);
                $post->timestamps = false;
                $post->forceFill([
                    'answers_count' => count($ids),
                    'accepted_answer_id' => $accepted !== null ? $ids[$accepted] : null,
                ])->save();
            }

            return [count($members), $count, $answers];
        });
    }

    /**
     * Deletes demo members with everything they wrote or marked, then puts right the counts on any
     * real content they touched. Real answers on demo posts go with those posts. Returns [members, posts].
     */
    public static function purge(): array
    {
        self::guard();
        $ids = Member::where('is_demo', true)->pluck('id');
        if ($ids->isEmpty()) {
            return [0, 0];
        }

        return DB::transaction(function () use ($ids) {
            $touchedPosts = Answer::whereIn('member_id', $ids)->distinct()->pluck('post_id');
            $touchedThreads = Answer::whereIn('member_id', $ids)->whereNotNull('thread_id')->distinct()->pluck('thread_id');
            $marked = HelpfulMark::whereIn('member_id', $ids)->get(['markable_type', 'markable_id']);
            $posts = Post::whereIn('member_id', $ids)->count();

            // Accepted answers and notifications point at answers; clear those links first.
            Post::whereIn('accepted_answer_id', Answer::whereIn('member_id', $ids)->select('id'))->update(['accepted_answer_id' => null]);
            Answer::whereIn('member_id', $ids)->delete();
            Post::whereIn('member_id', $ids)->delete(); // answers, notifications, marks cascade
            Member::whereIn('id', $ids)->delete();

            foreach (Post::whereIn('id', $touchedPosts)->get() as $post) {
                $post->refreshAnswerCount();
            }
            foreach (Answer::whereIn('id', $touchedThreads)->get() as $thread) {
                $thread->refreshReplyCount();
            }
            foreach ($marked->groupBy('markable_type') as $type => $rows) {
                $model = $type === 'post' ? Post::class : Answer::class;
                foreach ($model::whereIn('id', $rows->pluck('markable_id'))->get() as $item) {
                    $item->forceFill(['helpful_count' => HelpfulMark::where(['markable_type' => $type, 'markable_id' => $item->id])->count()])->save();
                }
            }

            return [$ids->count(), $posts];
        });
    }

    /** `$n` distinct members (not the author) mark an item helpful, some time after it was written. */
    private static function mark(Post|Answer $item, int $n, array $members, Carbon $after, Carbon $now): void
    {
        $type = $item instanceof Post ? 'post' : 'answer';
        $pool = array_values(array_filter($members, fn ($m) => $m->id !== $item->member_id));
        shuffle($pool);
        $rows = array_map(fn ($member) => [
            'member_id' => $member->id, 'markable_type' => $type, 'markable_id' => $item->id,
            'created_at' => self::later($after, $now, mt_rand(5, 2880)),
        ], array_slice($pool, 0, $n));
        HelpfulMark::insert($rows);
        $item->helpful_count = count($rows); // saved by the caller
    }

    /**
     * `body` and `body_html` for a demo text. Lines starting with "• " or "১. " become lists and
     * **bold** / *italic* become tags, so the UI review shows formatted posts; other text stays plain.
     */
    private static function bodies(?string $text, bool $headings = false): array
    {
        $isList = fn (array $lines, string $pattern) => $lines && collect($lines)->every(fn ($l) => preg_match($pattern, $l));
        $blocks = [];
        $formatted = false;
        foreach (preg_split('~\n{2,}~u', (string) $text) as $block) {
            $lines = explode("\n", e($block));
            $inline = fn (string $s) => preg_replace(['~\*\*(.+?)\*\*~u', '~\*(.+?)\*~u'], ['<strong>$1</strong>', '<em>$1</em>'], $s);
            if ($isList($lines, '~^• ~u')) {
                $blocks[] = '<ul>'.implode('', array_map(fn ($l) => '<li>'.$inline(mb_substr($l, 2)).'</li>', $lines)).'</ul>';
                $formatted = true;
            } elseif ($isList($lines, '~^[০-৯0-9]+\. ~u')) {
                $blocks[] = '<ol>'.implode('', array_map(fn ($l) => '<li>'.$inline(preg_replace('~^[০-৯0-9]+\. ~u', '', $l)).'</li>', $lines)).'</ol>';
                $formatted = true;
            } else {
                $html = $inline(implode('<br>', $lines));
                $formatted = $formatted || $html !== implode('<br>', $lines);
                $blocks[] = "<p>{$html}</p>";
            }
        }
        if ($text === null || ! $formatted) {
            return ['body' => $text];
        }
        $html = RichText::clean(implode('', $blocks), $headings);

        return ['body' => RichText::toText($html), 'body_html' => $html];
    }

    private static function later(Carbon $from, Carbon $now, int $minutes): Carbon
    {
        $at = $from->copy()->addMinutes($minutes);

        return $at->gt($now) ? $now->copy() : $at;
    }
}
