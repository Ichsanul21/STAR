<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class ActionInboxService
{
    /**
     * Work that still needs the user's action, derived from domain state.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function items(User $user): Collection
    {
        if ($user->isDealer()) {
            return collect();
        }

        $items = collect();

        Expense::with('event')
            ->where('status', 'submitted')
            ->whereHas('event', fn ($query) => $query->visibleTo($user))
            ->latest('expense_date')
            ->get()
            ->filter(fn (Expense $expense) => $user->canApproveExpense($expense->event))
            ->each(function (Expense $expense) use ($items) {
                $items->push([
                    'category' => 'finance',
                    'title' => 'Expense menunggu approval',
                    'subtitle' => $expense->description.' · '.($expense->event?->name ?? '—'),
                    'meta' => 'Rp'.number_format((float) $expense->amount, 0, ',', '.').' · '.$expense->expense_date?->format('d/m/Y'),
                    'url' => route('global.expenses', ['status' => 'submitted']),
                    'action' => 'Review',
                ]);
            });

        Task::with('event')
            ->where('assigned_to', $user->id)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays(7))
            ->orderBy('due_at')
            ->limit(10)
            ->get()
            ->each(function (Task $task) use ($items) {
                $items->push([
                    'category' => 'task',
                    'title' => 'Task perlu dikerjakan',
                    'subtitle' => $task->name.' · '.($task->event?->name ?? '—'),
                    'meta' => $task->due_at?->isPast()
                        ? 'Terlambat '.$task->due_at->diffForHumans()
                        : 'Jatuh tempo '.$task->due_at->diffForHumans(),
                    'url' => $task->event ? route('events.tab', [$task->event, 'tasks']) : route('global.my-tasks'),
                    'action' => 'Buka',
                ]);
            });

        ReportVersion::with('event')
            ->where('status', 'draft')
            ->whereHas('event', fn ($query) => $query->visibleTo($user))
            ->latest()
            ->limit(10)
            ->get()
            ->filter(fn (ReportVersion $report) => $report->event && $user->canManageEvent($report->event))
            ->each(function (ReportVersion $report) use ($items) {
                $items->push([
                    'category' => 'report',
                    'title' => 'Laporan draft siap dipublish',
                    'subtitle' => ($report->event?->name ?? '—').' · v'.$report->version,
                    'meta' => 'Dibuat '.$report->created_at?->format('d/m/Y H:i'),
                    'url' => $report->event ? route('events.tab', [$report->event, 'reports']) : route('global.reports'),
                    'action' => 'Publish',
                ]);
            });

        return $items->values();
    }

    /**
     * @return array{task: int, finance: int, report: int, total: int}
     */
    public function counts(User $user): array
    {
        $items = $this->items($user);

        return [
            'task' => $items->where('category', 'task')->count(),
            'finance' => $items->where('category', 'finance')->count(),
            'report' => $items->where('category', 'report')->count(),
            'total' => $items->count(),
        ];
    }
}
