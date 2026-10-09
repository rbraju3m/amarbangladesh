<?php

namespace App\Community;

use App\Models\Post;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Search over published posts (title and body), on the `posts_search` FULLTEXT index with MySQL's
 * ngram parser. Each word of the query is matched as a phrase, i.e. as a substring, which suits
 * Bangla (no dictionary needed, and suffixes like -র / -এর still match). Search wants every word;
 * "similar questions" while asking wants any word and then keeps the closest titles.
 */
final class Search
{
    public const PER_PAGE = 20;

    /** Longer queries are cut to this many words. */
    private const MAX_TERMS = 8;

    /** Too common to tell questions apart (Bangla and English); ignored when finding similar titles. */
    private const STOPWORDS = [
        'আমি', 'আমার', 'আমাদের', 'আপনি', 'আপনার', 'কি', 'কী', 'কেন', 'কোন', 'কোনো', 'কোথায়', 'কীভাবে', 'কিভাবে', 'কখন', 'কত',
        'করব', 'করবো', 'করতে', 'করা', 'হয়', 'হবে', 'যায়', 'যাবে', 'পাবো', 'পাব', 'জন্য', 'থেকে', 'এবং', 'আর', 'ও', 'না', 'নাকি',
        'এর', 'এই', 'সেই', 'একটা', 'ভালো', 'দরকার', 'চাই', 'লাগে',
        'the', 'a', 'an', 'to', 'of', 'in', 'on', 'for', 'is', 'are', 'i', 'my', 'me', 'how', 'what', 'where', 'when', 'why',
        'which', 'can', 'do', 'does', 'should', 'and', 'or', 'with', 'from', 'it', 'be', 'get',
    ];

    /** The words of a query: punctuation and FULLTEXT operators removed, at least 2 characters each. */
    public static function terms(?string $query): array
    {
        $words = preg_split('/[\s\p{P}\p{S}]+/u', mb_strtolower(trim((string) $query)), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_unique(array_filter($words, fn ($w) => mb_strlen($w) >= 2)));

        return array_slice($words, 0, self::MAX_TERMS);
    }

    /** Every word must appear; best matches first, then newest. */
    public static function posts(string $query): ?LengthAwarePaginator
    {
        $terms = self::terms($query);
        if (! $terms) {
            return null;
        }
        $against = implode(' ', array_map(fn ($t) => '+"'.$t.'"', $terms));

        return self::base($against)
            ->orderByDesc('relevance')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->withQueryString();
    }

    /**
     * Published questions whose titles look like the one being written: any meaningful word may
     * match, then titles sharing the most words win (at least two, or one for one-word titles).
     */
    public static function similar(string $title, int $limit = 5): Collection
    {
        $terms = array_values(array_filter(self::terms($title), fn ($t) => ! in_array($t, self::STOPWORDS, true) && mb_strlen($t) >= 3));
        if (! $terms) {
            return collect();
        }
        $against = implode(' ', array_map(fn ($t) => '"'.$t.'"', $terms));
        $need = min(2, count($terms));

        return self::base($against)->orderByDesc('relevance')->limit(50)->get()
            ->map(function (Post $post) use ($terms) {
                $haystack = mb_strtolower($post->title);
                $post->shared = count(array_filter($terms, fn ($t) => str_contains($haystack, $t)));

                return $post;
            })
            ->filter(fn (Post $post) => $post->shared >= $need)
            ->sortByDesc(fn (Post $post) => [$post->shared, $post->accepted_answer_id ? 1 : 0, $post->answers_count])
            ->take($limit)->values();
    }

    private static function base(string $against): Builder
    {
        return Post::published()
            ->select('posts.*')
            ->selectRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE) AS relevance', [$against])
            ->whereRaw('MATCH(title, body) AGAINST (? IN BOOLEAN MODE)', [$against])
            ->with(['member:id,code,name,deleted_at', 'category:id,slug,name_bn,name_en,emoji', 'area:id,slug,name_bn,name_en']);
    }
}
