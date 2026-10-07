<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A personality dimension (প্রকৃতি, আড্ডা…). Named PersonalityTrait because `Trait` is reserved in PHP.
 */
#[Table('traits')]
#[Fillable(['key', 'label_bn', 'emoji', 'sort_order'])]
class PersonalityTrait extends Model {}
