<h2>Hasil Event</h2>
<table class="data">
    <tbody>
        <tr><td>Pengunjung</td><td class="right">{{ $event->result?->visitors ?? $event->visitors->count() }}</td></tr>
        <tr><td>Leads</td><td class="right">{{ $event->result?->leads ?? $event->visitors->count() }}</td></tr>
        <tr><td>Test Drive</td><td class="right">{{ $event->result?->test_drives ?? 0 }}</td></tr>
        <tr><td>SPK</td><td class="right">{{ $event->result?->spk ?? 0 }}</td></tr>
        <tr><td>Unit Terjual</td><td class="right">{{ $event->result?->units_sold ?? 0 }}</td></tr>
        <tr><td>Revenue</td><td class="right">Rp{{ number_format((float) ($event->result?->revenue ?? 0), 0, ',', '.') }}</td></tr>
        <tr><td>ROI</td><td class="right">{{ (float) ($event->result?->roi ?? 0) }}%</td></tr>
    </tbody>
</table>
