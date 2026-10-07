<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['prompt_bn', 'subtitle_bn', 'kind', 'sort_order', 'is_active'])]
class Question extends Model
{
    public const KINDS = ['emoji', 'image'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
