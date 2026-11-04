<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Http\Controllers\Concerns\ChecksVersion;
use App\Models\Event;
use App\Models\Expense;
use App\Models\BudgetItem;
use App\Services\ExpenseService;
use App\Services\FinanceService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    use AuthorizesEvents, ChecksVersion;

    public function store(Request $request, Event $event, FinanceService $finance, ExpenseService $expenses)
    {
        $this->authorizeExpenseCreate($event);

        $expense = $expenses->create($event, $this->validateExpense($request), $request->file('receipt'));

        if ($request->expectsJson()) {
            session()->flash('success', 'Expense dicatat.');

            return response()->json(['ok' => true, 'id' => $expense->id, 'message' => 'Expense dicatat.']);
        }

        return back()->with('success', 'Expense dicatat.');
    }

    public function storeGlobal(Request $request, ExpenseService $expenses)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        $this->authorizeExpenseCreate($event);

        $expense = $expenses->create($event, $this->validateExpense($request), $request->file('receipt'));

        if ($request->expectsJson()) {
            session()->flash('success', 'Expense dicatat pada event '.$event->name.'.');

            return response()->json(['ok' => true, 'message' => 'Expense dicatat pada event '.$event->name.'.']);
        }

        return back()->with('success', 'Expense dicatat pada event '.$event->name.'.');
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:160'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'budget_item_id' => ['nullable', 'exists:budget_items,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', 'in:cash,transfer,card,petty_cash'],
            'paid_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
    }

    public function update(Request $request, Expense $expense, FinanceService $finance)
    {
        $this->authorizeExpenseCreate($expense->event);

        if (in_array($expense->status, ['approved', 'paid', 'reimbursed'], true) && ! auth()->user()->isDev()) {
            return back()->withErrors(['expense' => 'Expense yang sudah approved tidak bisa diedit. Buat adjustment expense.']);
        }

        if ($this->hasVersionConflict($expense, $request->input('lock_version'))) {
            return $this->conflictResponse($request, 'Expense sudah diubah orang lain. Muat ulang halaman.');
        }

        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['required', 'string', 'max:160'],
            'qty' => ['required', 'numeric', 'min:0.01'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'budget_item_id' => ['nullable', 'exists:budget_items,id'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'vendor' => ['nullable', 'string', 'max:120'],
            'payment_method' => ['required', 'in:cash,transfer,card,petty_cash'],
            'paid_by' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $oldItem = $expense->budgetItem;
        $newItem = ! empty($data['budget_item_id']) ? BudgetItem::find($data['budget_item_id']) : $oldItem;

        $receiptPath = $expense->receipt_path;
        if ($request->hasFile('receipt')) {
            if ($receiptPath) {
                Storage::disk('local')->delete($receiptPath);
            }
            $file = $request->file('receipt');
            $ext = $file->getClientOriginalExtension() ?: 'bin';
            $receiptPath = $file->storeAs("events/{$expense->event_id}/receipts", Str::uuid().'.'.$ext, 'local');
        }

        $expense->update([
            'budget_item_id' => $newItem?->id,
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
            'status' => $expense->status === 'rejected' ? 'submitted' : $expense->status,
            'approved_by' => $expense->status === 'rejected' ? null : $expense->approved_by,
            'approved_at' => $expense->status === 'rejected' ? null : $expense->approved_at,
            'notes' => $data['notes'] ?? null,
            'lock_version' => (int) $expense->lock_version + 1,
        ]);

        if ($oldItem) {
            $finance->recalcItem($oldItem);
            $finance->recalcVersion($oldItem->version);
        }
        if ($newItem) {
            $finance->recalcItem($newItem);
            $finance->recalcVersion($newItem->version);
        }

        return back()->with('success', 'Expense diperbarui.');
    }

    public function approve(Request $request, Expense $expense, FinanceService $finance, NotificationService $notifier)
    {
        $this->authorizeExpenseApprove($expense->event);

        if ($expense->status !== 'submitted') {
            return back()->withErrors(['expense' => 'Expense sudah diproses orang lain.']);
        }

        if ($this->hasVersionConflict($expense, $request->input('lock_version'))) {
            return $this->conflictResponse($request, 'Expense sudah diubah orang lain. Muat ulang halaman.');
        }

        $expense->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $this->bumpVersion($expense);
        $expense->save();

        $finance->recalcExpense($expense);

        $this->notifyCreator($expense, 'approved', $notifier);

        return back()->with('success', 'Expense disetujui.');
    }

    public function bulk(Request $request, FinanceService $finance, NotificationService $notifier)
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'expense_ids' => ['required', 'array', 'min:1'],
            'expense_ids.*' => ['integer', 'exists:expenses,id'],
        ]);

        $expenses = Expense::with('event')->whereIn('id', $data['expense_ids'])->get();

        foreach ($expenses as $expense) {
            $this->authorizeExpenseApprove($expense->event);
        }

        $processed = 0;
        $processedExpenses = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($expenses, $data, $finance, $notifier, &$processed, &$processedExpenses) {
            foreach ($expenses as $expense) {
                $fresh = Expense::whereKey($expense->id)->lockForUpdate()->first();

                if (! $fresh || $fresh->status !== 'submitted') {
                    continue;
                }

                $fresh->update([
                    'status' => $data['action'] === 'approve' ? 'approved' : 'rejected',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);
                $this->bumpVersion($fresh);
                $fresh->save();

                $finance->recalcExpense($fresh);
                $processed++;
                $processedExpenses[] = $fresh;
            }
        });

        foreach ($processedExpenses as $processedExpense) {
            $this->notifyCreator($processedExpense, $data['action'] === 'approve' ? 'approved' : 'rejected', $notifier);
        }

        $skipped = $expenses->count() - $processed;

        return back()->with('success', $processed.' expense diproses.'.($skipped > 0 ? ' '.$skipped.' dilewati karena sudah diproses.' : ''));
    }

    private function notifyCreator(Expense $expense, string $status, NotificationService $notifier): void
    {
        $creator = $expense->creator;

        if (! $creator || (int) $creator->id === (int) auth()->id()) {
            return;
        }

        $label = $status === 'approved' ? 'disetujui' : 'ditolak';

        $notifier->send(
            $creator,
            'Expense '.$label,
            $expense->description.' · Rp'.number_format((float) $expense->amount, 0, ',', '.'),
            $expense->event,
            'expense'
        );
    }

    public function receipt(Expense $expense)
    {
        $this->authorizeEvent($expense->event);

        if (! $expense->receipt_path || ! Storage::disk('local')->exists($expense->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->response($expense->receipt_path);
    }

    public function reject(Request $request, Expense $expense, FinanceService $finance, NotificationService $notifier)
    {
        $this->authorizeExpenseApprove($expense->event);

        if ($expense->status !== 'submitted') {
            return back()->withErrors(['expense' => 'Expense sudah diproses orang lain.']);
        }

        if ($this->hasVersionConflict($expense, $request->input('lock_version'))) {
            return $this->conflictResponse($request, 'Expense sudah diubah orang lain. Muat ulang halaman.');
        }

        $expense->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        $this->bumpVersion($expense);
        $expense->save();

        $finance->recalcExpense($expense);

        $this->notifyCreator($expense, 'rejected', $notifier);

        return back()->with('success', 'Expense ditolak.');
    }
}
