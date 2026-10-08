<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A place in Bangladesh's administrative tree: division → district (→ upazila later). */
class Area extends Model
{
    public $timestamps = false;

    protected $fillable = ['parent_id', 'type', 'slug', 'name_bn', 'name_en', 'sort_order'];

    /** The name in the page language. */
    public function name(): string
    {
        return Lang::isEnglish() ? $this->name_en : $this->name_bn;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }
}
