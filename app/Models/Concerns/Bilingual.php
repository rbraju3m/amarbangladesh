<?php

namespace App\Models\Concerns;

use App\Support\Lang;

/** Content stored as `{field}_bn` / `{field}_en`: text('title') gives the page language, falling back to Bangla. */
trait Bilingual
{
    public function text(string $field): ?string
    {
        $english = $this->getAttribute($field.'_en');

        return Lang::isEnglish() && filled($english) ? $english : $this->getAttribute($field.'_bn');
    }
}
