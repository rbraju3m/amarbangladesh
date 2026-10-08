<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Question;
use App\Models\QuizResult;
use App\Quiz\ResultPresenter;
use App\Support\PublicUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class QuizController extends Controller
{
    /** Below this, a play count is weak social proof, so the landing page doesn't show it. */
    public const SHOW_PLAYS_FROM = 100;

    public function index(): View
    {
        return view('site.app', ['boot' => $this->boot(), 'totalPlays' => self::totalPlays()]);
    }

    /** Social proof on the landing page; a few minutes stale is fine, so it costs one query per 10 minutes. */
    public static function totalPlays(): ?int
    {
        $plays = Cache::remember('quiz.total_plays', 600, fn () => QuizResult::count());

        return $plays >= self::SHOW_PLAYS_FROM ? $plays : null;
    }

    /** The share page: a teaser for visitors, the full result for its owner (decided client-side). */
    public function show(QuizResult $result): View
    {
        return view('site.app', [
            'boot' => $this->boot() + ['shared' => ResultPresenter::present($result)],
            'result' => $result->loadMissing('location'),
            // Social proof on the teaser: friends who already played from this link.
            'friendPlays' => QuizResult::where('referrer_result_id', $result->id)->count(),
        ]);
    }

    /** Everything the quiz needs, embedded in the page so there is no extra request before the first question. */
    private function boot(): array
    {
        return Cache::rememberForever('quiz.boot', fn () => [
            'questions' => Question::where('is_active', true)->orderBy('sort_order')->orderBy('id')
                ->with(['options' => fn ($q) => $q->where('is_active', true)])
                ->get()
                ->map(fn (Question $q) => [
                    'id' => $q->id,
                    'prompt' => $q->prompt_bn,
                    'subtitle' => $q->subtitle_bn,
                    'kind' => $q->kind,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id,
                        'label' => $o->label_bn,
                        'emoji' => $o->emoji,
                        'image' => $o->image ? PublicUrl::path($o->image) : null,
                    ])->all(),
                ])->filter(fn ($q) => count($q['options']) > 0)->values()->all(),
            'locations' => Location::where('is_active', true)->orderBy('sort_order')->get()
                ->map(fn (Location $l) => ResultPresenter::locationBrief($l) + [
                    'title_bn' => $l->title_bn,
                    'tagline_bn' => $l->tagline_bn,
                    'description_bn' => $l->description_bn,
                    'x' => $l->map_x,
                    'y' => $l->map_y,
                    'illustration' => $l->illustrationUrl(),
                ])->all(),
        ]);
    }
}
