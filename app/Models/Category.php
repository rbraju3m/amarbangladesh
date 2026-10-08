<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['slug', 'name_bn', 'name_en', 'emoji', 'sort_order', 'is_active'];

    /** The name in the page language. */
    public function name(): string
    {
        return Lang::isEnglish() && $this->name_en ? $this->name_en : $this->name_bn;
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
