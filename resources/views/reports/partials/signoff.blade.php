<h2>Sign-off</h2>
<table class="sign">
    <tr>
        <td>Disiapkan oleh<br><br><strong>EO / PM Event</strong></td>
        <td>Direview oleh<br><br><strong>Reviewer</strong></td>
        <td>Diterima dealer<br><br><strong>{{ $event->dealer?->name }}</strong></td>
    </tr>
</table>

<div class="footer">Disiapkan oleh STAR — Supported by Alenkosa · {{ $event->code }} · {{ $report->report_number ?: 'Laporan v'.$report->version }}</div>
