<?php

namespace App\Http\Controllers\Admin;

use App\Community\Taxonomy;
use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Helpline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The helpline directory: add, edit, mark checked, switch on/off. Never deleted (a wrong or dead number
 * is turned off); every change goes into the action log. A number can only be on once it has a
 * "last checked" date, so nothing unconfirmed reaches the public page.
 */
class HelplineController extends Controller
{
    public function index(): View
    {
        return view('admin.helplines.index', [
            'helplines' => Helpline::with('area')->get()
                ->sortBy(fn (Helpline $h) => [array_search($h->kind, array_keys(Helpline::KINDS), true), $h->sort_order, $h->id]),
        ]);
    }

    public function edit(Helpline $helpline): View
    {
        return view('admin.helplines.form', ['helpline' => $helpline]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request) + ['is_active' => $request->boolean('is_active')];
        $data['sort_order'] ??= (int) Helpline::where('kind', $data['kind'])->max('sort_order') + 1;
        $this->mustBeChecked($data);

        $helpline = Helpline::create($data);
        $this->log($request, 'helpline_add', $helpline, []);

        return redirect()->route('admin.helplines.index')->with('status', "Helpline {$helpline->number} added".($helpline->is_active ? ' and shown.' : ' (off).'));
    }

    public function update(Request $request, Helpline $helpline): RedirectResponse
    {
        // The list's quick buttons send only `checked` or `toggle`; the full form sends everything.
        $data = match (true) {
            $request->has('checked') => ['last_checked_at' => today()],
            $request->has('toggle') => ['is_active' => $request->boolean('is_active')],
            default => $this->validated($request) + ['is_active' => $request->boolean('is_active')],
        };
        $data['sort_order'] = array_key_exists('sort_order', $data) ? ($data['sort_order'] ?? $helpline->sort_order) : $helpline->sort_order;
        $this->mustBeChecked($data + $helpline->only(['last_checked_at', 'is_active']));

        $helpline->fill($data);
        $changes = collect($helpline->getDirty())->except('updated_at')
            ->map(fn ($new, $field) => ['from' => $this->plain($helpline->getOriginal($field)), 'to' => $this->plain($helpline->getAttribute($field))])
            ->all();
        if (! $changes) {
            return redirect()->route('admin.helplines.index')->with('status', 'Nothing changed.');
        }
        $helpline->save();

        $action = match (array_keys($changes)) {
            ['is_active'] => $helpline->is_active ? 'helpline_on' : 'helpline_off',
            ['last_checked_at'] => 'helpline_check',
            default => 'helpline_edit',
        };
        $this->log($request, $action, $helpline, ['changes' => $changes]);

        return redirect()->route('admin.helplines.index')->with('status', "Helpline {$helpline->number} ".match ($action) {
            'helpline_on' => 'turned on: it shows on the public page.',
            'helpline_off' => 'turned off.',
            'helpline_check' => 'marked as checked today.',
            default => 'saved.',
        });
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_bn' => ['required', 'string', 'max:120'],
            'name_en' => ['nullable', 'string', 'max:120'],
            'number' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 \-]*$/'],
            'kind' => ['required', Rule::in(array_keys(Helpline::KINDS))],
            'area' => ['nullable', 'string', Rule::in(array_keys(Taxonomy::areas()))],
            'note_bn' => ['nullable', 'string', 'max:200'],
            'note_en' => ['nullable', 'string', 'max:200'],
            'last_checked_at' => ['nullable', 'date', 'before_or_equal:today'],
            'source' => ['nullable', 'string', 'max:300'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ], [
            'number.regex' => 'The number may hold only digits, spaces, dashes and a leading +.',
            'last_checked_at.before_or_equal' => 'The check date can\'t be in the future.',
        ]);

        $data['area_id'] = filled($data['area'] ?? null) ? Taxonomy::areas()[$data['area']]['id'] : null;
        unset($data['area']);
        foreach (['name_bn', 'name_en', 'note_bn', 'note_en', 'source'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? Str::squish($data[$field]) : null;
        }
        $data['number'] = Str::squish($data['number']);
        $data['last_checked_at'] = filled($data['last_checked_at'] ?? null) ? Carbon::parse($data['last_checked_at'])->startOfDay() : null;

        return $data;
    }

    /** An unconfirmed number never goes public. */
    private function mustBeChecked(array $data): void
    {
        if (($data['is_active'] ?? false) && empty($data['last_checked_at'])) {
            throw ValidationException::withMessages(['is_active' => 'Check the number first (call it or confirm it on an official site), then set the “last checked” date or press “Checked today”, and turn it on.']);
        }
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? Carbon::instance($value)->format('j M Y') : $value;
    }

    /** Clears the cached public list and writes the log line. */
    private function log(Request $request, string $action, Helpline $helpline, array $meta): void
    {
        Helpline::forget();
        AdminAction::create([
            'user_id' => $request->user()->id, 'action' => $action, 'target_type' => 'helpline', 'target_id' => $helpline->id,
            'meta' => ['title' => $helpline->number.' · '.Str::limit($helpline->name_bn, 100)] + $meta,
        ]);
    }
}
