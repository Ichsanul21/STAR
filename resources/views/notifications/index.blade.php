@extends('layouts.app')

@section('title', 'Notifikasi')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Notifikasi</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Semua aktivitas penting event dan approval.</p>
    </div>
    <form method="POST" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="btn btn-secondary" type="submit">Tandai semua dibaca</button>
    </form>
</div>

<div class="mb-4 flex flex-wrap gap-2">
    @foreach ([
        'all' => ['Semua', 'semua'],
        'task' => ['Tugas', 'task'],
        'finance' => ['Keuangan', 'finance'],
        'report' => ['Laporan', 'report'],
    ] as $key => [$label, $value])
        <a href="{{ route('notifications.index', $value === 'semua' ? [] : ['type' => $value]) }}"
           class="rounded-xl border px-3 py-1.5 text-xs font-semibold {{ $type === ($value === 'semua' ? '' : $value) ? 'text-white' : '' }}"
           style="border-color: var(--border); {{ $type === ($value === 'semua' ? '' : $value) ? 'background: var(--brand)' : '' }}">
            {{ $label }}
            <span class="ml-1 rounded-full px-1.5 py-0.5 text-[10px]" style="background: {{ $type === ($value === 'semua' ? '' : $value) ? 'rgb(255 255 255 / 0.2)' : 'var(--brand-soft)' }}; color: {{ $type === ($value === 'semua' ? '' : $value) ? '#fff' : 'var(--brand)' }}">{{ $tabCounts[$key] }}</span>
        </a>
    @endforeach
</div>

@if ($actions->isNotEmpty())
    <div class="mb-4">
        <div class="mb-2 flex items-center justify-between">
            <div class="section-title">Perlu tindakan</div>
            <span class="badge badge-warning">{{ $actions->count() }} item</span>
        </div>
        <div class="grid gap-2">
            @foreach ($actions as $action)
                <div class="card flex flex-wrap items-center gap-3 p-3">
                    <span class="badge {{ $action['category'] === 'finance' ? 'badge-warning' : ($action['category'] === 'task' ? 'badge-brand' : 'badge-muted') }}">
                        {{ $action['category'] === 'finance' ? 'Keuangan' : ($action['category'] === 'task' ? 'Tugas' : 'Laporan') }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-bold">{{ $action['title'] }}</div>
                        <div class="truncate text-xs" style="color: var(--muted)">{{ $action['subtitle'] }}</div>
                    </div>
                    <div class="text-xs" style="color: var(--muted)">{{ $action['meta'] }}</div>
                    <a href="{{ $action['url'] }}" class="btn btn-primary btn-sm">{{ $action['action'] }}</a>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="card divide-y" style="border-color: var(--border)">
    @forelse ($notifications as $notification)
        <div class="flex items-start gap-3 p-4 sm:p-5">
            <div class="mt-1 h-2.5 w-2.5 rounded-full" style="background: {{ $notification->read_at ? 'var(--border)' : 'var(--brand)' }}"></div>
            <div class="min-w-0 flex-1">
                <div class="font-semibold {{ $notification->read_at ? 'text-slate-500' : '' }}">{{ $notification->title }}</div>
                <div class="mt-1 text-sm" style="color: var(--muted)">{{ $notification->body }}</div>
                <div class="mt-1 text-xs" style="color: var(--muted)">{{ $notification->created_at->diffForHumans() }}</div>
            </div>
            @if (! $notification->read_at)
                <form method="POST" action="{{ route('notifications.read', $notification) }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm" type="submit">Baca</button>
                </form>
            @endif
        </div>
    @empty
        @if ($actions->isEmpty())
            <div class="p-6"><x-empty title="Tidak ada notifikasi" body="Notifikasi akan muncul saat ada approval, laporan, atau perubahan status." /></div>
        @endif
    @endforelse
</div>

<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
