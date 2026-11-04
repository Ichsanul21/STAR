<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Event;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    use AuthorizesEvents;

    public function storeInvoice(Request $request, Event $event)
    {
        $this->authorizeFinance($event);

        $data = $this->validateInvoice($request);

        if ($error = $this->invoiceLimitError($event, (float) $data['amount'])) {
            return back()->withErrors(['amount' => $error]);
        }

        $this->createInvoice($event, $data);

        return back()->with('success', 'Invoice client dibuat.');
    }

    public function storeInvoiceGlobal(Request $request)
    {
        $data = $request->validate([
            'event_id' => ['required', 'exists:events,id'],
        ]);

        $event = Event::findOrFail($data['event_id']);
        $this->authorizeFinance($event);

        $payload = $this->validateInvoice($request);

        if ($error = $this->invoiceLimitError($event, (float) $payload['amount'])) {
            return back()->withErrors(['amount' => $error]);
        }

        $this->createInvoice($event, $payload);

        return back()->with('success', 'Invoice client dibuat untuk event '.$event->name.'.');
    }

    public function storePayment(Request $request, ClientInvoice $invoice, FinanceService $finance)
    {
        $this->authorizeFinance($invoice->event);

        $data = $this->validatePayment($request);

        if ((float) $data['amount'] > $invoice->balance() + 0.01) {
            return back()->withErrors(['amount' => 'Pembayaran melebihi sisa invoice.']);
        }

        $this->createPayment($invoice, $data, $request->file('proof'), $finance);

        return back()->with('success', 'Pembayaran client dicatat.');
    }

    public function storePaymentGlobal(Request $request, FinanceService $finance)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:client_invoices,id'],
        ]);

        $invoice = ClientInvoice::with('event')->findOrFail($data['invoice_id']);
        $this->authorizeFinance($invoice->event);

        $payload = $this->validatePayment($request);

        if ((float) $payload['amount'] > $invoice->balance() + 0.01) {
            return back()->withErrors(['amount' => 'Pembayaran melebihi sisa invoice.']);
        }

        $this->createPayment($invoice, $payload, $request->file('proof'), $finance);

        return back()->with('success', 'Pembayaran client dicatat untuk invoice '.$invoice->invoice_number.'.');
    }

    private function validateInvoice(Request $request): array
    {
        return $request->validate([
            'invoice_number' => ['required', 'string', 'max:80', 'unique:client_invoices,invoice_number'],
            'term' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ]);
    }

    private function validatePayment(Request $request): array
    {
        return $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['required', 'in:transfer,cash,cheque'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);
    }

    private function invoiceLimitError(Event $event, float $amount): ?string
    {
        $contract = (float) ($event->contract_value ?? $event->finalBudget?->total_planned ?? $event->planningBudget?->total_planned ?? 0);
        $invoiced = (float) $event->invoices()->sum('amount');
        $remaining = max(0, $contract - $invoiced);

        if ($contract > 0 && $amount > $remaining + 0.01) {
            return 'Amount melebihi sisa contract. Sisa yang bisa diinvoice: Rp'.number_format($remaining, 0, ',', '.');
        }

        return null;
    }

    private function createInvoice(Event $event, array $data): ClientInvoice
    {
        return ClientInvoice::create([
            'event_id' => $event->id,
            'invoice_number' => $data['invoice_number'],
            'term' => $data['term'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'due_date' => $data['due_date'] ?? null,
            'status' => 'unpaid',
        ]);
    }

    private function createPayment(ClientInvoice $invoice, array $data, $proof, FinanceService $finance): ClientPayment
    {
        $proofPath = null;

        if ($proof) {
            $extension = $proof->getClientOriginalExtension() ?: 'bin';
            $proofPath = $proof->storeAs("events/{$invoice->event_id}/payments", Str::uuid().'.'.$extension, 'local');
        }

        $payment = ClientPayment::create([
            'client_invoice_id' => $invoice->id,
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'method' => $data['method'],
            'proof_path' => $proofPath,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
            'status' => 'verified',
        ]);

        $finance->recalcInvoice($invoice);

        return $payment;
    }
}
