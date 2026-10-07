<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A personality dimension (প্রকৃতি, আড্ডা…). Named PersonalityTrait because `Trait` is reserved in PHP.
 */
class PersonalityTrait extends Model
{
    protected $table = 'traits';

    protected $fillable = ['key', 'label_bn', 'emoji', 'sort_order'];
}
