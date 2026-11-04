<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ReportVersion;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function export(string $type, Request $request, FinanceService $finance): StreamedResponse
    {
        return match ($type) {
            'budget' => $this->budget($request, $finance),
            'expenses' => $this->expenses($request),
            'payments' => $this->payments($request),
            'reports' => $this->reports($request),
            'clients' => $this->clients(),
            default => abort(404),
        };
    }

    public function budget(Request $request, FinanceService $finance): StreamedResponse
    {
        abort_if(auth()->user()->isDealer(), 403);

        $events = Event::visibleTo(auth()->user())
            ->with(['dealer', 'finalBudget', 'planningBudget'])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->integer('dealer_id'), fn ($q, $id) => $q->where('dealer_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('start_date')
            ->get();

        $rows = $events->map(function (Event $event) use ($finance) {
            $pnl = $finance->eventPnl($event);
            $financial = $finance->eventFinancials($event);

            return [
                $event->code,
                $event->name,
                $event->dealer?->name,
                $event->status,
                $financial['planned'],
                $financial['actual'],
                $financial['absorption'].'%',
                $pnl['contract'],
                $pnl['gross_profit'],
                $pnl['margin'].'%',
                $pnl['outstanding'],
            ];
        });

        return $this->csv('budget-events-'.now()->format('Ymd-His').'.csv', [
            'Kode', 'Event', 'Dealer', 'Status', 'RAB Rencana', 'Realisasi', 'Serapan',
            'Contract', 'P&L', 'Margin %', 'Outstanding',
        ], $rows);
    }

    public function expenses(Request $request): StreamedResponse
    {
        abort_if(auth()->user()->isDealer(), 403);

        $expenses = Expense::with(['event.dealer', 'budgetItem'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('description', 'like', "%{$search}%")->orWhere('vendor', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('expense_date')
            ->get();

        $rows = $expenses->map(fn (Expense $expense) => [
            $expense->expense_date?->toDateString(),
            $expense->event?->code,
            $expense->event?->name,
            $expense->category,
            $expense->description,
            $expense->vendor,
            (float) $expense->qty,
            (float) $expense->unit_price,
            (float) $expense->amount,
            $expense->status,
            $expense->budgetItem?->description,
        ]);

        return $this->csv('expenses-'.now()->format('Ymd-His').'.csv', [
            'Tanggal', 'Kode Event', 'Event', 'Kategori', 'Deskripsi', 'Vendor',
            'Qty', 'Harga Satuan', 'Amount', 'Status', 'Budget Item',
        ], $rows);
    }

    public function payments(Request $request): StreamedResponse
    {
        abort_if(auth()->user()->isDealer(), 403);

        $invoices = ClientInvoice::with(['event.dealer', 'payments'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('invoice_number', 'like', "%{$search}%")->orWhere('term', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->get();

        $rows = $invoices->map(fn (ClientInvoice $invoice) => [
            $invoice->invoice_number,
            $invoice->event?->code,
            $invoice->event?->name,
            $invoice->term,
            (float) $invoice->amount,
            $invoice->paidAmount(),
            $invoice->balance(),
            $invoice->due_date?->toDateString(),
            $invoice->derivedStatus(),
        ]);

        return $this->csv('payments-'.now()->format('Ymd-His').'.csv', [
            'Invoice', 'Kode Event', 'Event', 'Termin', 'Amount', 'Paid', 'Sisa', 'Due Date', 'Status',
        ], $rows);
    }

    public function reports(Request $request): StreamedResponse
    {
        abort_if(auth()->user()->isDealer(), 403);

        $reports = ReportVersion::with(['event.dealer', 'generator'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->get();

        $rows = $reports->map(fn (ReportVersion $report) => [
            $report->event?->code,
            $report->event?->name,
            $report->version,
            $report->status,
            $report->generator?->name,
            $report->created_at?->toDateTimeString(),
            $report->published_at?->toDateTimeString(),
        ]);

        return $this->csv('reports-'.now()->format('Ymd-His').'.csv', [
            'Kode Event', 'Event', 'Versi', 'Status', 'Dibuat Oleh', 'Dibuat', 'Published',
        ], $rows);
    }

    public function clients(): StreamedResponse
    {
        abort_if(auth()->user()->isDealer(), 403);

        $dealers = Dealer::withCount('events')->orderBy('name')->get();

        $rows = $dealers->map(fn (Dealer $dealer) => [
            $dealer->code,
            $dealer->name,
            $dealer->city,
            $dealer->pic_name,
            $dealer->phone,
            $dealer->events_count,
            $dealer->is_active ? 'Aktif' : 'Nonaktif',
        ]);

        return $this->csv('clients-'.now()->format('Ymd-His').'.csv', [
            'Kode', 'Dealer', 'Kota', 'PIC', 'Telepon', 'Jumlah Event', 'Status',
        ], $rows);
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value) => is_bool($value) ? ($value ? '1' : '0') : $value, $row));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
