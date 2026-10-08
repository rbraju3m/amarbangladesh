<?php

namespace App\Quiz;

use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Models\Question;
use Illuminate\Support\Facades\Cache;

/**
 * Immutable snapshot of everything scoring needs: trait order, location profiles and
 * option weights, all as plain arrays aligned to the trait order. Cached forever and
 * flushed whenever the admin saves quiz content.
 */
final class QuizConfig
{
    private const CACHE_KEY = 'quiz.config.v1';

    /**
     * @param  list<string>  $traitKeys
     * @param  array<string, array{id:int, profile:list<float>}>  $locations  keyed by slug, in sort order
     * @param  array<int, array<int, array{weights:list<float>, bonus:array<string,float>, reason:string, reason_en:?string}>>  $questions  question id => option id => option
     */
    public function __construct(
        public readonly array $traitKeys,
        public readonly array $locations,
        public readonly array $questions,
    ) {}

    public static function load(): self
    {
        // Cached as plain arrays: the cache store refuses to unserialize objects (cache.serializable_classes).
        $data = Cache::rememberForever(self::CACHE_KEY, fn () => self::fromDatabase()->toArray());

        return new self($data['traitKeys'], $data['locations'], $data['questions']);
    }

    /** Flush every cache derived from quiz content (scoring config, embedded quiz data, trait labels). */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        foreach (['bn', 'en'] as $locale) {
            Cache::forget("quiz.boot.{$locale}");
            Cache::forget("quiz.trait-meta.{$locale}");
        }
    }

    public static function fromDatabase(): self
    {
        $traitKeys = PersonalityTrait::orderBy('sort_order')->orderBy('id')->pluck('key')->all();

        $align = fn (?array $map) => array_map(fn ($key) => (float) ($map[$key] ?? 0), $traitKeys);

        $locations = [];
        foreach (Location::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get() as $location) {
            $locations[$location->slug] = ['id' => $location->id, 'profile' => $align($location->profile)];
        }

        $questions = [];
        $active = Question::where('is_active', true)->orderBy('sort_order')->orderBy('id')
            ->with(['options' => fn ($q) => $q->where('is_active', true)])->get();
        foreach ($active as $question) {
            foreach ($question->options as $option) {
                $questions[$question->id][$option->id] = [
                    'weights' => $align($option->trait_weights),
                    'bonus' => array_map('floatval', array_intersect_key($option->location_bonus ?? [], $locations)),
                    'reason' => $option->reason_bn,
                    'reason_en' => $option->reason_en,
                ];
            }
        }

        return new self($traitKeys, $locations, array_filter($questions));
    }

    public function toArray(): array
    {
        return ['traitKeys' => $this->traitKeys, 'locations' => $this->locations, 'questions' => $this->questions];
    }

    /** Short hash stored with each result so later scoring edits are traceable. */
    public function version(): string
    {
        return substr(md5(json_encode([$this->traitKeys, $this->locations, $this->questions])), 0, 10);
    }
}
