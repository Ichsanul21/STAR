@php
    $invoiced = (float) $event->invoices->sum('amount');
    $paid = (float) $event->invoices->sum(fn ($invoice) => $invoice->paidAmount());
    $outstanding = max(0, $invoiced - $paid);
    $contract = (float) ($event->contract_value ?? $event->finalBudget?->total_planned ?? $event->planningBudget?->total_planned ?? 0);
    $remaining = max(0, $contract - $invoiced);
@endphp

<section id="payments" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Client Payments</h2>
            <p class="text-sm" style="color: var(--muted)">Invoice, termin, pembayaran masuk, dan outstanding.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-muted">Contract <x-money :value="$contract" /></span>
            <span class="badge badge-muted">Invoiced <x-money :value="$invoiced" /></span>
            <span class="badge badge-success">Paid <x-money :value="$paid" /></span>
            <span class="badge badge-danger">Outstanding <x-money :value="$outstanding" /></span>
            @if (auth()->user()->canFinanceEvent($event))
                <button type="button" class="btn btn-primary btn-sm" data-modal-target="createInvoiceModal">+ Buat Invoice</button>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex flex-wrap gap-2 border-b p-3" style="border-color: var(--border)">
            <input data-table-filter="invoicesTable" class="input max-w-xs" placeholder="Cari invoice…">
            <select data-table-filter-status="invoicesTable" class="input !w-auto">
                <option value="">Semua status</option>
                @foreach (['unpaid' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="overflow-x-auto">
            <table class="table" id="invoicesTable">
                <thead><tr><th>Invoice</th><th>Termin</th><th>Amount</th><th>Paid</th><th>Sisa</th><th>Due</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($event->invoices->sortByDesc('created_at') as $invoice)
                        <tr data-search="{{ strtolower($invoice->invoice_number.' '.$invoice->term.' '.$invoice->description) }}" data-status="{{ $invoice->derivedStatus() }}">
                            <td class="font-semibold">{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->term }}</td>
                            <td><x-money :value="$invoice->amount" /></td>
                            <td><x-money :value="$invoice->paidAmount()" /></td>
                            <td><x-money :value="$invoice->balance()" /></td>
                            <td class="whitespace-nowrap">{{ $invoice->due_date?->format('d/m/Y') ?: '—' }}</td>
                            <td><x-status :status="$invoice->derivedStatus()" /></td>
                            <td>
                                @if (auth()->user()->canFinanceEvent($event) && $invoice->balance() > 0)
                                    <button type="button" class="btn btn-secondary btn-sm" data-modal-target="paymentInvoice{{ $invoice->id }}">Catat Bayar</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (auth()->user()->canFinanceEvent($event))
        <x-modal id="createInvoiceModal" title="Buat Invoice Client" size="lg">
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase" style="color: var(--muted)">Contract</div><div class="font-bold"><x-money :value="$contract" /></div></div>
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase" style="color: var(--muted)">Invoiced</div><div class="font-bold"><x-money :value="$invoiced" /></div></div>
                <div class="rounded-xl bg-slate-50 p-3"><div class="text-[10px] font-bold uppercase" style="color: var(--muted)">Sisa</div><div class="font-bold"><x-money :value="$remaining" /></div></div>
            </div>
            <form method="POST" action="{{ route('invoices.store', $event) }}" class="grid gap-3 md:grid-cols-3">
                @csrf
                <div><label class="label">No. Invoice</label><input name="invoice_number" class="input" required placeholder="INV-{{ $event->code }}-01"></div>
                <div><label class="label">Termin</label>
                    <select name="term" class="input" required data-creatable>
                        <option value="">Pilih termin…</option>
                        @foreach (config('star_options.invoice_terms') as $value => $label)
                            <option value="{{ $value }}" @selected(old('term') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div><label class="label">Amount</label><input type="number" step="0.01" name="amount" class="input" required value="{{ old('amount', $remaining) }}"></div>
                <div class="md:col-span-2"><label class="label">Deskripsi</label><input name="description" class="input" placeholder="Pembayaran 50% event"></div>
                <div><label class="label">Due Date</label><input type="date" name="due_date" class="input"></div>
                <div class="md:col-span-3 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary" type="submit">Buat Invoice</button></div>
            </form>
        </x-modal>

        @foreach ($event->invoices as $invoice)
            @if ($invoice->balance() > 0)
                <x-modal id="paymentInvoice{{ $invoice->id }}" title="Catat Pembayaran {{ $invoice->invoice_number }}" size="lg">
                    <form method="POST" action="{{ route('payments.store', $invoice) }}" enctype="multipart/form-data" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <div><label class="label">Amount</label><input type="number" step="0.01" name="amount" class="input" required value="{{ $invoice->balance() }}"></div>
                        <div><label class="label">Tanggal</label><input type="datetime-local" name="paid_at" class="input" required value="{{ now()->format('Y-m-d\TH:i') }}"></div>
                        <div><label class="label">Metode</label><select name="method" class="input"><option value="transfer">Transfer</option><option value="cash">Cash</option><option value="cheque">Cheque</option></select></div>
                        <div><label class="label">Bukti</label><input type="file" name="proof" class="input" accept=".jpg,.jpeg,.png,.pdf"></div>
                        <div class="sm:col-span-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary" type="submit">Simpan Pembayaran</button></div>
                    </form>
                </x-modal>
            @endif
        @endforeach
    @endif
</section>
