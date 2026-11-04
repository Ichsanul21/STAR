<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanningItem extends Model
{
    protected $fillable = ['event_id', 'aspect', 'description', 'target', 'pic', 'deadline', 'status', 'notes', 'sort_order'];

    protected $casts = ['deadline' => 'date'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
