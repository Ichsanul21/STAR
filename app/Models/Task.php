<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = [
        'event_id', 'task_group_id', 'name', 'description', 'weight', 'status', 'progress',
        'priority', 'assigned_to', 'start_at', 'due_at', 'completed_at', 'sort_order', 'graph_x', 'graph_y',
        'revision_of_id', 'is_revision', 'revision_reason',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'progress' => 'decimal:2',
        'is_revision' => 'boolean',
        'start_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function group()
    {
        return $this->belongsTo(TaskGroup::class, 'task_group_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function checklists()
    {
        return $this->hasMany(TaskChecklist::class)->orderBy('sort_order');
    }

    public function dependencies()
    {
        return $this->hasMany(TaskDependency::class);
    }

    public function dependents()
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
    }

    public function timeEntries()
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function updates()
    {
        return $this->hasMany(TaskUpdate::class)->latest();
    }

    public function revisionOf()
    {
        return $this->belongsTo(Task::class, 'revision_of_id');
    }

    public function revisions()
    {
        return $this->hasMany(Task::class, 'revision_of_id');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function isBlocked(): bool
    {
        return $this->dependencies()
            ->with('dependsOn')
            ->get()
            ->contains(fn (TaskDependency $d) => $d->dependsOn && $d->dependsOn->status !== 'done');
    }

    public function computedProgress(): float
    {
        if ($this->checklists()->exists()) {
            $total = $this->checklists()->count();
            $done = $this->checklists()->where('is_done', true)->count();

            return $total > 0 ? round($done / $total * 100, 2) : 0;
        }

        return match ($this->status) {
            'done' => 100,
            'review' => 90,
            'in_progress' => 50,
            'blocked' => 0,
            default => 0,
        };
    }

    public function deadlineDays(): ?int
    {
        if (! $this->due_at) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_at->copy()->startOfDay(), false);
    }

    public function deadlineLabel(): ?string
    {
        $days = $this->deadlineDays();

        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return 'Terlambat '.abs($days).' hari';
        }

        if ($days === 0) {
            return 'Hari ini';
        }

        if ($days === 1) {
            return 'Besok';
        }

        return $days.' hari lagi';
    }

    public function deadlineTone(): string
    {
        $days = $this->deadlineDays();

        if ($days === null || $days > 3) {
            return 'muted';
        }

        return $days < 0 ? 'danger' : 'warning';
    }
}
