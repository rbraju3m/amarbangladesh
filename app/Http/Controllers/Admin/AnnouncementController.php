<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Site-wide announcements: add, edit, switch on/off. Never deleted (an old one is turned off or simply
 * ends); every change goes into the action log. Times are entered and shown in Dhaka time.
 */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::orderByDesc('id')->get(),
            'current' => Announcement::current(),
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', ['announcement' => $announcement]);
    }

    public function store(Request $request): RedirectResponse
    {
        $announcement = Announcement::create($this->validated($request) + ['is_active' => $request->boolean('is_active', true)]);
        $this->log($request, 'announce_add', $announcement, []);

        return redirect()->route('admin.announcements.index')->with('status', 'Announcement added.'.$this->when($announcement));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->fill($this->validated($request, $announcement) + ['is_active' => $request->boolean('is_active')]);
        $changes = collect($announcement->getDirty())->except('updated_at')
            ->map(fn ($new, $field) => ['from' => $this->plain($announcement->getOriginal($field)), 'to' => $this->plain($announcement->getAttribute($field))])
            ->all();
        if (! $changes) {
            return redirect()->route('admin.announcements.index')->with('status', 'Nothing changed.');
        }
        $announcement->save();

        $action = array_keys($changes) === ['is_active'] ? ($announcement->is_active ? 'announce_on' : 'announce_off') : 'announce_edit';
        $this->log($request, $action, $announcement, ['changes' => $changes]);

        return redirect()->route('admin.announcements.index')->with('status', match ($action) {
            'announce_off' => 'Announcement turned off.',
            default => 'Announcement saved.'.$this->when($announcement),
        });
    }

    /**
     * The on/off switch in the list sends only `is_active`; the full form sends everything. A quick toggle
     * keeps the rest as it is.
     */
    private function validated(Request $request, ?Announcement $existing = null): array
    {
        if ($existing && $request->has('toggle')) {
            return $existing->only(['text_bn', 'text_en', 'link', 'level', 'starts_at', 'ends_at']);
        }

        $data = $request->validate([
            'text_bn' => ['required', 'string', 'max:240'],
            'text_en' => ['nullable', 'string', 'max:240'],
            'link' => ['nullable', 'string', 'max:500', 'regex:#^(/(?!/)|https?://)\S+$#'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ], [
            'link.regex' => 'The link must be a page on this site (starting with /) or a full https:// address.',
        ]);

        foreach (['starts_at', 'ends_at'] as $field) {
            // datetime-local has no zone: it is Dhaka time, like everything else here.
            $data[$field] = filled($data[$field] ?? null) ? Carbon::parse($data[$field], config('app.timezone')) : null;
        }
        if ($data['starts_at'] && $data['ends_at'] && $data['ends_at']->lte($data['starts_at'])) {
            throw ValidationException::withMessages(['ends_at' => 'The end must be after the start.']);
        }
        $data['text_bn'] = Str::squish($data['text_bn']);
        $data['text_en'] = filled($data['text_en'] ?? null) ? Str::squish($data['text_en']) : null;

        return $data;
    }

    private function plain(mixed $value): mixed
    {
        return $value instanceof \DateTimeInterface ? Carbon::instance($value)->format('j M Y, H:i') : $value;
    }

    private function when(Announcement $announcement): string
    {
        return match ($announcement->state()) {
            'live' => ' It shows now (cached pages within about 10 minutes).',
            'scheduled' => ' It starts '.$announcement->starts_at->format('j M, H:i').'.',
            'ended' => ' Its end time has passed, so it isn\'t shown.',
            default => ' It is off.',
        };
    }

    /** Clears the cached live list and writes the log line. */
    private function log(Request $request, string $action, Announcement $announcement, array $meta): void
    {
        Announcement::forget();
        AdminAction::create([
            'user_id' => $request->user()->id, 'action' => $action, 'target_type' => 'announcement', 'target_id' => $announcement->id,
            'meta' => ['title' => Str::limit($announcement->text_bn, 120)] + $meta,
        ]);
    }
}
