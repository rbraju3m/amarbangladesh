<?php

namespace App\Models;

use App\Models\Concerns\Bilingual;
use App\Support\Lang;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Bilingual;

    protected $fillable = [
        'slug', 'name_bn', 'name_en', 'emoji', 'title_bn', 'title_en', 'tagline_bn', 'tagline_en', 'description_bn', 'description_en', 'reason_tail_bn', 'reason_tail_en', 'badges_en',
        'badges', 'profile', 'accent_color', 'map_x', 'map_y', 'illustration', 'og_image', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'badges' => 'array',
            'badges_en' => 'array',
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

    /** The link-preview image in the page language (English ones live in images/og/en/). */
    public function ogImageUrl(): string
    {
        if ($this->og_image) {
            return asset($this->og_image);
        }
        $english = "images/og/en/{$this->slug}.png";

        return asset(Lang::isEnglish() && file_exists(public_path($english)) ? $english : "images/og/{$this->slug}.png");
    }

    /** Badges in the page language. */
    public function localizedBadges(): array
    {
        return Lang::isEnglish() && $this->badges_en ? $this->badges_en : ($this->badges ?? []);
    }
}
