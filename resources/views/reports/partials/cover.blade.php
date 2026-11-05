<div class="cover">
    <div class="brand">STAR — SUPPORTED BY ALENKOSA</div>
    <h1>{{ $event->name }}</h1>
    <div class="dealer">{{ $event->dealer?->name }}</div>

    <table class="meta">
        <tr><td>Kode Event</td><td>{{ $event->code }}</td></tr>
        @if ($report->report_number)
            <tr><td>No. Laporan</td><td>{{ $report->report_number }}</td></tr>
        @endif
        <tr><td>Lokasi</td><td>{{ $event->location ?: '—' }}{{ $event->city ? ' · '.$event->city : '' }}</td></tr>
        <tr><td>Tanggal</td><td>{{ $event->start_date?->format('d/m/Y') }}@if($event->end_date) – {{ $event->end_date->format('d/m/Y') }}@endif</td></tr>
        <tr><td>Versi Laporan</td><td>v{{ $report->version }} · {{ strtoupper($report->status) }}</td></tr>
        <tr><td>Dibuat</td><td>{{ $report->created_at->format('d/m/Y H:i') }} WIB</td></tr>
    </table>
</div>
