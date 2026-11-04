<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Services\ConflictReporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaskProgressService
{
    public function __construct(
        private ProgressService $progress,
        private ActivityLogger $logger,
        private NotificationService $notifier
    ) {
    }

    public function advance(Task $task, User $user, array $data): TaskUpdate
    {
        if (! $user->canAdvanceTask($task)) {
            throw ValidationException::withMessages(['task' => 'Hanya PIC task, field crew, atau PIC divisi yang dapat memajukan task.']);
        }

        $note = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($note) < 3) {
            throw ValidationException::withMessages(['note' => 'Keterangan update wajib diisi minimal 3 karakter.']);
        }

        return DB::transaction(function () use ($task, $user, $data, $note) {
            $fresh = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $fresh->lock_version) {
                app(ConflictReporter::class)->record($fresh, $data['lock_version']);

                throw ValidationException::withMessages(['conflict' => 'Task sudah diubah orang lain. Muat ulang halaman.']);
            }

            $to = (string) $data['to_status'];
            if (! in_array($to, $this->allowedTransitions($fresh->status), true)) {
                throw ValidationException::withMessages(['to_status' => 'Transisi status tidak valid. Progress hanya boleh maju.']);
            }

            $photoPath = $this->storePhoto($fresh, $data['photo'] ?? null);

            $update = TaskUpdate::create([
                'event_id' => $fresh->event_id,
                'event_phase_id' => $fresh->group?->event_phase_id,
                'task_id' => $fresh->id,
                'user_id' => $user->id,
                'type' => 'status',
                'from_status' => $fresh->status,
                'to_status' => $to,
                'note' => $note,
                'photo_path' => $photoPath,
            ]);

            $fresh->status = $to;
            $fresh->completed_at = $to === 'done' ? now() : null;
            $fresh->lock_version = (int) $fresh->lock_version + 1;
            $fresh->save();

            $this->progress->recalcTask($fresh);

            $this->logger->log(
                'task.status_changed',
                "Task {$fresh->name}: {$update->from_status} → {$to}",
                $fresh->event,
                $fresh,
                ['note' => $note],
                ['status' => $update->from_status],
                ['status' => $to],
                $to === 'blocked' ? 'warning' : 'info',
                $user->id
            );

            $this->notifyStatusChange($fresh, $to);

            return $update;
        });
    }

    public function completeChecklist(TaskChecklist $checklist, User $user, array $data): TaskUpdate
    {
        $checklist->load('task.group');
        $task = $checklist->task;

        if (! $user->canAdvanceTask($task)) {
            throw ValidationException::withMessages(['checklist' => 'Hanya PIC task, field crew, atau PIC divisi yang dapat menyelesaikan checklist.']);
        }

        if ($checklist->is_done) {
            throw ValidationException::withMessages(['checklist' => 'Checklist sudah selesai dan tidak bisa dibatalkan. Ajukan revisi jika perlu koreksi.']);
        }

        $note = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($note) < 3) {
            throw ValidationException::withMessages(['note' => 'Keterangan checklist wajib diisi minimal 3 karakter.']);
        }

        return DB::transaction(function () use ($checklist, $task, $user, $data, $note) {
            $fresh = TaskChecklist::whereKey($checklist->id)->lockForUpdate()->firstOrFail();

            if (isset($data['lock_version']) && (int) $data['lock_version'] !== (int) $fresh->lock_version) {
                app(ConflictReporter::class)->record($fresh, $data['lock_version']);

                throw ValidationException::withMessages(['conflict' => 'Checklist sudah diubah orang lain. Muat ulang halaman.']);
            }

            if ($fresh->is_done) {
                throw ValidationException::withMessages(['checklist' => 'Checklist sudah selesai.']);
            }

            $photoPath = $this->storePhoto($task, $data['photo'] ?? null);

            $update = TaskUpdate::create([
                'event_id' => $task->event_id,
                'event_phase_id' => $task->group?->event_phase_id,
                'task_id' => $task->id,
                'task_checklist_id' => $fresh->id,
                'user_id' => $user->id,
                'type' => 'checklist',
                'from_status' => 'unchecked',
                'to_status' => 'checked',
                'note' => $note,
                'photo_path' => $photoPath,
            ]);

            $fresh->is_done = true;
            $fresh->lock_version = (int) $fresh->lock_version + 1;
            $fresh->save();

            $this->progress->recalcTask($task->fresh());

            $this->logger->log(
                'task.checklist_completed',
                "Checklist {$fresh->label} selesai pada task {$task->name}",
                $task->event,
                $task,
                ['note' => $note, 'checklist' => $fresh->label],
                ['checklist' => 'unchecked'],
                ['checklist' => 'checked'],
                'info',
                $user->id
            );

            return $update;
        });
    }

    public function requestRevision(Task $task, User $user, array $data): Task
    {
        if (! $user->canRequestRevision($task->event)) {
            throw ValidationException::withMessages(['revision' => 'Hanya reviewer atau PM yang dapat mengajukan revisi.']);
        }

        $reason = trim((string) ($data['note'] ?? ''));
        if (mb_strlen($reason) < 3) {
            throw ValidationException::withMessages(['note' => 'Alasan revisi wajib diisi minimal 3 karakter.']);
        }

        return DB::transaction(function () use ($task, $user, $reason, $data) {
            $photoPath = $this->storePhoto($task, $data['photo'] ?? null);

            $revision = Task::create([
                'event_id' => $task->event_id,
                'task_group_id' => $task->task_group_id,
                'revision_of_id' => $task->id,
                'is_revision' => true,
                'revision_reason' => $reason,
                'name' => 'Revisi: '.$task->name,
                'description' => $reason,
                'weight' => 1,
                'status' => 'todo',
                'priority' => $task->priority,
                'assigned_to' => $task->assigned_to,
                'due_at' => now()->addDays(3),
                'sort_order' => (int) Task::where('task_group_id', $task->task_group_id)->max('sort_order') + 1,
            ]);

            TaskUpdate::create([
                'event_id' => $revision->event_id,
                'event_phase_id' => $revision->group?->event_phase_id,
                'task_id' => $task->id,
                'user_id' => $user->id,
                'type' => 'revision',
                'from_status' => $task->status,
                'to_status' => 'revision',
                'note' => $reason,
                'photo_path' => $photoPath,
                'meta' => ['revision_task_id' => $revision->id],
            ]);

            $this->progress->recalcTask($revision);

            $this->logger->log(
                'task.revision_requested',
                "Revisi diajukan untuk task {$task->name}",
                $task->event,
                $task,
                ['reason' => $reason, 'revision_task_id' => $revision->id],
                [],
                ['revision_task_id' => $revision->id],
                'warning',
                $user->id
            );

            if ($revision->assigned_to) {
                $this->notifier->send(
                    User::find($revision->assigned_to),
                    'Revisi task baru',
                    $reason,
                    $task->event,
                    'task'
                );
            }

            return $revision;
        });
    }

    private function allowedTransitions(string $status): array
    {
        return match ($status) {
            'todo' => ['in_progress', 'blocked'],
            'in_progress' => ['review', 'blocked'],
            'review' => ['done', 'blocked'],
            'blocked' => ['in_progress'],
            default => [],
        };
    }

    private function storePhoto(Task $task, ?UploadedFile $photo): ?string
    {
        if (! $photo) {
            return null;
        }

        $extension = $photo->getClientOriginalExtension() ?: 'jpg';

        return $photo->storeAs("events/{$task->event_id}/task-updates", Str::uuid().'.'.$extension, 'local');
    }

    private function notifyStatusChange(Task $task, string $status): void
    {
        if (! in_array($status, ['review', 'blocked', 'done'], true)) {
            return;
        }

        $roles = $status === 'review' ? ['reviewer', 'pm_event'] : ['pm_event'];
        $users = User::whereHas('eventMemberships', function ($query) use ($task, $roles) {
            $query->where('event_id', $task->event_id)->whereIn('role', $roles);
        })->get();

        $this->notifier->send(
            $users,
            'Task '.$status,
            $task->name.' sekarang berstatus '.$status.'.',
            $task->event,
            'task'
        );
    }
}
