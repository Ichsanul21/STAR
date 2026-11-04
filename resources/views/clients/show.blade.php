@extends('layouts.app')

@section('title', $dealer->name)

@section('content')
<div class="mb-4"><a href="{{ route('clients.index') }}" class="link text-xs">← Kembali ke Clients</a></div>

<div class="card card-pad">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $dealer->code }}</div>
            <h1 class="mt-1 text-3xl font-extrabold tracking-tight">{{ $dealer->name }}</h1>
            <div class="mt-1 text-sm" style="color: var(--muted)">{{ $dealer->city ?: '—' }} · {{ $dealer->address ?: 'Alamat belum diisi' }}</div>
            <div class="mt-1 text-sm" style="color: var(--muted)">PIC: {{ $dealer->pic_name ?: '—' }} · {{ $dealer->phone ?: '—' }}</div>
        </div>
        <div class="flex gap-2">
            @if (auth()->user()->isAdmin())
                <button type="button" class="btn btn-secondary btn-sm" data-modal-target="editClientModal">Edit Profil</button>
            @endif
            <a href="{{ route('events.index', ['dealer_id' => $dealer->id, 'create' => 1]) }}" class="btn btn-primary btn-sm">+ Buat Event</a>
        </div>
    </div>
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <div class="card card-pad"><div class="text-xs" style="color: var(--muted)">Contract</div><div class="mt-1 text-xl font-extrabold"><x-money :value="$totals['contract']" /></div></div>
    <div class="card card-pad"><div class="text-xs" style="color: var(--muted)">Invoiced</div><div class="mt-1 text-xl font-extrabold"><x-money :value="$totals['invoiced']" /></div></div>
    <div class="card card-pad"><div class="text-xs" style="color: var(--muted)">Received</div><div class="mt-1 text-xl font-extrabold"><x-money :value="$totals['received']" /></div></div>
    <div class="card card-pad"><div class="text-xs" style="color: var(--muted)">Outstanding</div><div class="mt-1 text-xl font-extrabold" style="color: var(--danger)"><x-money :value="$totals['outstanding']" /></div></div>
    <div class="card card-pad"><div class="text-xs" style="color: var(--muted)">P&L</div><div class="mt-1 text-xl font-extrabold" style="color: {{ $totals['profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$totals['profit']" /></div></div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--border)">
        <div class="section-title">Riwayat Event</div>
        <div class="flex flex-wrap items-center gap-2">
            <input data-table-filter="clientEventsTable" class="input max-w-xs !py-1.5 text-xs" placeholder="Cari event…">
            <select data-table-filter-status="clientEventsTable" class="input !w-auto !py-1.5 text-xs">
                <option value="">Semua status</option>
                @foreach (['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai'] as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <div class="text-xs" style="color: var(--muted)">{{ $dealer->events->count() }} event</div>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="table" id="clientEventsTable">
            <thead><tr><th>Event</th><th>Tanggal</th><th>Status</th><th>Progress</th><th>Contract</th><th>Cost</th><th>P&L</th><th></th></tr></thead>
            <tbody>
                @forelse ($dealer->events as $event)
                    @php $pnl = $financials[$event->id]; @endphp
                    <tr data-search="{{ strtolower($event->name.' '.$event->code) }}" data-status="{{ $event->status }}">
                        <td><div class="font-semibold">{{ $event->name }}</div><div class="text-xs" style="color: var(--muted)">{{ $event->code }}</div></td>
                        <td class="whitespace-nowrap">{{ $event->start_date?->format('d/m/Y') }}</td>
                        <td><x-status :status="$event->status" /></td>
                        <td class="min-w-28"><x-progress :value="$event->progress()" :label="$event->progress().'%'" /></td>
                        <td><x-money :value="$pnl['contract']" /></td>
                        <td><x-money :value="$pnl['cost']" /></td>
                        <td class="font-bold" style="color: {{ $pnl['gross_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$pnl['gross_profit']" /></td>
                        <td><a href="{{ route('events.show', $event) }}" class="btn btn-secondary btn-sm">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada event.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if (auth()->user()->isAdmin())
<x-modal id="editClientModal" title="Edit Profil Dealer" size="lg">
    <form method="POST" action="{{ route('clients.update', $dealer) }}" class="grid gap-3 md:grid-cols-2">
        @csrf @method('PATCH')
        <div><label class="label">Nama</label><input name="name" value="{{ $dealer->name }}" class="input" required></div>
        <div><label class="label">Kota</label><input name="city" value="{{ $dealer->city }}" class="input" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
        <div class="md:col-span-2"><label class="label">Alamat</label><input name="address" value="{{ $dealer->address }}" class="input"></div>
        <div><label class="label">Telepon</label><input name="phone" value="{{ $dealer->phone }}" class="input"></div>
        <div><label class="label">PIC</label><input name="pic_name" value="{{ $dealer->pic_name }}" class="input"></div>
        <div class="md:col-span-2 flex justify-end gap-2"><button type="button" class="btn btn-secondary" data-modal-close>Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
</x-modal>
@endif
@endsection
