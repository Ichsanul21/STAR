<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Http\Controllers\Concerns\ChecksVersion;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskDependency;
use App\Models\TaskGroup;
use App\Services\ProgressService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use AuthorizesEvents, ChecksVersion;

    public function storeGroup(Request $request, Event $event, ProgressService $progress)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'event_phase_id' => ['required', 'exists:event_phases,id'],
            'event_division_id' => ['nullable', 'exists:event_divisions,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric', 'min:0.1'],
        ]);

        $group = TaskGroup::create([
            'event_id' => $event->id,
            'event_phase_id' => $data['event_phase_id'],
            'event_division_id' => $data['event_division_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'] ?? 1,
            'sort_order' => (int) $event->taskGroups()->where('event_phase_id', $data['event_phase_id'])->max('sort_order') + 1,
        ]);

        $progress->recalcGroup($group);

        return back()->with('success', 'Task group ditambahkan.');
    }

    public function store(Request $request, Event $event, ProgressService $progress)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'task_group_id' => ['required', 'exists:task_groups,id'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'weight' => ['nullable', 'numeric', 'min:0.1'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $task = Task::create([
            'event_id' => $event->id,
            'task_group_id' => $data['task_group_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'] ?? 1,
            'priority' => $data['priority'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'sort_order' => (int) Task::where('task_group_id', $data['task_group_id'])->max('sort_order') + 1,
        ]);

        $progress->recalcTask($task);

        return back()->with('success', 'Task ditambahkan.');
    }

    public function update(Request $request, Task $task, ProgressService $progress)
    {
        $this->authorizeManage($task->event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'expected_updated_at' => ['nullable', 'date'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($this->hasConflict($task, $data['expected_updated_at'] ?? null) || $this->hasVersionConflict($task, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Task sudah diubah orang lain. Muat ulang halaman sebelum menyimpan.');
        }

        if (! empty($data['start_at']) && ! empty($data['due_at']) && strtotime($data['due_at']) < strtotime($data['start_at'])) {
            return back()->withErrors(['due_at' => 'Due date harus setelah start date.'])->withInput();
        }

        unset($data['expected_updated_at'], $data['lock_version']);
        $task->update($data);
        $this->bumpVersion($task);
        $task->save();
        $progress->recalcTask($task);

        return back()->with('success', 'Task diperbarui.');
    }

    public function updateStatus(Request $request, Task $task, ProgressService $progress)
    {
        $this->authorizeWork($task->event);

        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,blocked,review,done,cancelled'],
            'expected_updated_at' => ['nullable', 'date'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($this->hasConflict($task, $data['expected_updated_at'] ?? null) || $this->hasVersionConflict($task, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Status task sudah diubah orang lain. Muat ulang halaman.');
        }

        $task->status = $data['status'];
        $this->bumpVersion($task);
        $task->save();
        $progress->recalcTask($task);

        return back()->with('success', 'Status task diperbarui.');
    }

    public function storeChecklist(Request $request, Task $task, ProgressService $progress)
    {
        $this->authorizeWork($task->event);

        $data = $request->validate(['label' => ['required', 'string', 'max:160']]);

        TaskChecklist::create([
            'task_id' => $task->id,
            'label' => $data['label'],
            'sort_order' => (int) $task->checklists()->max('sort_order') + 1,
        ]);

        $progress->recalcTask($task);

        return back()->with('success', 'Checklist ditambahkan.');
    }

    public function toggleChecklist(Request $request, TaskChecklist $checklist, ProgressService $progress)
    {
        $checklist->load('task.event');
        $this->authorizeWork($checklist->task->event);

        $data = $request->validate([
            'expected_updated_at' => ['nullable', 'date'],
            'lock_version' => ['nullable', 'integer'],
        ]);
        if ($this->hasConflict($checklist, $data['expected_updated_at'] ?? null) || $this->hasVersionConflict($checklist, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Checklist sudah diubah orang lain. Muat ulang halaman.');
        }

        $checklist->is_done = ! $checklist->is_done;
        $this->bumpVersion($checklist);
        $checklist->save();
        $progress->recalcTask($checklist->task);

        return back()->with('success', 'Checklist diperbarui.');
    }

    public function storeDependency(Request $request, Task $task, ProgressService $progress)
    {
        $this->authorizeManage($task->event);

        $data = $request->validate([
            'depends_on_task_id' => ['required', 'exists:tasks,id'],
            'type' => ['required', 'in:finish_to_start,start_to_start,finish_to_finish,start_to_finish'],
            'lag_minutes' => ['nullable', 'integer'],
        ]);

        $dependsOn = Task::findOrFail($data['depends_on_task_id']);

        if ($dependsOn->event_id !== $task->event_id) {
            return back()->withErrors(['depends_on_task_id' => 'Task harus dalam event yang sama.']);
        }

        if ($dependsOn->id === $task->id) {
            return back()->withErrors(['depends_on_task_id' => 'Task tidak bisa bergantung pada dirinya sendiri.']);
        }

        if ($this->wouldCycle($task, $dependsOn)) {
            return back()->withErrors(['depends_on_task_id' => 'Dependency membentuk siklus.']);
        }

        TaskDependency::firstOrCreate([
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
        ], [
            'type' => $data['type'],
            'lag_minutes' => $data['lag_minutes'] ?? 0,
        ]);

        $progress->recalcTask($task);

        return back()->with('success', 'Dependency ditambahkan.');
    }

    public function reschedule(Request $request, Task $task)
    {
        $this->authorizeManage($task->event);

        $data = $request->validate([
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
        ]);

        $start = $data['start_at'] ?? null;
        $due = $data['due_at'] ?? null;

        if ($start && $due && strtotime($due) < strtotime($start)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Due date harus setelah start date.'], 422);
            }

            return back()->withErrors(['due_at' => 'Due date harus setelah start date.']);
        }

        $task->start_at = $start;
        $task->due_at = $due;
        $this->bumpVersion($task);
        $task->save();

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'start_at' => $task->start_at?->toDateString(),
                'due_at' => $task->due_at?->toDateString(),
            ]);
        }

        return back()->with('success', 'Jadwal task diperbarui.');
    }

    public function updateGraphPositions(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*.x' => ['required', 'numeric'],
            'positions.*.y' => ['required', 'numeric'],
        ]);

        foreach ($data['positions'] as $taskId => $position) {
            Task::where('event_id', $event->id)
                ->whereKey($taskId)
                ->update([
                    'graph_x' => $position['x'],
                    'graph_y' => $position['y'],
                ]);
        }

        return response()->json(['ok' => true]);
    }

    private function hasConflict($model, ?string $expected): bool
    {
        if (! $expected) {
            return false;
        }

        return $model->updated_at?->toDateTimeString() !== \Carbon\Carbon::parse($expected)->toDateTimeString();
    }

    private function wouldCycle(Task $task, Task $dependency): bool
    {
        $stack = [$dependency->id];
        $seen = [];

        while ($stack) {
            $id = array_pop($stack);
            if ($id === $task->id) {
                return true;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $parents = TaskDependency::where('task_id', $id)->pluck('depends_on_task_id')->all();
            foreach ($parents as $parent) {
                $stack[] = $parent;
            }
        }

        return false;
    }
}
