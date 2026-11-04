@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">Reports</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Library laporan semua event.</p>
    </div>
    <a href="{{ route('global.export', ['type' => 'reports'] + request()->query()) }}" class="btn btn-secondary">Export CSV</a>
</div>

<div class="card mb-4 p-4">
    <form method="GET" class="filter-bar">
        <div class="filter-field is-search"><label class="label">Cari</label><input name="search" value="{{ request('search') }}" class="input" placeholder="Nama event…"></div>
        <div class="filter-field"><label class="label">Event</label><select name="event_id" class="input"><option value="">Semua</option>@foreach ($events as $event)<option value="{{ $event->id }}" @selected(request('event_id') == $event->id)>{{ $event->name }}</option>@endforeach</select></div>
        <div class="filter-field"><label class="label">Status</label><select name="status" class="input"><option value="">Semua</option>@foreach (['draft' => 'Draft', 'published' => 'Published'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Filter</button><a href="{{ route('global.reports') }}" class="btn btn-secondary">Reset</a></div>
    </form>
</div>

<x-filter-chips
    :keys="['search' => 'Cari', 'status' => 'Status', 'event_id' => 'Event']"
    :names="['event_id' => $events->pluck('name', 'id'), 'status' => ['draft' => 'Draft', 'published' => 'Published']]"
/>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="table">
            <thead><tr><th>Event</th><th>Versi</th><th>Status</th><th>Dibuat</th><th>Published</th><th></th></tr></thead>
            <tbody>
                @forelse ($reports as $report)
                    <tr>
                        <td>
                            <a href="{{ route('events.tab', [$report->event, 'reports']) }}" class="font-semibold hover:underline">{{ $report->event?->name }}</a>
                            <div class="text-xs" style="color: var(--muted)">{{ $report->event?->code }}</div>
                        </td>
                        <td>v{{ $report->version }}</td>
                        <td><x-status :status="$report->status" /></td>
                        <td class="whitespace-nowrap text-xs">{{ $report->created_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap text-xs">{{ $report->published_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td>
                            <div class="flex gap-1">
                                <a href="{{ route('reports.preview', $report) }}" class="btn btn-secondary btn-sm" data-preview="html" data-preview-title="Preview {{ $report->event?->name }} v{{ $report->version }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::report($report)) }}">Preview</a>
                                @if ($report->pdf_path)<a href="{{ route('reports.pdf', $report) }}" class="btn btn-ghost btn-sm" data-preview="pdf" data-preview-title="PDF {{ $report->event?->name }} v{{ $report->version }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::report($report)) }}">PDF</a>@endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-sm" style="color: var(--muted)">Belum ada laporan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $reports->links() }}</div>
@endsection
