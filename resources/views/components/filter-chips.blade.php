@props(['keys' => [], 'names' => []])

@php
    $active = collect(request()->query())
        ->filter(fn ($value, $key) => isset($keys[$key]) && $value !== '' && $value !== null)
        ->except(['page', 'view']);
@endphp

@if ($active->isNotEmpty())
    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs">
        <span class="font-semibold" style="color: var(--muted)">Filter aktif:</span>
        @foreach ($active as $key => $value)
            @php
                $resolved = $names[$key][$value] ?? $value;
                $resolved = is_bool($resolved) ? ($resolved ? 'Ya' : 'Tidak') : $resolved;
            @endphp
            <a href="{{ request()->fullUrlWithoutQuery($key) }}" class="badge badge-muted inline-flex items-center gap-1.5">
                <span class="opacity-70">{{ $keys[$key] }}:</span> {{ $resolved }}
                <span class="opacity-60">✕</span>
            </a>
        @endforeach
        <a href="{{ url()->current() }}" class="link font-semibold">Reset semua</a>
    </div>
@endif
