@extends('layouts.app')

@section('title', 'Clients')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Clients / Dealer</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Profil dealer, riwayat event, dan ringkasan finansial.</p>
    </div>
    <a href="{{ route('global.export', ['type' => 'clients']) }}" class="btn btn-secondary">Export CSV</a>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search">
            <label class="label">Cari</label>
            <input name="search" value="{{ $search }}" class="input" placeholder="Nama dealer, kode, PIC…">
        </div>
        <div class="filter-field">
            <label class="label">Kota</label>
            <select name="city" class="input">
                <option value="">Semua kota</option>
                @foreach ($cities as $option)
                    <option value="{{ $option }}" @selected($city === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a href="{{ route('clients.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'city' => 'Kota']"
/>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($dealers as $dealer)
        @php $s = $summaries[$dealer->id] ?? []; @endphp
        <a href="{{ route('clients.show', $dealer) }}" class="card card-pad block transition hover:-translate-y-0.5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $dealer->code }}</div>
                    <div class="mt-1 text-lg font-bold">{{ $dealer->name }}</div>
                    <div class="text-sm" style="color: var(--muted)">{{ $dealer->city ?: '—' }} · {{ $dealer->pic_name ?: 'PIC belum diisi' }}</div>
                </div>
                <span class="badge badge-brand">{{ $dealer->events->count() }} event</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-3 border-t pt-3 text-xs" style="border-color: var(--border)">
                <div><div style="color: var(--muted)">Contract</div><div class="text-sm font-semibold"><x-money :value="$s['contract'] ?? 0" /></div></div>
                <div><div style="color: var(--muted)">P&L</div><div class="text-sm font-bold" style="color: {{ ($s['profit'] ?? 0) >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$s['profit'] ?? 0" /></div></div>
                <div><div style="color: var(--muted)">Invoiced</div><div class="text-sm font-semibold"><x-money :value="$s['invoiced'] ?? 0" /></div></div>
                <div><div style="color: var(--muted)">Outstanding</div><div class="text-sm font-semibold"><x-money :value="max(0, ($s['invoiced'] ?? 0) - ($s['received'] ?? 0))" /></div></div>
            </div>
        </a>
    @empty
        <div class="md:col-span-2 xl:col-span-3"><x-empty title="Belum ada dealer" body="Tambahkan dealer terlebih dahulu." /></div>
    @endforelse
</div>

<div class="mt-4">{{ $dealers->links() }}</div>
@endsection
