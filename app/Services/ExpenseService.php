<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\Event;
use App\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseService
{
    public function __construct(private FinanceService $finance)
    {
    }

    public function create(Event $event, array $data, ?UploadedFile $receipt, ?int $userId = null): Expense
    {
        return DB::transaction(function () use ($event, $data, $receipt, $userId) {
            $budgetItem = $this->resolveBudgetItem($event, $data['budget_item_id'] ?? null);

            $receiptPath = null;
            if ($receipt) {
                $extension = $receipt->getClientOriginalExtension() ?: 'bin';
                $receiptPath = $receipt->storeAs("events/{$event->id}/receipts", Str::uuid().'.'.$extension, 'local');
            }

            $expense = Expense::create([
                'event_id' => $event->id,
                'budget_item_id' => $budgetItem->id,
                'task_id' => $data['task_id'] ?? null,
                'expense_date' => $data['expense_date'],
                'category' => $data['category'],
                'description' => $data['description'],
                'qty' => $data['qty'],
                'unit_price' => $data['unit_price'],
                'amount' => round((float) $data['qty'] * (float) $data['unit_price'], 2),
                'vendor' => $data['vendor'] ?? null,
                'payment_method' => $data['payment_method'],
                'paid_by' => $data['paid_by'] ?? null,
                'receipt_path' => $receiptPath,
                'status' => 'submitted',
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId ?? auth()->id(),
            ]);

            $this->finance->recalcExpense($expense);

            return $expense;
        });
    }

    private function resolveBudgetItem(Event $event, ?string $budgetItemId): BudgetItem
    {
        if (! empty($budgetItemId)) {
            $item = BudgetItem::findOrFail($budgetItemId);

            if ((int) $item->version?->event_id !== (int) $event->id) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'budget_item_id' => 'Budget item bukan milik event ini.',
                ]);
            }

            return $item;
        }

        $budget = $event->finalBudget ?: $event->planningBudget;

        if (! $budget) {
            $budget = BudgetVersion::create([
                'event_id' => $event->id,
                'stage' => 'planning',
                'version' => 1,
                'status' => 'draft',
            ]);
        }

        return BudgetItem::firstOrCreate(
            ['budget_version_id' => $budget->id, 'stable_key' => 'unbudgeted'],
            [
                'category' => 'Unbudgeted',
                'description' => 'Pengeluaran tanpa RAB',
                'qty' => 1,
                'unit' => 'item',
                'unit_price' => 0,
                'planned_amount' => 0,
                'sort_order' => 999,
            ]
        );
    }
}
