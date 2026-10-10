<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A post a member saved to read later (private; listed on /saved). */
class SavedPost extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['member_id', 'post_id'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
