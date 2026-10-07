<?php

namespace App\Models;

use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $fillable = [
        'slug', 'name_bn', 'name_en', 'emoji', 'title_bn', 'tagline_bn', 'description_bn', 'reason_tail_bn',
        'badges', 'profile', 'accent_color', 'map_x', 'map_y', 'illustration', 'og_image', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'badges' => 'array',
            'profile' => 'array',
            'map_x' => 'float',
            'map_y' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function results(): HasMany
    {
        return $this->hasMany(QuizResult::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function illustrationUrl(): string
    {
        return PublicUrl::path($this->illustration ?: "images/locations/{$this->slug}.svg");
    }

    public function ogImageUrl(): string
    {
        return asset($this->og_image ?: "images/og/{$this->slug}.png");
    }
}
