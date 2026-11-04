<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\BudgetVersion;
use App\Models\ClientInvoice;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Task;
use App\Models\TaskUpdate;
use App\Services\FinanceService;

class DashboardController extends Controller
{
    public function index(FinanceService $finance)
    {
        $user = auth()->user();

        if ($user->isDealer()) {
            return redirect()->route('portal.index');
        }

        $eventQuery = Event::query()->visibleTo($user);
        $eventIds = (clone $eventQuery)->pluck('id');

        $taskQuery = Task::query()->whereIn('event_id', $eventIds);
        $taskStats = (clone $taskQuery)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $activeEvents = (clone $eventQuery)->where('status', 'active')->count();
        $draftEvents = (clone $eventQuery)->where('status', 'draft')->count();
        $completedEvents = (clone $eventQuery)->where('status', 'closed')->count();

        $overdueTasks = (clone $taskQuery)->whereNotNull('due_at')->whereDate('due_at', '<', now()->toDateString())->whereNotIn('status', ['done', 'cancelled'])->count();
        $dueSoonTasks = (clone $taskQuery)->whereDate('due_at', '>=', now()->toDateString())->whereDate('due_at', '<=', now()->addDays(7)->toDateString())->whereNotIn('status', ['done', 'cancelled'])->count();

        $deadlineToday = (clone $taskQuery)->whereDate('due_at', now()->toDateString())->whereNotIn('status', ['done', 'cancelled'])->count();
        $deadlineSoon = (clone $taskQuery)->whereDate('due_at', '>=', now()->toDateString())->whereDate('due_at', '<=', now()->addDays(3)->toDateString())->whereNotIn('status', ['done', 'cancelled'])->count();

        $deadlineTasks = (clone $taskQuery)
            ->with('event')
            ->whereNotNull('due_at')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->orderBy('due_at')
            ->limit(4)
            ->get();

        $planned = (float) BudgetVersion::query()
            ->whereIn('event_id', $eventIds)
            ->where('status', 'approved')
            ->sum('total_planned');

        if ($planned <= 0) {
            $planned = (float) BudgetVersion::query()->whereIn('event_id', $eventIds)->sum('total_planned');
        }

        $actual = (float) Expense::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', ['approved', 'paid', 'reimbursed'])
            ->sum('amount');

        $absorption = $planned > 0 ? round($actual / $planned * 100, 1) : 0;

        $invoices = ClientInvoice::with('payments')->whereIn('event_id', $eventIds)->get();
        $invoiced = (float) $invoices->sum('amount');
        $received = (float) $invoices->sum(fn (ClientInvoice $invoice) => $invoice->paidAmount());
        $outstanding = (float) $invoices->sum(fn (ClientInvoice $invoice) => $invoice->balance());

        $contract = (float) Event::with('finalBudget')->whereIn('id', $eventIds)->get()
            ->sum(fn (Event $event) => (float) ($event->contract_value ?? $event->finalBudget?->total_planned ?? 0));
        $grossProfit = $contract - $actual;
        $margin = $contract > 0 ? round($grossProfit / $contract * 100, 1) : 0;

        $performanceEvents = Event::with(['dealer', 'phases', 'finalBudget', 'planningBudget'])
            ->whereIn('id', $eventIds)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")
            ->orderByDesc('start_date')
            ->limit(6)
            ->get()
            ->map(function (Event $event) use ($finance) {
                $pnl = $finance->eventPnl($event);

                return [
                    'name' => $event->name,
                    'dealer' => $event->dealer?->name,
                    'progress' => $event->progress(),
                    'absorption' => $event->finalBudget && (float) $event->finalBudget->total_planned > 0
                        ? round((float) $event->finalBudget->total_actual / (float) $event->finalBudget->total_planned * 100, 1)
                        : 0,
                    'profit' => $pnl['gross_profit'],
                    'margin' => $pnl['margin'],
                ];
            })
            ->values();

        $activeEventList = Event::with(['dealer', 'phases', 'finalBudget', 'planningBudget'])
            ->whereIn('id', $eventIds)
            ->where('status', 'active')
            ->orderBy('start_date')
            ->limit(5)
            ->get()
            ->map(fn (Event $event) => [
                'event' => $event,
                'progress' => $event->progress(),
                'pnl' => $finance->eventPnl($event),
            ]);

        $agendaTasks = Task::with(['event'])
            ->whereIn('event_id', $eventIds)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $dueInvoices = $invoices
            ->filter(fn (ClientInvoice $invoice) => $invoice->balance() > 0 && $invoice->due_date !== null)
            ->sortBy('due_date')
            ->take(3)
            ->values();

        $eventsWithoutReport = Event::query()
            ->whereIn('id', $eventIds)
            ->where('status', '!=', 'draft')
            ->whereDoesntHave('reports')
            ->orderBy('start_date')
            ->get();

        $teamUpdates = TaskUpdate::with(['user', 'task', 'event'])
            ->whereIn('event_id', $eventIds)
            ->latest()
            ->limit(6)
            ->get();

        $activities = ActivityLog::with('user', 'event')->visibleTo($user)->latest()->take(6)->get();

        return view('dashboard', compact(
            'activeEvents', 'draftEvents', 'completedEvents', 'overdueTasks', 'dueSoonTasks',
            'deadlineTasks', 'deadlineToday', 'deadlineSoon',
            'planned', 'actual', 'absorption',
            'invoiced', 'received', 'outstanding', 'contract', 'grossProfit', 'margin',
            'performanceEvents', 'activeEventList', 'agendaTasks', 'dueInvoices', 'eventsWithoutReport',
            'teamUpdates', 'taskStats', 'activities'
        ));
    }
}
