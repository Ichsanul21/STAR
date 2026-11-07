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
    <div class="flex min-w-0 items-center gap-3">
        <span class="dev-console-mark"><x-icon name="terminal" class="w-5 h-5" /></span>
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-extrabold tracking-tight">Developer Console</h1>
                <span class="dev-console-badge">ALENKOSA</span>
            </div>
            <p class="mt-1 text-xs" style="color: var(--muted)">Akses penuh. Aktivitas developer disembunyikan dari Jejak Audit klien.</p>
        </div>
    </div>
    <div class="dev-mode-card">
        <div class="text-[9px] font-bold uppercase tracking-[0.16em]" style="color: var(--muted)">Dev Mode</div>
        <div class="mt-1 text-sm font-extrabold" style="color: var(--success)">AKTIF</div>
    </div>
</div>

<div class="mt-5 grid items-start gap-5 xl:grid-cols-[220px_minmax(0,1fr)]">
    <aside class="card dev-console-nav">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('dev.index', ['section' => $key]) }}" class="dev-console-link {{ $section === $key ? 'is-active' : '' }}">
                <x-icon :name="$icon" class="w-4 h-4" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </aside>

    <div class="min-w-0">
        @include('dev.partials.'.$section)
    </div>
</div>
@endsection
