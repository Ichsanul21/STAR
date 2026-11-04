<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskUpdate extends Model
{
    protected $fillable = [
        'event_id', 'event_phase_id', 'task_id', 'task_checklist_id', 'user_id',
        'type', 'from_status', 'to_status', 'note', 'photo_path', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function checklist()
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function phase()
    {
        return $this->belongsTo(EventPhase::class, 'event_phase_id');
    }
}
