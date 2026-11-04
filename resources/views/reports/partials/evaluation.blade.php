@if (! empty($report->narrative['evaluation']) || ! empty($report->narrative['recommendations']) || ! empty($report->narrative['conclusion']))
    <h2>Evaluasi & Rekomendasi</h2>
    @if (! empty($report->narrative['evaluation']))
        <h3>Evaluasi & Lessons Learned</h3>
        <div class="note">{!! nl2br(e($report->narrative['evaluation'])) !!}</div>
    @endif
    @if (! empty($report->narrative['recommendations']))
        <h3>Rekomendasi & Action Plan</h3>
        <div class="note">{!! nl2br(e($report->narrative['recommendations'])) !!}</div>
    @endif
    @if (! empty($report->narrative['conclusion']))
        <h3>Kesimpulan</h3>
        <div class="note">{!! nl2br(e($report->narrative['conclusion'])) !!}</div>
    @endif
@endif
