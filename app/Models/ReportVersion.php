<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportVersion extends Model
{
    protected $fillable = [
        'event_id', 'version', 'report_number', 'status', 'pdf_path', 'xlsx_path', 'snapshot', 'narrative', 'section_order',
        'generated_by', 'approved_by', 'published_at', 'acknowledged_by', 'acknowledged_at', 'checksum',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'narrative' => 'array',
        'section_order' => 'array',
        'published_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function generator()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
