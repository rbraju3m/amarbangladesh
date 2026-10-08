<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A community identity without a login: the browser keeps a random token (stored here hashed),
 * the public sees only the name and a short code. Admin accounts are separate (`users`); a later
 * phone/email sign-in would attach to a member rather than replace it.
 */
class Member extends Model
{
    protected $fillable = ['code', 'name', 'token_hash', 'blocked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['blocked_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /** @return array{0: Member, 1: string} the member and its plain token (shown once). */
    public static function register(?string $name): array
    {
        $token = Str::random(40);
        do {
            $code = Str::lower(Str::random(8));
        } while (static::where('code', $code)->exists());

        return [static::create(['code' => $code, 'name' => $name, 'token_hash' => hash('sha256', $token)]), $token];
    }

    public static function fromToken(?string $token): ?self
    {
        return $token && strlen($token) === 40 ? static::where('token_hash', hash('sha256', $token))->first() : null;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function displayName(): string
    {
        return $this->name ?: 'একজন বাংলাদেশি';
    }

    /** A stable avatar colour from the code, from a set that reads in light and dark mode. */
    public function avatarColor(): string
    {
        $colors = ['#2f7d4f', '#c2410c', '#0369a1', '#7c3aed', '#be185d', '#0f766e', '#a16207', '#4d7c0f'];

        return $colors[crc32($this->code) % count($colors)];
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
