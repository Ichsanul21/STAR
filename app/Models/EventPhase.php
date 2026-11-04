<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPhase extends Model
{
    protected $fillable = ['event_id', 'name', 'code', 'sort_order', 'weight', 'start_date', 'end_date', 'status', 'progress'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'weight' => 'decimal:2',
        'progress' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function taskGroups()
    {
        return $this->hasMany(TaskGroup::class)->orderBy('sort_order');
    }

    public function media()
    {
        return $this->hasMany(MediaItem::class);
    }
}
