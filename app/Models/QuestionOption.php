<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'question_id', 'label_bn', 'emoji', 'image', 'reason_bn', 'trait_weights', 'location_bonus', 'sort_order', 'is_active',
])]
class QuestionOption extends Model
{
    protected function casts(): array
    {
        return [
            'trait_weights' => 'array',
            'location_bonus' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
