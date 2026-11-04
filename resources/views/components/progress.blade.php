@props(['value' => 0, 'label' => null])

<div>
    @if ($label)
        <div class="mb-1 flex items-center justify-between text-xs font-semibold" style="color: var(--muted)">
            <span>{{ $label }}</span>
            <span>{{ number_format((float) $value, 1) }}%</span>
        </div>
    @endif
    <div class="progress-track">
        <div class="progress-bar" style="width: {{ max(0, min(100, (float) $value)) }}%"></div>
    </div>
</div>
