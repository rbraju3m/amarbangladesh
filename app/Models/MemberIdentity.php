<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One way a member signs in: a Google or Facebook account, a phone number or an email address. */
class MemberIdentity extends Model
{
    public const PROVIDERS = ['google', 'facebook', 'phone', 'email'];

    protected $fillable = ['member_id', 'provider', 'identifier', 'verified_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
