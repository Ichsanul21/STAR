<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleItem extends Model
{
    protected $fillable = [
        'event_id', 'date', 'start_time', 'end_time', 'activity', 'description', 'pic',
        'location', 'category', 'target_participants', 'actual_participants', 'status',
        'actual_start', 'actual_end', 'variance_minutes', 'notes', 'sort_order',
    ];

    protected $casts = ['date' => 'date'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
