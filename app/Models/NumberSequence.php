<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberSequence extends Model
{
    protected $fillable = ['type', 'scope', 'last_number'];

    protected $casts = ['last_number' => 'integer'];
}
