@extends('layouts.app')

@section('title', 'Log Detail')

@section('content')
<div class="mb-4">
    <a href="{{ route('activity-logs.index') }}" class="link text-xs">← Kembali ke Activity Log</a>
</div>

<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold tracking-tight">{{ $log->action }}</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">{{ $log->description }}</p>
    </div>
    <x-status :status="$log->severity" />
</div>

<div class="mt-6 grid gap-4 xl:grid-cols-3">
    <div class="card card-pad xl:col-span-1">
        <div class="section-title">Ringkasan</div>
        <div class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Waktu</span><span class="font-semibold">{{ $log->created_at->format('d/m/Y H:i:s') }}</span></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">User</span><span class="font-semibold">{{ $log->user?->name ?: 'System' }}</span></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Role</span><span class="font-semibold">{{ $log->user?->roleLabel() ?: 'System' }}</span></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Event</span><span class="font-semibold">{{ $log->event?->name ?: '—' }}</span></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">Subject</span><span class="font-semibold">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</span></div>
            <div class="flex justify-between gap-3"><span style="color: var(--muted)">System</span><span class="font-semibold">{{ $log->is_system ? 'Ya' : 'Tidak' }}</span></div>
        </div>
    </div>

    <div class="card card-pad xl:col-span-2">
        <div class="section-title">Perubahan Data</div>
        <div class="mt-3 grid gap-4 lg:grid-cols-2">
            <div>
                <div class="text-xs font-bold" style="color: var(--muted)">Nilai Lama</div>
                <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-50 p-3 text-[11px]">{{ $log->old_values ? json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
            </div>
            <div>
                <div class="text-xs font-bold" style="color: var(--muted)">Nilai Baru</div>
                <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-50 p-3 text-[11px]">{{ $log->new_values ? json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '—' }}</pre>
            </div>
        </div>
        @if ($log->meta)
            <div class="mt-4">
                <div class="text-xs font-bold" style="color: var(--muted)">Meta</div>
                <pre class="mt-2 max-h-72 overflow-auto rounded-xl bg-slate-50 p-3 text-[11px]">{{ json_encode($log->meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</div>

@if ($showRequestDetails)
    <div class="card card-pad mt-4">
        <div class="section-title">Request & Device</div>
        <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-4">
            <div><div class="text-xs" style="color: var(--muted)">IP Address</div><div class="font-semibold">{{ $log->ip_address ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Device</div><div class="font-semibold">{{ $log->device_type ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Browser</div><div class="font-semibold">{{ $log->browser ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Platform</div><div class="font-semibold">{{ $log->platform ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Route</div><div class="font-semibold">{{ $log->route ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Method</div><div class="font-semibold">{{ $log->http_method ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Status</div><div class="font-semibold">{{ $log->status_code ?: '—' }}</div></div>
            <div><div class="text-xs" style="color: var(--muted)">Duration</div><div class="font-semibold">{{ $log->duration_ms ? $log->duration_ms.' ms' : '—' }}</div></div>
        </div>
        <div class="mt-3">
            <div class="text-xs" style="color: var(--muted)">User Agent</div>
            <div class="mt-1 break-all rounded-xl bg-slate-50 p-3 text-xs">{{ $log->user_agent ?: '—' }}</div>
        </div>
    </div>
@endif
@endsection
