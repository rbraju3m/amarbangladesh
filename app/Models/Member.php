<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A community member. They sign in with one or more identities (Google, Facebook, phone, email);
 * each signed-in browser keeps a random token (stored hashed in `member_tokens`) and sends it as a
 * header, so public pages stay cookieless. The public sees only the name and a short code.
 * Admin accounts are separate (`users`).
 */
class Member extends Model
{
    protected $fillable = ['code', 'name', 'password', 'email', 'email_notifications', 'notification_prefs', 'locale', 'district_id', 'blocked_at', 'deleted_at'];

    protected $hidden = ['password', 'email', 'badge_note', 'badge_by', 'district_id'];

    /** The columns `partials/author` needs: eager-load authors as `'member:'.Member::AUTHOR_COLUMNS`. */
    public const AUTHOR_COLUMNS = 'id,code,name,deleted_at,badge_bn,badge_en';

    /** Contact-verified badges, from how a member signs in: Google accounts have a checked email, Facebook doesn't count. */
    public const CONTACT_BADGES = ['phone' => ['phone'], 'email' => ['email', 'google']];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime', 'deleted_at' => 'datetime', 'badge_at' => 'datetime', 'is_demo' => 'boolean', 'password' => 'hashed', 'email_notifications' => 'boolean', 'notification_prefs' => 'array'];
    }

    /** Notice types a member can turn off on the site, and the ones also emailed (Notifier, SendNotificationEmails). */
    public const NOTICE_TYPES = ['answer', 'reply', 'accepted', 'need'];

    public const EMAILED_TYPES = ['answer', 'accepted'];

    /**
     * Whether this member wants notices of `$type` on the `site` (no site notice = no email either) or
     * by `email` (also needs an address and email notifications on: the unsubscribe link turns those off).
     */
    public function wantsNotice(string $channel, string $type): bool
    {
        $site = $this->notification_prefs['site'][$type] ?? true;
        if ($channel === 'site') {
            return $site;
        }

        return $site && in_array($type, self::EMAILED_TYPES, true) && $this->email && $this->email_notifications
            && ($this->notification_prefs['email'][$type] ?? true);
    }

    /** Every type × channel as booleans, for the settings switches. */
    public function noticeChoices(): array
    {
        return [
            'site' => collect(self::NOTICE_TYPES)->mapWithKeys(fn ($t) => [$t => $this->notification_prefs['site'][$t] ?? true])->all(),
            'email' => collect(self::EMAILED_TYPES)->mapWithKeys(fn ($t) => [$t => $this->notification_prefs['email'][$t] ?? true])->all(),
        ];
    }

    /** Notification emails go out in the language the member last used the site in. */
    public function rememberLocale(): static
    {
        if ($this->locale !== app()->getLocale()) {
            $this->update(['locale' => app()->getLocale() === 'en' ? 'en' : 'bn']);
        }

        return $this;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** Signs a browser in: returns the plain token; only its hash is kept. */
    public function issueToken(): string
    {
        $token = Str::random(40);
        $this->tokens()->create(['token_hash' => hash('sha256', $token), 'last_used_at' => now()]);

        return $token;
    }

    public static function fromToken(?string $token): ?self
    {
        if (! $token || strlen($token) !== 40) {
            return null;
        }
        $row = MemberToken::with('member')->where('token_hash', hash('sha256', $token))->first();
        if ($row && (! $row->last_used_at || $row->last_used_at->lt(now()->subHour()))) {
            $row->update(['last_used_at' => now()]); // at most one write per hour per device
        }

        return $row?->member;
    }

    public static function revokeToken(string $token): void
    {
        MemberToken::where('token_hash', hash('sha256', $token))->delete();
    }

    /** Signed in with a verified identity (not just a device-only member from before sign-in existed). */
    public function hasAccount(): bool
    {
        return $this->identities()->exists();
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** Deleted their account; only kept as the author of posts and answers they left up. */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function displayName(): string
    {
        if ($this->isDeleted()) {
            return __('মুছে ফেলা অ্যাকাউন্ট');
        }

        return $this->name ?: __('একজন বাংলাদেশি');
    }

    /**
     * The professional badge an admin gave (in the page's language; English falls back to Bangla), or null.
     * Never shown on anonymous items: those have no public author at all.
     */
    public function badge(): ?string
    {
        if ($this->isDeleted() || ! $this->badge_bn) {
            return null;
        }

        return app()->getLocale() === 'en' && $this->badge_en ? $this->badge_en : $this->badge_bn;
    }

    /**
     * Which contacts this member proved they own when signing in (`phone`, `email`): it says the number or
     * address was checked, not who the person is.
     */
    public function contactBadges(): array
    {
        if ($this->isDeleted()) {
            return [];
        }
        $providers = $this->identities()->whereNotNull('verified_at')->distinct()->pluck('provider')->all();

        return array_keys(array_filter(self::CONTACT_BADGES, fn ($from) => array_intersect($from, $providers)));
    }

    /** A stable avatar colour from the code, from a set that reads in light and dark mode. */
    public function avatarColor(): string
    {
        $colors = ['#2f7d4f', '#c2410c', '#0369a1', '#7c3aed', '#be185d', '#0f766e', '#a16207', '#4d7c0f'];

        return $colors[crc32($this->code) % count($colors)];
    }

    public function badgeGiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'badge_by');
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(MemberToken::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(MemberIdentity::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(MemberNotification::class);
    }

    public function savedPosts(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    /** Their home district (optional, private: only they see it, for "my area"). */
    public function district(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'district_id');
    }
}
