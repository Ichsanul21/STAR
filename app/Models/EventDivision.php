<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventDivision extends Model
{
    protected $fillable = ['event_id', 'name', 'code', 'sort_order'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
