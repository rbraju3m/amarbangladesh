<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HelpfulMark extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['member_id', 'markable_type', 'markable_id'];
}
