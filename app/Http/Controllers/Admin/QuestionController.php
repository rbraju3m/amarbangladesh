<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PersonalityTrait;
use App\Models\Question;
use App\Quiz\QuizConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function index(): View
    {
        return view('admin.questions.index', [
            'questions' => Question::withCount('options')->with('options')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        $question = new Question(['kind' => 'emoji', 'is_active' => true]);
        $question->setRelation('options', collect());

        return view('admin.questions.form', $this->formData($question));
    }

    public function store(Request $request): RedirectResponse
    {
        $question = DB::transaction(function () use ($request) {
            $question = Question::create($this->questionData($request) + ['sort_order' => (Question::max('sort_order') ?? 0) + 1]);
            $this->syncOptions($question, $request);

            return $question;
        });
        QuizConfig::forget();

        return redirect()->route('admin.questions.edit', $question)->with('status', 'Question created.');
    }

    public function edit(Question $question): View
    {
        return view('admin.questions.form', $this->formData($question->load('options')));
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        DB::transaction(function () use ($request, $question) {
            $question->update($this->questionData($request));
            $this->syncOptions($question, $request);
        });
        QuizConfig::forget();

        return redirect()->route('admin.questions.edit', $question)->with('status', 'Saved. Check the balance page before going live.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $question->delete();
        QuizConfig::forget();

        return redirect()->route('admin.questions.index')->with('status', 'Question deleted.');
    }

    public function move(Question $question, string $direction): RedirectResponse
    {
        $ordered = Question::orderBy('sort_order')->orderBy('id')->get()->values();
        $i = $ordered->search(fn ($q) => $q->is($question));
        $j = $direction === 'up' ? $i - 1 : $i + 1;

        if (isset($ordered[$j])) {
            [$ordered[$i], $ordered[$j]] = [$ordered[$j], $ordered[$i]];
            DB::transaction(fn () => $ordered->each(fn ($q, $n) => $q->update(['sort_order' => $n + 1])));
            QuizConfig::forget();
        }

        return back();
    }

    private function formData(Question $question): array
    {
        return [
            'question' => $question,
            'traits' => PersonalityTrait::orderBy('sort_order')->get(),
            'locations' => Location::orderBy('sort_order')->get(),
        ];
    }

    private function questionData(Request $request): array
    {
        $traitKeys = PersonalityTrait::pluck('key')->all();
        $slugs = Location::pluck('slug')->all();

        // The form always offers blank rows for new answers; ignore the ones left empty.
        $request->merge(['options' => array_values(array_filter(
            (array) $request->input('options'),
            fn ($o) => ! empty($o['id']) || trim($o['label_bn'] ?? '') !== '',
        ))]);

        $request->validate([
            'prompt_bn' => ['required', 'string', 'max:255'],
            'subtitle_bn' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::in(Question::KINDS)],
            'options' => ['required', 'array', 'min:2', 'max:6'],
            'options.*.id' => ['nullable', 'integer'],
            'options.*.label_bn' => ['required', 'string', 'max:120'],
            'options.*.emoji' => ['nullable', 'string', 'max:16'],
            'options.*.image' => ['nullable', 'string', 'max:255'],
            'options.*.reason_bn' => ['required', 'string', 'max:120'],
            'options.*.weights' => ['array'],
            'options.*.weights.*' => ['nullable', 'integer', 'between:-5,10'],
            'options.*.bonus' => ['array'],
            'options.*.bonus.*' => ['nullable', 'integer', 'between:0,5'],
        ]);

        // Unknown trait / location keys are dropped rather than rejected.
        $request->merge(['options' => collect($request->input('options'))->map(fn ($o) => [
            ...$o,
            'weights' => array_filter(array_map('intval', array_intersect_key($o['weights'] ?? [], array_flip($traitKeys)))),
            'bonus' => array_filter(array_map('intval', array_intersect_key($o['bonus'] ?? [], array_flip($slugs)))),
        ])->all()]);

        return $request->only('prompt_bn', 'subtitle_bn', 'kind') + ['is_active' => $request->boolean('is_active')];
    }

    private function syncOptions(Question $question, Request $request): void
    {
        $keep = [];
        foreach (array_values($request->input('options')) as $n => $o) {
            if (! empty($o['_delete'])) {
                continue;
            }
            $data = [
                'label_bn' => $o['label_bn'],
                'emoji' => $o['emoji'] ?? null,
                'image' => $o['image'] ?? null,
                'reason_bn' => $o['reason_bn'],
                'trait_weights' => $o['weights'],
                'location_bonus' => $o['bonus'],
                'sort_order' => $n + 1,
                'is_active' => ! empty($o['is_active']),
            ];
            $option = ! empty($o['id']) ? $question->options()->whereKey($o['id'])->first() : null;
            $option ? $option->update($data) : $option = $question->options()->create($data);
            $keep[] = $option->id;
        }
        $question->options()->whereNotIn('id', $keep)->delete();
    }
}
