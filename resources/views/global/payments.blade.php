@extends('layouts.app')

@section('title', 'Payments')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Payments</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Invoice client, pembayaran, dan outstanding.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('global.export', ['type' => 'payments'] + request()->query()) }}" class="btn btn-secondary">Export CSV</a>
        @if ($financeEvents->isNotEmpty())
            <button type="button" class="btn btn-secondary" data-modal-target="globalCreateInvoice">+ Buat Invoice</button>
            @if ($unpaidInvoices->isNotEmpty())
                <button type="button" class="btn btn-primary" data-modal-target="globalCreatePayment">+ Catat Pembayaran</button>
            @endif
        @endif
    </div>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search"><label class="label">Cari</label><input name="search" value="{{ request('search') }}" class="input" placeholder="No invoice / termin…"></div>
        <div class="filter-field"><label class="label">Event</label><select name="event_id" class="input"><option value="">Semua</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
        <div class="filter-field"><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('global.payments') }}" class="btn btn-secondary">Reset</a></div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'event_id' => 'Event']"
    :names="['event_id' => $events->pluck('name', 'id'), 'status' => ['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue']]"
/>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Invoice</th><th>Event</th><th>Termin</th><th class="text-right">Amount</th><th class="text-right">Paid</th><th class="text-right">Sisa</th><th>Due</th><th>Status</th></tr></thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td class="font-semibold">{{ $invoice->invoice_number }}</td>
                        <td><a href="{{ route('events.tab', [$invoice->event, 'finance']) }}?view=payments" class="link text-xs">{{ $invoice->event?->name }}</a></td>
                        <td>{{ $invoice->term }}</td>
                        <td class="text-right"><x-money :value="$invoice->amount" /></td>
                        <td class="text-right"><x-money :value="$invoice->paidAmount()" /></td>
                        <td class="text-right"><x-money :value="$invoice->balance()" /></td>
                        <td class="whitespace-nowrap">{{ $invoice->due_date?->format('d/m/Y') ?: '—' }}</td>
                        <td><x-status :status="$invoice->derivedStatus()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada invoice.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $invoices->links() }}</div>

@if ($financeEvents->isNotEmpty())
    <x-modal id="globalCreateInvoice" title="Buat Invoice Client" size="lg">
        <form method="POST" action="{{ route('invoices.store-global') }}" class="grid gap-3 md:grid-cols-3">
            @csrf
            <div class="md:col-span-3">
                <label class="label">Event</label>
                <select name="event_id" class="input" required data-invoice-event data-finance-base="{{ url('/events') }}">
                    <option value="">Pilih event…</option>
                    @foreach ($financeEvents as $option)
                        <option value="{{ $option->id }}" @selected(old('event_id', $financeEvents->count() === 1 ? $option->id : null) == $option->id)>
                            {{ $option->name }} · {{ $option->dealer?->name }} · {{ $option->status }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-3 rounded-xl bg-slate-50 px-3 py-2 text-xs" style="color: var(--muted)" data-invoice-info>
                Pilih event untuk melihat sisa contract.
            </div>
            <div><label class="label">No. Invoice</label><input name="invoice_number" class="input" required placeholder="INV-KODE-01" data-invoice-number value="{{ old('invoice_number') }}"></div>
            <div>
                <label class="label">Termin</label>
                <select name="term" class="input" required data-creatable>
                    <option value="">Pilih termin…</option>
                    @foreach (config('star_options.invoice_terms') as $value => $label)
                        <option value="{{ $value }}" @selected(old('term') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="label">Amount</label><input type="number" step="0.01" name="amount" class="input" required value="{{ old('amount') }}" data-invoice-amount></div>
            <div class="md:col-span-2"><label class="label">Deskripsi</label><input name="description" class="input" value="{{ old('description') }}" placeholder="Pembayaran 50% event"></div>
            <div><label class="label">Due Date</label><input type="date" name="due_date" class="input" value="{{ old('due_date') }}"></div>
            <div class="md:col-span-3 flex justify-end gap-2">
                <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                <button class="btn btn-primary" type="submit">Buat Invoice</button>
            </div>
        </form>
    </x-modal>

    @if ($unpaidInvoices->isNotEmpty())
        <x-modal id="globalCreatePayment" title="Catat Pembayaran Client" size="lg">
            <form method="POST" action="{{ route('payments.store-global') }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2">
                    <label class="label">Invoice</label>
                    <select name="invoice_id" class="input" required data-payment-invoice>
                        <option value="">Pilih invoice…</option>
                        @foreach ($unpaidInvoices as $option)
                            <option value="{{ $option->id }}" data-balance="{{ $option->balance() }}" @selected(old('invoice_id') == $option->id)>
                                {{ $option->invoice_number }} · {{ $option->event?->name }} · sisa Rp{{ number_format($option->balance(), 0, ',', '.') }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Amount</label><input type="number" step="0.01" name="amount" class="input" required value="{{ old('amount') }}" data-payment-amount></div>
                <div><label class="label">Tanggal</label><input type="datetime-local" name="paid_at" class="input" required value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                <div>
                    <label class="label">Metode</label>
                    <select name="method" class="input">
                        <option value="transfer">Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div><label class="label">Bukti</label><input type="file" name="proof" class="input" accept=".jpg,.jpeg,.png,.pdf"></div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
                    <button class="btn btn-primary" type="submit">Simpan Pembayaran</button>
                </div>
            </form>
        </x-modal>
    @endif
@endif
@endsection
