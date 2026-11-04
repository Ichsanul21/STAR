<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\ClientInvoice;
use App\Models\Expense;

class FinanceService
{
    public function recalcItem(BudgetItem $item): void
    {
        $item->actual_amount = (float) $item->expenses()
            ->whereIn('status', ['approved', 'paid', 'reimbursed'])
            ->sum('amount');
        $item->save();
    }

    public function recalcVersion(?BudgetVersion $version): void
    {
        if (! $version) {
            return;
        }

        foreach ($version->items as $item) {
            $this->recalcItem($item);
        }

        $version->total_planned = (float) $version->items()->sum('planned_amount');
        $version->total_actual = (float) $version->items()->sum('actual_amount');
        $version->save();
    }

    public function recalcExpense(Expense $expense): void
    {
        if ($expense->budgetItem) {
            $this->recalcItem($expense->budgetItem);
            $this->recalcVersion($expense->budgetItem->version);
        }
    }

    public function recalcInvoice(ClientInvoice $invoice): void
    {
        $invoice->status = $invoice->derivedStatus();
        $invoice->save();
    }

    public function eventPnl($event): array
    {
        $financials = $this->eventFinancials($event);
        $contract = (float) ($event->contract_value ?? $financials['planned'] ?? 0);
        $cost = (float) $financials['actual'];
        $grossProfit = $contract - $cost;
        $margin = $contract > 0 ? round($grossProfit / $contract * 100, 2) : 0;

        return [
            'contract' => $contract,
            'invoiced' => (float) $financials['invoiced'],
            'received' => (float) $financials['paid'],
            'cost' => $cost,
            'gross_profit' => $grossProfit,
            'margin' => $margin,
            'cash_position' => (float) $financials['paid'] - $cost,
            'outstanding' => (float) $financials['outstanding'],
        ];
    }

    public function eventFinancials($event): array
    {
        $version = $event->finalBudget()->first() ?: $event->planningBudget()->first();
        $planned = (float) ($version?->total_planned ?? 0);
        $actual = (float) $event->expenses()->whereIn('status', ['approved', 'paid', 'reimbursed'])->sum('amount');
        $committed = (float) $event->expenses()->whereIn('status', ['submitted', 'approved', 'paid', 'reimbursed'])->sum('amount');
        $invoiced = (float) $event->invoices()->sum('amount');
        $paid = (float) \App\Models\ClientPayment::whereHas(
            'invoice',
            fn ($q) => $q->where('event_id', $event->id)
        )->where('status', 'verified')->sum('amount');
        $outstanding = max(0, $invoiced - $paid);

        return [
            'planned' => $planned,
            'actual' => $actual,
            'committed' => $committed,
            'remaining' => $planned - $actual,
            'variance' => $actual - $planned,
            'absorption' => $planned > 0 ? round($actual / $planned * 100, 2) : 0,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'version' => $version,
        ];
    }
}
