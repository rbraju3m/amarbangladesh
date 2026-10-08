<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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
    protected $fillable = ['code', 'name', 'password', 'blocked_at'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime', 'password' => 'hashed'];
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

    public function displayName(): string
    {
        return $this->name ?: __('একজন বাংলাদেশি');
    }

    /** A stable avatar colour from the code, from a set that reads in light and dark mode. */
    public function avatarColor(): string
    {
        $colors = ['#2f7d4f', '#c2410c', '#0369a1', '#7c3aed', '#be185d', '#0f766e', '#a16207', '#4d7c0f'];

        return $colors[crc32($this->code) % count($colors)];
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
}
