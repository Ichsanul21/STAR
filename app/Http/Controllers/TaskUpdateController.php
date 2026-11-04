<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskUpdate;
use App\Services\TaskProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TaskUpdateController extends Controller
{
    use AuthorizesEvents;

    public function advance(Request $request, Task $task, TaskProgressService $service)
    {
        $this->authorizeEvent($task->event);

        $data = $request->validate([
            'to_status' => ['required', 'in:in_progress,review,done,blocked'],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $service->advance($task, auth()->user(), $data);

        return back()->with('success', 'Progress task diperbarui.');
    }

    public function completeChecklist(Request $request, TaskChecklist $checklist, TaskProgressService $service)
    {
        $checklist->load('task.event');
        $this->authorizeEvent($checklist->task->event);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        $service->completeChecklist($checklist, auth()->user(), $data);

        return back()->with('success', 'Checklist diselesaikan.');
    }

    public function requestRevision(Request $request, Task $task, TaskProgressService $service)
    {
        $this->authorizeEvent($task->event);

        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:10240'],
        ]);

        $service->requestRevision($task, auth()->user(), $data);

        return back()->with('success', 'Task revisi dibuat.');
    }

    public function photo(TaskUpdate $update)
    {
        $this->authorizeEvent($update->event);

        if (! $update->photo_path || ! Storage::disk('local')->exists($update->photo_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($update->photo_path);
    }
}
