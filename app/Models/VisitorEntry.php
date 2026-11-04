<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorEntry extends Model
{
    protected $fillable = [
        'event_id', 'name', 'phone', 'email', 'city', 'province', 'source', 'interest_model',
        'budget_range', 'payment_method', 'test_drive', 'lead_status', 'sales_pic',
        'follow_up_date', 'notes', 'consent', 'client_uuid', 'created_by',
    ];

    protected $casts = [
        'test_drive' => 'boolean',
        'consent' => 'boolean',
        'follow_up_date' => 'date',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
