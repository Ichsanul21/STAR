<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BudgetVersion extends Model
{
    protected $fillable = [
        'event_id', 'stage', 'version', 'status', 'total_planned', 'total_actual',
        'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'total_planned' => 'decimal:2',
        'total_actual' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function items()
    {
        return $this->hasMany(BudgetItem::class)->orderBy('sort_order');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isFinal(): bool
    {
        return $this->stage === 'final' && $this->status === 'approved';
    }
}
