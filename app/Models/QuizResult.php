<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuizResult extends Model
{
    protected $fillable = [
        'code', 'visitor_id', 'location_id', 'match_pct', 'second_location_id', 'second_match_pct', 'trait_vector',
        'trait_scores', 'answer_ids', 'reason_bn', 'reason_en', 'display_name', 'owner_token_hash', 'referrer_result_id', 'friend_match_pct', 'scoring_version',
    ];

    protected $hidden = ['owner_token_hash', 'visitor_id'];

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'trait_vector' => 'array',
            'trait_scores' => 'array',
            'answer_ids' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public static function newCode(): string
    {
        do {
            $code = Str::random(8);
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function secondLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'second_location_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(QuizResult::class, 'referrer_result_id');
    }
}
