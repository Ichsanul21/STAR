<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskGroup extends Model
{
    protected $fillable = ['event_id', 'event_phase_id', 'event_division_id', 'name', 'description', 'weight', 'status', 'progress', 'sort_order'];

    protected $casts = ['weight' => 'decimal:2', 'progress' => 'decimal:2'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function phase()
    {
        return $this->belongsTo(EventPhase::class, 'event_phase_id');
    }

    public function division()
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class)->orderBy('sort_order');
    }

    public function budgetItems()
    {
        return $this->hasMany(BudgetItem::class);
    }
}
