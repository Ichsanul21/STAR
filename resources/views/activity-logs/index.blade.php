@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Activity Log</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Audit trail aktivitas user, event, dan sistem.</p>
    </div>
    @if ($showRequestDetails)
        <span class="badge badge-brand">Super Admin · IP & device visible</span>
    @endif
</div>

<div class="card mb-4 p-4">
    <form method="GET">
        <div class="filter-row">
            <div class="filter-field is-search">
                <label class="label">Cari</label>
                <input name="search" value="{{ request('search') }}" class="input" placeholder="Aksi, deskripsi, user…">
            </div>
            <div class="filter-field">
                <label class="label">User</label>
                <select name="user_id" class="input">
                    <option value="">Semua</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label class="label">Event</label>
                <select name="event_id" class="input">
                    <option value="">Semua</option>
                    @foreach ($events as $event)
                        <option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label class="label">Severity</label>
                <select name="severity" class="input">
                    <option value="">Semua</option>
                    @foreach (['info' => 'Info', 'warning' => 'Warning', 'danger' => 'Danger'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('severity') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="filter-field">
                <label class="label">Aksi</label>
                <select name="action" class="input">
                    <option value="">Semua aksi</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="filter-row">
            <div class="filter-field is-date">
                <label class="label">Dari</label>
                <input type="date" name="from" value="{{ request('from') }}" class="input">
            </div>
            <div class="filter-field is-date">
                <label class="label">Sampai</label>
                <input type="date" name="to" value="{{ request('to') }}" class="input">
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a href="{{ route('activity-logs.index') }}" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="border-b p-3" style="border-color: var(--border)">
        <input data-table-filter="activityLogsTable" class="input max-w-xs" placeholder="Cari aksi / user / deskripsi…">
    </div>
    <div class="overflow-x-auto">
        <table class="table" id="activityLogsTable">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>User</th>
                    <th>Aksi</th>
                    <th>Event</th>
                    <th>Deskripsi</th>
                    <th>Severity</th>
                    @if ($showRequestDetails)
                        <th>IP / Device</th>
                    @endif
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr data-search="{{ strtolower($log->action.' '.($log->user?->name ?? 'system').' '.($log->description ?? '').' '.($log->event?->name ?? '')) }}" data-status="{{ $log->severity }}">
                        <td class="whitespace-nowrap text-xs">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="text-xs">
                            <div class="font-semibold">{{ $log->user?->name ?: 'System' }}</div>
                            <div style="color: var(--muted)">{{ $log->user?->roleLabel() }}</div>
                        </td>
                        <td><span class="badge badge-muted">{{ $log->action }}</span></td>
                        <td class="text-xs">{{ $log->event?->name ?: '—' }}</td>
                        <td class="max-w-md text-xs">{{ $log->description }}</td>
                        <td><x-status :status="$log->severity" /></td>
                        @if ($showRequestDetails)
                            <td class="text-xs">
                                <div class="font-semibold">{{ $log->ip_address ?: '—' }}</div>
                                <div style="color: var(--muted)">{{ $log->device_type ?: '—' }} · {{ $log->browser ?: '—' }} · {{ $log->platform ?: '—' }}</div>
                            </td>
                        @endif
                        <td><a href="{{ route('activity-logs.show', $log) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $showRequestDetails ? 8 : 7 }}" class="text-center text-sm" style="color: var(--muted)">Belum ada log.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
