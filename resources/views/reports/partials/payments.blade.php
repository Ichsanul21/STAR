<h2>Client Payment</h2>
<table class="data">
    <thead><tr><th>Invoice</th><th>Termin</th><th class="right">Amount</th><th class="right">Paid</th><th class="right">Sisa</th><th>Status</th></tr></thead>
    <tbody>
        @forelse ($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->term }}</td>
                <td class="right">Rp{{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                <td class="right">Rp{{ number_format($invoice->paidAmount(), 0, ',', '.') }}</td>
                <td class="right">Rp{{ number_format($invoice->balance(), 0, ',', '.') }}</td>
                <td>{{ $invoice->derivedStatus() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Belum ada invoice.</td></tr>
        @endforelse
    </tbody>
</table>
