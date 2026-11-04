@extends('layouts.app')

@section('title', 'Konflik Data')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Konflik Data</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Riwayat bentrok versi data (optimistic locking) beserta detail perubahannya.</p>
    </div>
    <a href="{{ route('offline.index') }}" class="btn btn-secondary">Offline Queue</a>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search">
            <label class="label">Cari</label>
            <input name="search" value="{{ $search }}" class="input" placeholder="Model, user, atau URL…">
        </div>
        <div class="filter-field">
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">Semua</option>
                <option value="open" @selected($status === 'open')>Belum selesai</option>
                <option value="resolved" @selected($status === 'resolved')>Selesai</option>
            </select>
        </div>
        <div class="filter-field">
            <label class="label">Tipe</label>
            <select name="type" class="input">
                <option value="">Semua</option>
                @foreach ($types as $option)
                    <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a href="{{ route('conflicts.index') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'type' => 'Tipe']"
    :names="['status' => ['open' => 'Belum selesai', 'resolved' => 'Selesai']]"
/>

<div class="card overflow-hidden">
    <div class="flex flex-wrap gap-2 border-b p-3" style="border-color: var(--border)">
        <input data-table-filter="conflictsTable" class="input max-w-xs" placeholder="Cari di tabel…">
    </div>
    <div class="overflow-x-auto">
        <table class="table" id="conflictsTable">
            <thead><tr><th>Waktu</th><th>Data</th><th>User</th><th>Versi</th><th>Halaman</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse ($conflicts as $conflict)
                    <tr data-search="{{ strtolower($conflict->modelLabel().' '.$conflict->model_id.' '.($conflict->user?->name ?? '').' '.$conflict->event?->name.' '.$conflict->url) }}">
                        <td class="whitespace-nowrap text-xs">{{ $conflict->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="text-xs">
                            <div class="font-semibold">{{ $conflict->modelLabel() }} #{{ $conflict->model_id ?: '—' }}</div>
                            <div style="color: var(--muted)">{{ $conflict->event?->name ?: '—' }}</div>
                        </td>
                        <td class="text-xs">
                            <div class="font-semibold">{{ $conflict->user?->name ?: 'System' }}</div>
                            <div style="color: var(--muted)">{{ $conflict->user?->roleLabel() }}</div>
                        </td>
                        <td class="text-xs">
                            <span class="badge badge-warning">dikirim v{{ $conflict->expected_version ?? '—' }}</span>
                            <span class="badge badge-muted">server v{{ $conflict->actual_version ?? '—' }}</span>
                        </td>
                        <td class="max-w-xs truncate text-xs" style="color: var(--muted)" title="{{ $conflict->url }}">{{ $conflict->route ?: $conflict->url }}</td>
                        <td>
                            @if ($conflict->isResolved())
                                <span class="badge badge-success">Selesai</span>
                            @else
                                <span class="badge badge-danger">Terbuka</span>
                            @endif
                        </td>
                        <td><a href="{{ route('conflicts.show', $conflict) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-sm" style="color: var(--muted)">Tidak ada konflik tercatat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $conflicts->links() }}</div>
@endsection
