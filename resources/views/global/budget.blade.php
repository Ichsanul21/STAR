@extends('layouts.app')

@section('title', 'Budget')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Budget</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">RAB, realisasi, dan serapan budget semua event.</p>
    </div>
    <a href="{{ route('global.export', ['type' => 'budget'] + request()->query()) }}" class="btn btn-secondary">Export CSV</a>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search"><label class="label">Cari</label><input name="search" value="{{ request('search') }}" class="input" placeholder="Nama event / kode…"></div>
        <div class="filter-field"><label class="label">Dealer</label><select name="dealer_id" class="input"><option value="">Semua dealer</option>@foreach ($dealers as $dealer)<option value="{{ $dealer->id }}" @selected(request('dealer_id') == $dealer->id)>{{ $dealer->name }}</option>@endforeach</select></div>
        <div class="filter-field"><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('global.budget') }}" class="btn btn-secondary">Reset</a></div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'dealer_id' => 'Dealer']"
    :names="['dealer_id' => $dealers->pluck('name', 'id'), 'status' => ['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai']]"
/>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Event</th><th>Dealer</th><th class="text-right">Rencana</th><th class="text-right">Realisasi</th><th>Serapan</th><th>Outstanding</th><th>P&L</th><th></th></tr></thead>
            <tbody>
                @forelse ($events as $event)
                    @php $f = $financials[$event->id] ?? []; $p = $pnls[$event->id] ?? []; @endphp
                    <tr>
                        <td>
                            <a href="{{ route('events.tab', [$event, 'finance']) }}" class="font-semibold hover:underline">{{ $event->name }}</a>
                            <div class="text-xs" style="color: var(--muted)">{{ $event->code }}</div>
                        </td>
                        <td>{{ $event->dealer?->name }}</td>
                        <td class="text-right"><x-money :value="$f['planned'] ?? 0" /></td>
                        <td class="text-right"><x-money :value="$f['actual'] ?? 0" /></td>
                        <td class="min-w-32"><x-progress :value="$f['absorption'] ?? 0" :label="($f['absorption'] ?? 0).'%'" /></td>
                        <td><x-money :value="$f['outstanding'] ?? 0" /></td>
                        <td class="font-semibold" style="color: {{ ($p['gross_profit'] ?? 0) >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$p['gross_profit'] ?? 0" /></td>
                        <td><a href="{{ route('events.tab', [$event, 'finance']) }}" class="btn btn-secondary btn-sm">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-sm" style="color: var(--muted)">Belum ada event.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $events->links() }}</div>
@endsection
