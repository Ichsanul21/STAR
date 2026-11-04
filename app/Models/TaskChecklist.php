<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskChecklist extends Model
{
    protected $fillable = ['task_id', 'label', 'is_done', 'sort_order'];

    protected $casts = ['is_done' => 'boolean'];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function updates()
    {
        return $this->hasMany(TaskUpdate::class);
    }
}
