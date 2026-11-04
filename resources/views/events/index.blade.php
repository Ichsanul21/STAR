@extends('layouts.app')

@section('title', 'Events')

@section('content')
@php
    $mode = request('view', $defaultView ?? 'cards');
    $canCreate = in_array(auth()->user()->role, ['super_admin', 'eo_admin', 'pm_event', 'dev'], true);
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Events</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Semua event dealer, progres, budget, dan P&L.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <div class="flex items-center gap-1 rounded-xl border p-1" style="border-color: var(--border)" title="Tampilan default tersimpan otomatis">
            <form method="POST" action="{{ route('preferences.event-view') }}">
                @csrf
                <input type="hidden" name="view" value="cards">
                @foreach (request()->only(['search', 'dealer_id', 'status', 'date_from', 'date_to']) as $key => $value)
                    @if ($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $mode === 'cards' ? 'text-white' : 'text-slate-600' }}" @if($mode === 'cards') style="background: var(--brand)" @endif>Cards</button>
            </form>
            <form method="POST" action="{{ route('preferences.event-view') }}">
                @csrf
                <input type="hidden" name="view" value="list">
                @foreach (request()->only(['search', 'dealer_id', 'status', 'date_from', 'date_to']) as $key => $value)
                    @if ($value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                @endforeach
                <button type="submit" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $mode === 'list' ? 'text-white' : 'text-slate-600' }}" @if($mode === 'list') style="background: var(--brand)" @endif>List</button>
            </form>
        </div>
        @if ($canCreate)
            <button type="button" class="btn btn-primary" data-modal-target="createEventModal">+ Buat Event</button>
        @endif
    </div>
</div>

