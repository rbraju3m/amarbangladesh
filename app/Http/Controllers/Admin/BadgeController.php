<?php

namespace App\Http\Controllers\Admin;

use App\Community\Moderation;
use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Professional badges ("Doctor", "Lawyer"…): only admins give them, after checking. Who gave a badge and
 * when is kept on the member, how it was checked in a private note, and every give, change and removal
 * goes into the action log. Contact badges need nothing here: they come from how a member signs in.
 */
class BadgeController extends Controller
{
    public function index(): View
    {
        return view('admin.badges', [
            'members' => Member::whereNotNull('badge_bn')->whereNull('deleted_at')->with('badgeGiver:id,name,email')->latest('badge_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'member' => ['required', 'string', 'max:300'],
            'badge_bn' => ['required', 'string', 'max:40'],
            'badge_en' => ['nullable', 'string', 'max:40'],
            'badge_note' => ['required', 'string', 'max:300'],
        ], [
            'badge_note.required' => 'Write down how you checked it (e.g. "BMDC registration seen on video call").',
        ]);

        $member = $this->find($data['member']);
        $from = $member->badge_bn;
        $badge = [
            'badge_bn' => Str::squish($data['badge_bn']),
            'badge_en' => filled($data['badge_en'] ?? null) ? Str::squish($data['badge_en']) : null,
            'badge_note' => Str::squish($data['badge_note']),
        ];
        $member->forceFill($badge + ['badge_by' => $request->user()->id, 'badge_at' => now()])->save();
        Moderation::log($request->user(), $from ? 'badge_edit' : 'badge_give', $member, [
            'changes' => ['badge' => ['from' => $from, 'to' => $badge['badge_bn'].($badge['badge_en'] ? " / {$badge['badge_en']}" : '')]],
            'note' => $badge['badge_note'],
        ]);

        return redirect()->route('admin.badges.index')->with('status', ($member->name ?: 'Member').' now shows “'.$badge['badge_bn'].'”. Cached pages pick it up within a few minutes.');
    }

    public function destroy(Request $request, Member $member): RedirectResponse
    {
        if ($member->badge_bn) {
            Moderation::log($request->user(), 'badge_remove', $member, ['changes' => ['badge' => ['from' => $member->badge_bn, 'to' => null]]]);
            $member->forceFill(['badge_bn' => null, 'badge_en' => null, 'badge_note' => null, 'badge_by' => null, 'badge_at' => null])->save();
        }

        return redirect()->route('admin.badges.index')->with('status', 'Badge removed.');
    }

    /** A member by code, or by their profile link (…/u/{code}, either language). */
    private function find(string $input): Member
    {
        $code = Str::of(trim($input))->before('?')->before('#')->rtrim('/')->afterLast('/')->toString();
        $member = Member::where('code', $code)->whereNull('deleted_at')->first();
        if (! $member) {
            throw ValidationException::withMessages(['member' => 'No member with that code or profile link.']);
        }
        if (! $member->hasAccount()) {
            throw ValidationException::withMessages(['member' => 'That member has never signed in, so they can\'t hold a badge.']);
        }

        return $member;
    }
}
