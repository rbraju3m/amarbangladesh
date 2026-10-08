<?php

namespace App\Models;

use App\Models\Concerns\Bilingual;
use Illuminate\Database\Eloquent\Model;

/**
 * A personality dimension (প্রকৃতি, আড্ডা…). Named PersonalityTrait because `Trait` is reserved in PHP.
 */
class PersonalityTrait extends Model
{
    use Bilingual;

    protected $table = 'traits';

    protected $fillable = ['key', 'label_bn', 'label_en', 'emoji', 'sort_order'];
}