<div class="card mb-6 p-4">
    <form method="GET" class="filter-bar">
        <input type="hidden" name="view" value="{{ $mode }}">
        <div class="filter-field is-search">
            <label class="label">Cari</label>
            <input name="search" value="{{ $search }}" class="input" placeholder="Nama event, kode, dealer…">
        </div>
        <div class="filter-field">
            <label class="label">Dealer</label>
            <select name="dealer_id" class="input">
                <option value="">Semua dealer</option>
                @foreach ($dealers as $dealer)
                    <option value="{{ $dealer->id }}" @selected(request('dealer_id') == $dealer->id)>{{ $dealer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field">
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">Semua</option>
                @foreach (['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai'] as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field is-date">
            <label class="label">Dari</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
        </div>
        <div class="filter-field is-date">
            <label class="label">Sampai</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Filter</button>
            <a href="{{ route('events.index', ['view' => $mode]) }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'dealer_id' => 'Dealer', 'date_from' => 'Dari', 'date_to' => 'Sampai']"
    :names="['dealer_id' => $dealers->pluck('name', 'id'), 'status' => ['draft' => 'Draft', 'active' => 'Aktif', 'closed' => 'Selesai']]"
/>

@if ($mode === 'list')
    <div class="space-y-3">
        @forelse ($events as $event)
            @php
                $financial = app(\App\Services\FinanceService::class)->eventFinancials($event);
                $pnl = app(\App\Services\FinanceService::class)->eventPnl($event);
                $progress = $event->progress();
            @endphp
            <a href="{{ route('events.show', $event) }}" class="card card-pad block transition hover:-translate-y-0.5">
                <div class="grid gap-4 lg:grid-cols-12 lg:items-center">
                    <div class="lg:col-span-4">
                        <div class="flex items-center gap-2">
                            <x-status :status="$event->status" />
                            <span class="text-[10px] font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $event->code }}</span>
                        </div>
                        <div class="mt-1 text-lg font-bold">{{ $event->name }}</div>
                        <div class="text-sm" style="color: var(--muted)">{{ $event->dealer?->name }}</div>
                    </div>
                    <div class="text-sm lg:col-span-2">
                        <div class="text-xs" style="color: var(--muted)">Tanggal</div>
                        <div class="font-semibold">{{ $event->start_date?->format('d/m/Y') }}</div>
                    </div>
                    <div class="text-sm lg:col-span-2">
                        <div class="text-xs" style="color: var(--muted)">Lokasi</div>
                        <div class="font-semibold">{{ $event->city ?: $event->location ?: '—' }}</div>
                    </div>
                    <div class="lg:col-span-1">
                        <div class="text-xs" style="color: var(--muted)">Progress</div>
                        <div class="font-bold">{{ $progress }}%</div>
                    </div>
                    <div class="lg:col-span-2">
                        <div class="text-xs" style="color: var(--muted)">P&L</div>
                        <div class="font-bold" style="color: {{ $pnl['gross_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}">
                            <x-money :value="$pnl['gross_profit']" />
                        </div>
                        <div class="text-[10px]" style="color: var(--muted)">Margin {{ $pnl['margin'] }}%</div>
                    </div>
                    <div class="lg:col-span-1 text-right">
                        <div class="text-xs" style="color: var(--muted)">Budget</div>
                        <div class="text-sm font-semibold">{{ $financial['absorption'] }}%</div>
                        <div class="text-[10px]" style="color: var(--muted)"><x-money :value="$financial['actual']" /> / <x-money :value="$financial['planned']" /></div>
                    </div>
                </div>
            </a>
        @empty
            <x-empty title="Belum ada event" body="Buat event pertama untuk mulai mengelola fase, task, RAB, dan dokumentasi." />
        @endforelse
    </div>
@else
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($events as $event)
            @php
                $financial = app(\App\Services\FinanceService::class)->eventFinancials($event);
                $pnl = app(\App\Services\FinanceService::class)->eventPnl($event);
                $progress = $event->progress();
            @endphp
            <a href="{{ route('events.show', $event) }}" class="card card-pad block transition hover:-translate-y-0.5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--muted)">{{ $event->code }}</div>
                        <div class="mt-1 text-lg font-bold leading-snug">{{ $event->name }}</div>
                        <div class="mt-1 text-sm" style="color: var(--muted)">{{ $event->dealer?->name }}</div>
                    </div>
                    <x-status :status="$event->status" />
                </div>
                <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><div class="text-xs" style="color: var(--muted)">Tanggal</div><div class="font-semibold">{{ $event->start_date?->format('d/m/Y') }}</div></div>
                    <div><div class="text-xs" style="color: var(--muted)">Lokasi</div><div class="font-semibold">{{ $event->city ?: $event->location ?: '—' }}</div></div>
                </div>
                <div class="mt-4"><x-progress :value="$progress" :label="'Progress event'" /></div>
                <div class="mt-4 grid grid-cols-2 gap-3 border-t pt-3 text-xs" style="border-color: var(--border)">
                    <div>
                        <div style="color: var(--muted)">P&L</div>
                        <div class="text-sm font-bold" style="color: {{ $pnl['gross_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$pnl['gross_profit']" /></div>
                        <div style="color: var(--muted)">Margin {{ $pnl['margin'] }}%</div>
                    </div>
                    <div>
                        <div style="color: var(--muted)">Budget</div>
                        <div class="text-sm font-semibold"><x-money :value="$financial['actual']" /></div>
                        <div style="color: var(--muted)">dari <x-money :value="$financial['planned']" /></div>
                    </div>
                </div>
            </a>
        @empty
            <div class="md:col-span-2 xl:col-span-3">
                <x-empty title="Belum ada event" body="Buat event pertama untuk mulai mengelola fase, task, RAB, dan dokumentasi." />
            </div>
        @endforelse
    </div>
@endif

<div class="mt-6">{{ $events->links() }}</div>

@if ($canCreate)
<x-modal id="createEventModal" title="Buat Event Baru" size="xl" :open="$errors->any() || request()->boolean('create')">
    <form method="POST" action="{{ route('events.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @csrf
        <div>
            <label class="label">Dealer</label>
            <select name="dealer_id" class="input" required>
                <option value="">Pilih dealer</option>
                @foreach ($dealers as $dealer)
                    <option value="{{ $dealer->id }}" @selected(old('dealer_id', request('dealer_id')) == $dealer->id)>{{ $dealer->name }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Nama Event</label><input name="name" value="{{ old('name') }}" class="input" required placeholder="Dealer Activation Weekend"></div>
        <div>
            <label class="label">Jenis Event</label>
            <select name="type" class="input">
                @foreach (config('star_options.event_types') as $value => $label)
                    <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Lokasi</label><input name="location" value="{{ old('location') }}" class="input" placeholder="Main Atrium Mall" data-autocomplete data-remote="{{ route('lookup.locations') }}" data-creatable></div>
        <div><label class="label">Kota</label><input name="city" value="{{ old('city') }}" class="input" placeholder="Jakarta" data-autocomplete data-remote="{{ route('lookup.cities') }}" data-creatable></div>
        <div>
            <label class="label">Capture Mode</label>
            <select name="capture_mode" class="input">
                <option value="camera_only" @selected(old('capture_mode') === 'camera_only')>Camera only (watermark)</option>
                <option value="upload_or_camera" @selected(old('capture_mode', 'upload_or_camera') === 'upload_or_camera')>Upload atau kamera</option>
            </select>
        </div>
        <div><label class="label">Tanggal Mulai</label><input type="date" name="start_date" value="{{ old('start_date') }}" class="input" required></div>
        <div><label class="label">Tanggal Selesai</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="input"></div>
        <div><label class="label">Contract Value</label><input type="number" step="0.01" name="contract_value" value="{{ old('contract_value') }}" class="input" placeholder="0"></div>
        <div><label class="label">Target Pengunjung</label><input type="number" name="target_visitors" value="{{ old('target_visitors', 0) }}" class="input" min="0"></div>
        <div><label class="label">Target Lead</label><input type="number" name="target_leads" value="{{ old('target_leads', 0) }}" class="input" min="0"></div>
        <div><label class="label">Target Unit</label><input type="number" name="target_units" value="{{ old('target_units', 0) }}" class="input" min="0"></div>
        <div class="md:col-span-2 xl:col-span-3"><label class="label">Deskripsi</label><textarea name="description" rows="3" class="input">{{ old('description') }}</textarea></div>
        <div class="flex justify-end gap-2 md:col-span-2 xl:col-span-3">
            <button type="button" class="btn btn-secondary" data-modal-close>Batal</button>
            <button class="btn btn-primary" type="submit">Buat Event</button>
        </div>
    </form>
</x-modal>
@endif
@endsection
