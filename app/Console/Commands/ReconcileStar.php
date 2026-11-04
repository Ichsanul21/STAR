<?php

namespace App\Console\Commands;

use App\Models\BudgetVersion;
use App\Models\Event;
use App\Models\Task;
use App\Services\FinanceService;
use App\Services\ProgressService;
use Illuminate\Console\Command;

class ReconcileStar extends Command
{
    protected $signature = 'star:reconcile {--dry-run : Only report mismatches}';

    protected $description = 'Recalculate task rollups and budget actuals, and report mismatches.';

    public function handle(ProgressService $progress, FinanceService $finance): int
    {
        $dry = (bool) $this->option('dry-run');
        $taskFixes = 0;
        $budgetFixes = 0;

        Event::with(['tasks', 'budgetVersions.items'])->chunk(50, function ($events) use ($progress, $finance, $dry, &$taskFixes, &$budgetFixes) {
            foreach ($events as $event) {
                foreach ($event->tasks as $task) {
                    $expected = $task->computedProgress();
                    if (abs((float) $task->progress - $expected) > 0.01) {
                        $taskFixes++;
                        if (! $dry) {
                            $progress->recalcTask($task);
                        }
                    }
                }

                if (! $dry) {
                    foreach ($event->phases as $phase) {
                        $progress->recalcPhase($phase);
                    }
                }

                foreach ($event->budgetVersions as $version) {
                    $expectedActual = (float) $version->items->sum('actual_amount');
                    $expectedPlanned = (float) $version->items->sum('planned_amount');
                    if (abs((float) $version->total_actual - $expectedActual) > 0.01
                        || abs((float) $version->total_planned - $expectedPlanned) > 0.01) {
                        $budgetFixes++;
                        if (! $dry) {
                            $finance->recalcVersion($version);
                        }
                    }
                }
            }
        });

        $this->info("Task rollup fixes: {$taskFixes}");
        $this->info("Budget total fixes: {$budgetFixes}");
        $this->info($dry ? 'Dry run: no changes written.' : 'Reconciliation complete.');

        return self::SUCCESS;
    }
}
