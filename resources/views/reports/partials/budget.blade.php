<h2>RAB vs Realisasi</h2>
<table class="data">
    <thead><tr><th>Kategori</th><th>Uraian</th><th class="right">Rencana</th><th class="right">Realisasi</th><th class="right">Variance</th></tr></thead>
    <tbody>
        @forelse ($budgetItems as $item)
            <tr>
                <td>{{ $item->category }}</td>
                <td>{{ $item->description }}</td>
                <td class="right">Rp{{ number_format((float) $item->planned_amount, 0, ',', '.') }}</td>
                <td class="right">Rp{{ number_format((float) $item->actual_amount, 0, ',', '.') }}</td>
                <td class="right">Rp{{ number_format((float) $item->actual_amount - (float) $item->planned_amount, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">Belum ada item RAB.</td></tr>
        @endforelse
    </tbody>
</table>
