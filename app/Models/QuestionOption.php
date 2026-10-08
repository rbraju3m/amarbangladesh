<?php

namespace App\Models;

use App\Models\Concerns\Bilingual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionOption extends Model
{
    use Bilingual;

    protected $fillable = [
        'question_id', 'label_bn', 'label_en', 'emoji', 'image', 'reason_bn', 'reason_en', 'trait_weights', 'location_bonus', 'sort_order', 'is_active',
    ];

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
