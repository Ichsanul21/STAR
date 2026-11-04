@extends('layouts.app')

@section('title', 'Pencarian')

@section('content')
<div class="mb-6">
    <h1 class="text-3xl font-extrabold tracking-tight">Pencarian Global</h1>
    <p class="mt-1 text-sm" style="color: var(--muted)">Cari lintas event, task, dealer, expense, invoice, report, dan dokumentasi.</p>
</div>

<div class="card mb-5 p-4">
    <form method="GET" action="{{ route('global.search') }}" class="filter-bar">
        <div class="filter-field is-search">
            <input name="q" value="{{ $q }}" class="input" placeholder="Minimal 2 karakter…" autofocus>
        </div>
        <div class="filter-field">
            <select name="type" class="input">
                <option value="">Semua tipe</option>
                @foreach (['events' => 'Events', 'tasks' => 'Tasks', 'dealers' => 'Dealer', 'expenses' => 'Expenses', 'invoices' => 'Invoice', 'reports' => 'Reports', 'documents' => 'Dokumentasi'] as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Cari</button>
            @if ($q !== '')
                <a href="{{ route('global.search') }}" class="btn btn-secondary">Reset</a>
            @endif
        </div>
    </form>
</div>

@if (mb_strlen($q) < 2)
    <x-empty title="Mulai mengetik" body="Masukkan minimal 2 karakter untuk mencari di seluruh modul." />
@else
    <div class="mb-4 text-sm" style="color: var(--muted)">
        {{ $total }} hasil untuk <span class="font-bold" style="color: var(--ink)">“{{ $q }}”</span>
    </div>

    <div class="space-y-5">
        @forelse ($groups as $group)
            @if (count($group['items']) > 0)
                <section class="card overflow-hidden">
                    <div class="flex items-center justify-between border-b px-5 py-3" style="border-color: var(--border)">
                        <div class="section-title">{{ $group['label'] }}</div>
                        <div class="text-xs" style="color: var(--muted)">{{ count($group['items']) }} hasil</div>
                    </div>
                    <div class="divide-y" style="border-color: var(--border)">
                        @foreach ($group['items'] as $item)
                            <a href="{{ $item['url'] }}" class="flex flex-wrap items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">{{ $item['title'] }}</div>
                                    <div class="truncate text-xs" style="color: var(--muted)">{{ $item['subtitle'] }}</div>
                                </div>
                                <div class="text-xs" style="color: var(--muted)">{{ $item['meta'] }}</div>
                                @if (! empty($item['status']))
                                    <x-status :status="$item['status']" />
                                @endif
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @empty
            <x-empty title="Tidak ada hasil" body="Coba kata kunci lain atau ganti tipe pencarian." />
        @endforelse
    </div>
@endif
@endsection
