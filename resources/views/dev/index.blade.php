@extends('layouts.app')

@section('title', 'Developer Console')

@section('content')
@php
    $tabs = [
        'overview' => ['Ringkasan', 'audit'],
        'impersonation' => ['Masuk Sebagai', 'clients'],
        'maintenance' => ['Maintenance', 'settings'],
        'data' => ['Data', 'documents'],
        'sync' => ['Sinkronisasi', 'integrity'],
        'logs' => ['Log', 'reports'],
        'features' => ['Fitur', 'master'],
        'sql' => ['SQL', 'expenses'],
        'numbering' => ['Nomor', 'payments'],
        'watermark' => ['Watermark', 'documents'],
    ];
@endphp

<div class="dev-console-head">
    <span class="dev-console-mark"><x-icon name="terminal" class="w-5 h-5" /></span>
    <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="dev-console-title">Developer Console</h1>
            <span class="dev-console-badge">ALENKOSA</span>
        </div>
        <p class="mt-1 text-xs" style="color: var(--muted)">Akses penuh. Aktivitas developer disembunyikan dari Jejak Audit klien.</p>
    </div>
    <div class="dev-mode-card">
        <div class="text-[9px] font-bold uppercase tracking-[0.16em]" style="color: var(--muted)">Dev Mode</div>
        <div class="mt-1 text-sm font-extrabold" style="color: var(--success)">AKTIF</div>
    </div>
</div>

<div class="mt-5 grid items-start gap-5 md:grid-cols-[220px_minmax(0,1fr)]">
    <aside class="card dev-console-nav">
        <div class="dev-console-nav-links" data-dev-nav-scroll>
            @foreach ($tabs as $key => [$label, $icon])
                <a href="{{ route('dev.index', ['section' => $key]) }}" class="dev-console-link {{ $section === $key ? 'is-active' : '' }}">
                    <x-icon :name="$icon" class="w-4 h-4" />
                    <span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
        <div class="dev-console-nav-track" data-dev-nav-track hidden>
            <span class="dev-console-nav-thumb" data-dev-nav-thumb></span>
        </div>
    </aside>

    <div class="min-w-0">
        @include('dev.partials.'.$section)
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const nav = document.querySelector('[data-dev-nav-scroll]');
    const track = document.querySelector('[data-dev-nav-track]');
    const thumb = document.querySelector('[data-dev-nav-thumb]');

    if (!nav || !track || !thumb) return;

    const mobile = window.matchMedia('(max-width: 767.98px)');

    const update = () => {
        if (!mobile.matches || nav.scrollWidth <= nav.clientWidth + 1) {
            track.hidden = true;

            return;
        }

        track.hidden = false;

        const ratio = nav.clientWidth / nav.scrollWidth;
        const thumbWidth = Math.max(24, Math.round(track.clientWidth * ratio));
        const maxScroll = Math.max(1, nav.scrollWidth - nav.clientWidth);
        const maxLeft = Math.max(0, track.clientWidth - thumbWidth);
        const left = Math.round((nav.scrollLeft / maxScroll) * maxLeft);

        thumb.style.width = `${thumbWidth}px`;
        thumb.style.transform = `translateX(${left}px)`;
    };

    const active = nav.querySelector('.dev-console-link.is-active');
    if (active && mobile.matches) {
        nav.scrollLeft = Math.max(0, active.offsetLeft - 12);
    }

    nav.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    mobile.addEventListener('change', update);
    update();
});
</script>
@endsection
