<?php

namespace App\Http\Controllers;

use App\Models\BudgetVersion;
use App\Models\ClientInvoice;
use App\Models\Event;
use App\Models\Expense;
use App\Models\MediaItem;
use App\Models\ReportVersion;
use App\Models\Task;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class IntegrityController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isFinance(), 403);

        $issues = collect();

        Task::with('event')->chunk(200, function ($tasks) use ($issues) {
            foreach ($tasks as $task) {
                $expected = $task->computedProgress();
                if (abs((float) $task->progress - $expected) > 0.01) {
                    $issues->push([
                        'type' => 'Task progress',
                        'severity' => 'warning',
                        'message' => "Task \"{$task->name}\" progress {$task->progress}%, expected {$expected}%.",
                        'event' => $task->event,
                        'url' => $task->event ? route('events.show', $task->event).'#tasks' : null,
                    ]);
                }
            }
        });

        BudgetVersion::with('event', 'items')->chunk(100, function ($versions) use ($issues) {
            foreach ($versions as $version) {
                $planned = (float) $version->items->sum('planned_amount');
                $actual = (float) $version->items->sum('actual_amount');

                if (abs((float) $version->total_planned - $planned) > 0.01 || abs((float) $version->total_actual - $actual) > 0.01) {
                    $issues->push([
                        'type' => 'Budget total',
                        'severity' => 'danger',
                        'message' => "RAB {$version->stage} v{$version->version} total tidak sinkron.",
                        'event' => $version->event,
                        'url' => $version->event ? route('events.show', $version->event).'?budget_version='.$version->id.'#budget' : null,
                    ]);
                }
            }
        });

        Expense::whereNull('budget_item_id')->with('event')->chunk(100, function ($expenses) use ($issues) {
            foreach ($expenses as $expense) {
                $issues->push([
                    'type' => 'Expense without RAB',
                    'severity' => 'danger',
                    'message' => "Expense \"{$expense->description}\" tidak punya budget item.",
                    'event' => $expense->event,
                    'url' => $expense->event ? route('events.show', $expense->event).'#expenses' : null,
                ]);
            }
        });

        ClientInvoice::with('event', 'payments')->chunk(100, function ($invoices) use ($issues) {
            foreach ($invoices as $invoice) {
                $derived = $invoice->derivedStatus();
                if ($invoice->status !== $derived) {
                    $issues->push([
                        'type' => 'Invoice status',
                        'severity' => 'warning',
                        'message' => "Invoice {$invoice->invoice_number} status {$invoice->status}, expected {$derived}.",
                        'event' => $invoice->event,
                        'url' => $invoice->event ? route('events.show', $invoice->event).'#payments' : null,
                    ]);
                }
            }
        });

        ReportVersion::where('status', 'published')->with('event')->chunk(100, function ($reports) use ($issues) {
            foreach ($reports as $report) {
                foreach (['pdf_path' => 'PDF', 'xlsx_path' => 'Excel'] as $field => $label) {
                    if ($report->{$field} && ! Storage::disk('local')->exists($report->{$field})) {
                        $issues->push([
                            'type' => 'Report file',
                            'severity' => 'danger',
                            'message' => "File {$label} laporan v{$report->version} tidak ditemukan.",
                            'event' => $report->event,
                            'url' => $report->event ? route('events.show', $report->event).'#reports' : null,
                        ]);
                    }
                }
            }
        });

        MediaItem::with('event')->chunk(200, function ($media) use ($issues) {
            foreach ($media as $item) {
                $path = $item->displayPath();
                if ($path && ! Storage::disk('local')->exists($path)) {
                    $issues->push([
                        'type' => 'Media file',
                        'severity' => 'danger',
                        'message' => "File foto #{$item->id} tidak ditemukan.",
                        'event' => $item->event,
                        'url' => $item->event ? route('events.show', $item->event).'#documentation' : null,
                    ]);
                }
            }
        });

        $eventsWithoutPhases = Event::doesntHave('phases')->count();
        if ($eventsWithoutPhases > 0) {
            $issues->push([
                'type' => 'Event structure',
                'severity' => 'warning',
                'message' => "{$eventsWithoutPhases} event belum punya fase.",
                'event' => null,
                'url' => route('events.index'),
            ]);
        }

        $issues = $issues->sortBy(fn ($issue) => $issue['severity'] === 'danger' ? 0 : 1)->values();
        $danger = $issues->where('severity', 'danger')->count();
        $warning = $issues->where('severity', 'warning')->count();

        return view('integrity.index', compact('issues', 'danger', 'warning'));
    }

    public function reconcile()
    {
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isFinance(), 403);

        Artisan::call('star:reconcile');

        return back()->with('success', 'Reconcile selesai: '.trim(Artisan::output()));
    }
}
