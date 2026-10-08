<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'spam' => 'স্প্যাম বা বিজ্ঞাপন',
        'abuse' => 'অসম্মানজনক বা ঘৃণামূলক',
        'false' => 'ভুল বা বিভ্রান্তিকর তথ্য',
        'private' => 'কারও ব্যক্তিগত তথ্য',
        'other' => 'অন্য কিছু',
    ];

    protected $fillable = ['member_id', 'reportable_type', 'reportable_id', 'reason'];
}
