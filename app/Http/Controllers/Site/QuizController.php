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
    public function index(): View
    {
        return view('site.app', ['boot' => $this->boot()]);
    }

    /** The share page: a teaser for visitors, the full result for its owner (decided client-side). */
    public function show(QuizResult $result): View
    {
        return view('site.app', [
            'boot' => $this->boot() + ['shared' => ResultPresenter::present($result)],
            'result' => $result->loadMissing('location'),
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
                    'x' => $l->map_x,
                    'y' => $l->map_y,
                    'illustration' => $l->illustrationUrl(),
                ])->all(),
        ]);
    }
}
