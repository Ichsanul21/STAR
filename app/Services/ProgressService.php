<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhase;
use App\Models\Task;
use App\Models\TaskGroup;

class ProgressService
{
    public function recalcTask(Task $task): void
    {
        $task->progress = $task->computedProgress();
        $task->completed_at = $task->status === 'done' ? ($task->completed_at ?? now()) : null;
        $task->save();

        $this->recalcGroup($task->group);
    }

    public function recalcGroup(?TaskGroup $group): void
    {
        if (! $group) {
            return;
        }

        $tasks = $group->tasks()->where('status', '!=', 'cancelled')->get();
        $weight = (float) $tasks->sum('weight');

        $group->progress = $weight > 0
            ? round((float) $tasks->sum(fn (Task $t) => (float) $t->weight * (float) $t->progress) / $weight, 2)
            : 0;
        $group->status = $this->deriveStatus($tasks);
        $group->save();

        $this->recalcPhase($group->phase);
    }

    public function recalcPhase(?EventPhase $phase): void
    {
        if (! $phase) {
            return;
        }

        $groups = $phase->taskGroups()->where('status', '!=', 'cancelled')->get();
        $weight = (float) $groups->sum('weight');

        $phase->progress = $weight > 0
            ? round((float) $groups->sum(fn (TaskGroup $g) => (float) $g->weight * (float) $g->progress) / $weight, 2)
            : 0;
        $phase->status = $this->deriveStatus($groups);
        $phase->save();

        $this->recalcEvent($phase->event);
    }

    public function recalcEvent(?Event $event): void
    {
        if (! $event) {
            return;
        }

        foreach ($event->phases()->with('taskGroups.tasks')->get() as $phase) {
            $groups = $phase->taskGroups->where('status', '!=', 'cancelled');
            $weight = (float) $groups->sum('weight');
            $phase->progress = $weight > 0
                ? round((float) $groups->sum(fn (TaskGroup $g) => (float) $g->weight * (float) $g->progress) / $weight, 2)
                : 0;
            $phase->status = $this->deriveStatus($groups);
            $phase->save();
        }
    }

    private function deriveStatus($items): string
    {
        if ($items->isEmpty()) {
            return 'todo';
        }

        if ($items->contains(fn ($item) => $item->status === 'blocked')) {
            return 'blocked';
        }

        if ($items->every(fn ($item) => $item->status === 'done' || $item->status === 'cancelled')) {
            return 'done';
        }

        if ($items->contains(fn ($item) => (float) $item->progress > 0 || in_array($item->status, ['in_progress', 'review'], true))) {
            return 'in_progress';
        }

        return 'todo';
    }
}
