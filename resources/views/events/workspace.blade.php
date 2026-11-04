@extends('layouts.app')

@section('title', $event->name)

@section('content')
@php
    $pnl = app(\App\Services\FinanceService::class)->eventPnl($event);
    $tabs = [
        'overview' => 'Overview',
        'plan' => 'Plan',
        'tasks' => 'Tasks',
        'finance' => 'Finance',
        'documentation' => 'Documentation',
        'reports' => 'Reports',
        'team' => 'Team',
    ];
@endphp

<div class="mb-4 flex flex-wrap items-center gap-2 text-xs" style="color: var(--muted)">
    <a href="{{ route('events.index') }}" class="hover:underline">Events</a>
    <span>/</span>
    <span class="font-semibold" style="color: var(--ink)">{{ $event->code }}</span>
</div>

<div class="card overflow-hidden">
    <div class="p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status :status="$event->status" />
                    <span class="badge badge-muted">{{ $event->type }}</span>
                    <span class="badge badge-brand">{{ $event->capture_mode === 'camera_only' ? 'Camera only' : 'Upload / Camera' }}</span>
                </div>
                <h1 class="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $event->name }}</h1>
                <div class="mt-1 text-sm" style="color: var(--muted)">
                    {{ $event->dealer?->name }} · {{ $event->location ?: 'Lokasi belum diisi' }} · {{ $event->start_date?->format('d M Y') }}@if($event->end_date) – {{ $event->end_date->format('d M Y') }}@endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if (auth()->user()->canManageEvent($event))
                    <form method="POST" action="{{ route('reports.generate', $event) }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">Generate Laporan</button>
                    </form>
                @endif
                @if ($event->latestReport())
                    <a href="{{ route('reports.pdf', $event->latestReport()) }}" class="btn btn-secondary" data-preview="pdf" data-preview-title="PDF terbaru {{ $event->name }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::report($event->latestReport())) }}">PDF terbaru</a>
                @endif
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl p-4" style="background: var(--brand-soft)">
                <div class="text-xs font-semibold" style="color: var(--muted)">Progress Event</div>
                <div class="mt-1 text-2xl font-extrabold" style="color: var(--brand)">{{ $event->progress() }}%</div>
                <div class="mt-2"><x-progress :value="$event->progress()" /></div>
            </div>
            <div class="rounded-2xl border p-4" style="border-color: var(--border)">
                <div class="text-xs font-semibold" style="color: var(--muted)">RAB Rencana</div>
                <div class="mt-1 text-xl font-extrabold"><x-money :value="$financials['planned']" /></div>
                <div class="mt-1 text-xs" style="color: var(--muted)">{{ $budget?->stage === 'final' ? 'Final RAB' : 'Planning RAB' }}</div>
            </div>
            <div class="rounded-2xl border p-4" style="border-color: var(--border)">
                <div class="text-xs font-semibold" style="color: var(--muted)">Realisasi</div>
                <div class="mt-1 text-xl font-extrabold"><x-money :value="$financials['actual']" /></div>
                <div class="mt-1 text-xs" style="color: var(--muted)">Serapan {{ $financials['absorption'] }}%</div>
            </div>
            <div class="rounded-2xl border p-4" style="border-color: var(--border)">
                <div class="text-xs font-semibold" style="color: var(--muted)">Outstanding Client</div>
                <div class="mt-1 text-xl font-extrabold" style="color: {{ $financials['outstanding'] > 0 ? 'var(--danger)' : 'var(--success)' }}"><x-money :value="$financials['outstanding']" /></div>
                <div class="mt-1 text-xs" style="color: var(--muted)">Dari <x-money :value="$financials['invoiced']" /> invoice</div>
            </div>
            <div class="rounded-2xl border p-4" style="border-color: var(--border)">
                <div class="text-xs font-semibold" style="color: var(--muted)">P&L Event</div>
                <div class="mt-1 text-xl font-extrabold" style="color: {{ $pnl['gross_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$pnl['gross_profit']" /></div>
                <div class="mt-1 text-xs" style="color: var(--muted)">Margin {{ $pnl['margin'] }}% · Contract <x-money :value="$pnl['contract']" /></div>
            </div>
        </div>
    </div>

    <nav class="flex gap-1 overflow-x-auto border-t px-4 py-3" style="border-color: var(--border); background: #fafafa">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('events.tab', [$event, $key]) }}" class="tab {{ $tab === $key ? 'tab-active' : '' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>

<div class="mt-6">
    @if ($tab === 'overview')
        @include('events.partials.overview')
    @elseif ($tab === 'plan')
        @include('events.partials.planning')
    @elseif ($tab === 'tasks')
        <div class="mb-4 flex gap-1 overflow-x-auto">
            @foreach (['board' => 'Board', 'timeline' => 'Timeline', 'graph' => 'Graph'] as $key => $label)
                <a href="{{ route('events.tab', [$event, 'tasks']) }}?view={{ $key }}" class="tab {{ $view === $key ? 'tab-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($view === 'timeline')
            @include('events.partials.timeline')
        @elseif ($view === 'graph')
            @include('events.partials.graph')
        @else
            @include('events.partials.tasks')
        @endif
    @elseif ($tab === 'finance')
        <div class="mb-4 flex gap-1 overflow-x-auto">
            @foreach (['budget' => 'Budget', 'expenses' => 'Expenses', 'payments' => 'Payments'] as $key => $label)
                <a href="{{ route('events.tab', [$event, 'finance']) }}?view={{ $key }}" class="tab {{ $view === $key ? 'tab-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if ($view === 'expenses')
            @include('events.partials.expenses')
        @elseif ($view === 'payments')
            @include('events.partials.payments')
        @else
            @include('events.partials.budget')
        @endif
    @elseif ($tab === 'documentation')
        @include('events.partials.documentation')
    @elseif ($tab === 'reports')
        @include('events.partials.reports')
    @elseif ($tab === 'team')
        @include('events.partials.team')
    @endif
</div>
@endsection
