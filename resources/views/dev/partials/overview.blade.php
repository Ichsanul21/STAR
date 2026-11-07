@php
    $maxCount = max(1, collect($counts)->max());
@endphp

<div class="mb-3">
    <h2 class="text-xl font-extrabold">Ringkasan</h2>
    <p class="text-sm" style="color: var(--muted)">Status environment, data, dan akses cepat developer.</p>
</div>

<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($environment as $key => $value)
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">{{ str_replace('_', ' ', $key) }}</div>
            <div class="mt-1 truncate text-sm font-bold" title="{{ $value }}">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    @foreach ($counts as $label => $total)
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">{{ str_replace('_', ' ', $label) }}</div>
            <div class="mt-1 flex items-end justify-between gap-2">
                <span class="text-2xl font-extrabold">{{ number_format($total) }}</span>
                <span class="text-[10px] font-bold" style="color: var(--muted)">{{ round($total / $maxCount * 100) }}%</span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full" style="background: var(--brand-soft)">
                <span class="block h-full rounded-full" style="width: {{ max(3, round($total / $maxCount * 100)) }}%; background: var(--brand)"></span>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <a href="{{ route('dev.index', ['section' => 'watermark']) }}" class="dev-quick-action">
        <x-icon name="documents" class="w-5 h-5" />
        <span><b>Watermark</b><small>Atur watermark UI & PDF</small></span>
    </a>
    <a href="{{ route('dev.index', ['section' => 'numbering']) }}" class="dev-quick-action">
        <x-icon name="payments" class="w-5 h-5" />
        <span><b>Nomor</b><small>Format penomoran otomatis</small></span>
    </a>
    <a href="{{ route('dev.index', ['section' => 'logs']) }}" class="dev-quick-action">
        <x-icon name="reports" class="w-5 h-5" />
        <span><b>Log</b><small>Log aplikasi & failed jobs</small></span>
    </a>
    <a href="{{ route('dev.index', ['section' => 'sql']) }}" class="dev-quick-action">
        <x-icon name="expenses" class="w-5 h-5" />
        <span><b>SQL</b><small>Query read-only</small></span>
    </a>
</div>
