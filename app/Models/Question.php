<?php

namespace App\Models;

use App\Models\Concerns\Bilingual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use Bilingual;

    protected $fillable = ['prompt_bn', 'prompt_en', 'subtitle_bn', 'subtitle_en', 'kind', 'sort_order', 'is_active'];

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
