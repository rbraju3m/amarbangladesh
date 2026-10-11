<?php

namespace App\Models;

use App\Community\Taxonomy;
use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A place in Bangladesh's administrative tree: division → district → upazila (or a city's metropolitan thana). */
class Area extends Model
{
    public $timestamps = false;

    protected $fillable = ['parent_id', 'type', 'slug', 'name_bn', 'name_en', 'sort_order'];

    /** The name in the page language. */
    public function name(): string
    {
        return Lang::isEnglish() ? $this->name_en : $this->name_bn;
    }

    /**
     * "সাভার, ঢাকা": an upazila or thana with its district (the bare name for districts and divisions).
     * Read from the cached tree, so it works when only id/slug/names were loaded.
     */
    public function label(): string
    {
        $row = Taxonomy::areas()[$this->slug] ?? null;

        return $row && Taxonomy::LEVELS[$row['type']] > 1 ? __(':place, :district', ['place' => $this->name(), 'district' => $row['district']]) : $this->name();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }
}
