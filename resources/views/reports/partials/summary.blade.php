<h2>Ringkasan Eksekutif</h2>
<table class="kpi">
    <tr>
        <td><div class="label">Progress Event</div><div class="value">{{ $snapshot['progress'] }}%</div></td>
        <td><div class="label">Pengunjung</div><div class="value">{{ $snapshot['visitors'] }}</div></td>
        <td><div class="label">Serapan Budget</div><div class="value">{{ $finance['absorption'] }}%</div></td>
        <td><div class="label">Outstanding Client</div><div class="value">Rp{{ number_format($finance['outstanding'], 0, ',', '.') }}</div></td>
    </tr>
</table>

<div class="note">
    <strong>Budget:</strong> rencana Rp{{ number_format($finance['planned'], 0, ',', '.') }} ·
    realisasi Rp{{ number_format($finance['actual'], 0, ',', '.') }} ·
    variance Rp{{ number_format($finance['variance'], 0, ',', '.') }}.
    <br>
    <strong>Task:</strong> {{ $snapshot['tasks']['done'] }}/{{ $snapshot['tasks']['total'] }} selesai,
    {{ $snapshot['tasks']['overdue'] }} overdue.
</div>

@if (! empty($report->narrative['summary']))
    <h3>Ringkasan Naratif</h3>
    <div class="note">{!! nl2br(e($report->narrative['summary'])) !!}</div>
@endif
