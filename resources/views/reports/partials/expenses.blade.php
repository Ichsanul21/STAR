<h2>Expense</h2>
<table class="data">
    <thead><tr><th>Tanggal</th><th>Kategori</th><th>Deskripsi</th><th>Vendor</th><th class="right">Amount</th><th>Status</th></tr></thead>
    <tbody>
        @forelse ($expenses as $expense)
            <tr>
                <td>{{ $expense->expense_date?->format('d/m/Y') }}</td>
                <td>{{ $expense->category }}</td>
                <td>{{ $expense->description }}</td>
                <td>{{ $expense->vendor ?: '—' }}</td>
                <td class="right">Rp{{ number_format((float) $expense->amount, 0, ',', '.') }}</td>
                <td>{{ $expense->status }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">Belum ada expense.</td></tr>
        @endforelse
    </tbody>
</table>
