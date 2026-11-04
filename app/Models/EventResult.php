<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventResult extends Model
{
    protected $fillable = [
        'event_id', 'visitors', 'unique_visitors', 'leads', 'test_drives', 'spk',
        'units_sold', 'revenue', 'handover', 'cost_per_lead', 'roi', 'notes',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'cost_per_lead' => 'decimal:2',
        'roi' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
