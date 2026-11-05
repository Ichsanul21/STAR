@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $hour = (int) now()->format('H');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
    $canCreate = in_array(auth()->user()->role, ['super_admin', 'eo_admin', 'pm_event', 'dev'], true);
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <div class="text-sm font-semibold" style="color: var(--muted)">{{ $greeting }}, {{ auth()->user()->name }} · {{ now()->translatedFormat('l, d F Y') }}</div>
        <h1 class="mt-1 text-3xl font-extrabold tracking-tight">Dashboard</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Ringkasan event, task, dan keuangan yang bisa kamu akses.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('events.index') }}" class="btn btn-secondary">Semua Event</a>
        @if ($canCreate)
            <a href="{{ route('events.index') }}?create=1" class="btn btn-primary">+ Buat Event</a>
        @endif
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <a href="{{ route('events.index', ['status' => 'active']) }}" class="kpi-card kpi-card-brand">
        <div class="kpi-head">
            <span class="kpi-label">Event Aktif</span>
            <span class="kpi-icon"><x-icon name="events" class="w-5 h-5" /></span>
        </div>
        <div class="kpi-value">{{ $activeEvents }}</div>
        <div class="kpi-foot">{{ $draftEvents }} draft · {{ $completedEvents }} selesai</div>
        <span class="kpi-arrow"><x-icon name="arrow" class="w-4 h-4" /></span>
    </a>

    <a href="{{ route('global.my-tasks') }}" class="kpi-card">
        <div class="kpi-head">
            <span class="kpi-label">Task Perlu Perhatian</span>
            <span class="kpi-icon"><x-icon name="tasks" class="w-5 h-5" /></span>
        </div>
        <div class="kpi-value">{{ $overdueTasks + $dueSoonTasks }}</div>
        <div class="kpi-foot">{{ $overdueTasks }} overdue · {{ $dueSoonTasks }} jatuh tempo ≤ 7 hari</div>
        <span class="kpi-arrow"><x-icon name="arrow" class="w-4 h-4" /></span>
    </a>

    <a href="{{ route('global.budget') }}" class="kpi-card">
        <div class="kpi-head">
            <span class="kpi-label">Total PnL</span>
            <span class="kpi-icon"><x-icon name="budget" class="w-5 h-5" /></span>
        </div>
        <div class="kpi-value kpi-value-sm" style="color: {{ $grossProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$grossProfit" /></div>
        <div class="kpi-foot">Margin {{ $margin }}% · Contract <x-money :value="$contract" /></div>
        <span class="kpi-arrow"><x-icon name="arrow" class="w-4 h-4" /></span>
    </a>

    <a href="{{ route('global.payments') }}" class="kpi-card">
        <div class="kpi-head">
            <span class="kpi-label">Outstanding Client</span>
            <span class="kpi-icon"><x-icon name="payments" class="w-5 h-5" /></span>
        </div>
        <div class="kpi-value kpi-value-sm"><x-money :value="$outstanding" /></div>
        <div class="kpi-foot">Dari <x-money :value="$invoiced" /> invoice · <x-money :value="$received" /> diterima</div>
        <span class="kpi-arrow"><x-icon name="arrow" class="w-4 h-4" /></span>
    </a>
</div>

<div class="mt-4 grid gap-4 xl:grid-cols-12 items-start" data-dashboard-chart
     data-performance="{{ json_encode($performanceEvents) }}"
     data-task-stats="{{ json_encode($taskStats) }}"
     data-financial="{{ json_encode($financialEvents) }}">

    <div class="min-w-0 space-y-4 xl:col-span-7">
        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="section-title">Performa event</div>
                    <div class="text-xs" style="color: var(--muted)">Progress vs serapan budget</div>
                </div>
                <a href="{{ route('global.budget') }}" class="link text-xs">Detail budget</a>
            </div>
            <div class="mt-4 h-64">
                @if ($performanceEvents->isEmpty())
                    <x-empty title="Belum ada data event" body="Progress dan serapan budget muncul setelah event memiliki task dan RAB." />
                @else
                    <canvas id="performanceChart" aria-label="Grafik progress dan serapan budget per event" role="img"></canvas>
                @endif
            </div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl px-3 py-2" style="background: var(--brand-soft)">
                    <div class="text-[10px] font-semibold uppercase tracking-wide" style="color: var(--muted)">Avg progress</div>
                    <div class="text-lg font-extrabold" style="color: var(--brand)">{{ $avgProgress }}%</div>
                </div>
                <div class="rounded-xl px-3 py-2" style="background: #f1f5f9">
                    <div class="text-[10px] font-semibold uppercase tracking-wide" style="color: var(--muted)">Avg serapan</div>
                    <div class="text-lg font-extrabold" style="color: var(--chart-2)">{{ $avgAbsorption }}%</div>
                </div>
                <div class="rounded-xl px-3 py-2" style="background: #f1f5f9">
                    <div class="text-[10px] font-semibold uppercase tracking-wide" style="color: var(--muted)">Event</div>
                    <div class="text-lg font-extrabold">{{ $performanceEvents->count() }}</div>
                </div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="section-title">Progress keseluruhan</div>
                    <div class="text-xs" style="color: var(--muted)">Distribusi status dan progress per event</div>
                </div>
                <a href="{{ route('global.my-tasks') }}" class="link text-xs">My Tasks</a>
            </div>
            <div class="mt-3 flex items-center justify-center">
                <div class="relative h-40 w-40">
                    <canvas id="taskStatusChart" aria-label="Distribusi status task" role="img"></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        @php
                            $totalTasks = collect($taskStats)->sum();
                            $doneTasks = (int) ($taskStats['done'] ?? 0);
                            $donePercent = $totalTasks > 0 ? round($doneTasks / $totalTasks * 100) : 0;
                        @endphp
                        <div class="text-3xl font-extrabold">{{ $donePercent }}%</div>
                        <div class="text-[10px]" style="color: var(--muted)">task selesai</div>
                    </div>
                </div>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                @foreach (['done' => ['Selesai', 'var(--success)'], 'in_progress' => ['Berjalan', 'var(--brand)'], 'review' => ['Review', 'var(--warning)'], 'blocked' => ['Blocked', 'var(--danger)'], 'todo' => ['Todo', 'var(--muted)']] as $key => [$label, $color])
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" style="background: {{ $color }}"></span>
                        <span style="color: var(--muted)">{{ $label }}</span>
                        <span class="ml-auto font-bold">{{ (int) ($taskStats[$key] ?? 0) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-3 border-t pt-3 text-xs" style="border-color: var(--border); color: var(--muted)">
                {{ $totalTasks }} task di {{ $activeEvents + $draftEvents + $completedEvents }} event
            </div>
            @if ($performanceEvents->isNotEmpty())
                <div class="mt-3 space-y-2 border-t pt-3" style="border-color: var(--border)">
                    <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Progress per event</div>
                    @foreach ($performanceEvents->take(3) as $row)
                        <div>
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <span class="truncate font-semibold">{{ $row['name'] }}</span>
                                <span class="font-bold">{{ $row['progress'] }}%</span>
                            </div>
                            <div class="mt-1"><x-progress :value="$row['progress']" /></div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card card-pad">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="section-title">Perbandingan keuangan</div>
                    <div class="text-xs" style="color: var(--muted)">Pendapatan, expenses, PnL, dan piutang per event</div>
                </div>
                <a href="{{ route('global.budget') }}" class="link text-xs">Lihat budget</a>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ([
                    ['revenue', 'Pendapatan', 'var(--brand)'],
                    ['expenses', 'Expenses', 'var(--danger)'],
                    ['pnl', 'PnL', 'var(--success)'],
                    ['receivable', 'Piutang', 'var(--warning)'],
                ] as [$key, $label, $color])
                    <button type="button" class="fin-chip is-active" data-finance-toggle="{{ $key }}" aria-pressed="true">
                        <span class="fin-dot" style="background: {{ $color }}"></span>{{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="mt-3 h-72">
                @if ($financialEvents->isEmpty())
                    <x-empty title="Belum ada data keuangan" body="Grafik muncul setelah event memiliki kontrak, expense, dan invoice." />
                @else
                    <canvas id="financeChart" aria-label="Grafik perbandingan keuangan per event" role="img"></canvas>
                @endif
            </div>

            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="mini-stat"><div class="mini-label">Pendapatan</div><div class="mini-value"><x-money :value="$contract" /></div></div>
                <div class="mini-stat"><div class="mini-label">Expenses</div><div class="mini-value"><x-money :value="$actual" /></div></div>
                <div class="mini-stat"><div class="mini-label">PnL</div><div class="mini-value" style="color: {{ $grossProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$grossProfit" /></div></div>
                <div class="mini-stat"><div class="mini-label">Piutang</div><div class="mini-value" style="color: {{ $outstanding > 0 ? 'var(--warning)' : 'var(--success)' }}"><x-money :value="$outstanding" /></div></div>
            </div>

            <div class="mt-4">
                <div class="mb-1 flex justify-between text-xs" style="color: var(--muted)">
                    <span>Serapan budget</span><span>{{ $absorption }}%</span>
                </div>
                <x-progress :value="$absorption" />
                <div class="mt-1 text-[11px]" style="color: var(--muted)"><x-money :value="$actual" /> dari <x-money :value="$planned" /></div>
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between"><div class="section-title">Log terbaru</div><a href="{{ route('activity-logs.index') }}" class="link text-xs">Semua</a></div>
            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                @forelse ($activities as $activity)
                    <div class="flex items-start gap-2 text-xs">
                        <span class="mt-1 h-1.5 w-1.5 rounded-full" style="background: var(--brand)"></span>
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $activity->description ?: $activity->action }}</div>
                            <div class="text-[10px]" style="color: var(--muted)">{{ $activity->user?->name }} · {{ $activity->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm" style="color: var(--muted)">Belum ada aktivitas.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="min-w-0 space-y-4 xl:col-span-5">
        <div class="card card-pad">
            <div class="flex items-center justify-between gap-2">
                <div class="section-title">Agenda & pengingat</div>
                <a href="{{ route('notifications.index') }}" class="link text-xs">Notifikasi</a>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($agendaTasks as $task)
                    <a href="{{ route('events.tab', [$task->event, 'tasks']) }}" class="agenda-row">
                        <span class="agenda-date {{ $task->due_at?->isPast() ? 'is-danger' : '' }}">{{ $task->due_at?->format('d/m') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="agenda-title">{{ $task->name }}</span>
                            <span class="agenda-sub">{{ $task->event?->name }}</span>
                        </span>
                        <x-status :status="$task->status" />
                    </a>
                @empty
                    <div class="text-sm" style="color: var(--muted)">Tidak ada task dengan deadline.</div>
                @endforelse
            </div>

            @if ($dueInvoices->isNotEmpty() || $eventsWithoutReport->isNotEmpty())
                <div class="mt-4 space-y-2 border-t pt-3" style="border-color: var(--border)">
                    @foreach ($dueInvoices as $invoice)
                        <a href="{{ route('global.payments', ['search' => $invoice->invoice_number]) }}" class="agenda-row">
                            <span class="agenda-date {{ $invoice->due_date?->isPast() ? 'is-danger' : '' }}">{{ $invoice->due_date?->format('d/m') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="agenda-title">{{ $invoice->invoice_number }}</span>
                                <span class="agenda-sub">Sisa <x-money :value="$invoice->balance()" /></span>
                            </span>
                            <x-status :status="$invoice->derivedStatus()" />
                        </a>
                    @endforeach

                    @if ($eventsWithoutReport->isNotEmpty())
                        <div class="agenda-note">
                            {{ $eventsWithoutReport->count() }} event belum punya laporan:
                            {{ $eventsWithoutReport->take(2)->pluck('name')->join(', ') }}{{ $eventsWithoutReport->count() > 2 ? ', …' : '' }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--border)">
                <div>
                    <div class="section-title">Event aktif</div>
                    <div class="text-xs" style="color: var(--muted)">{{ $activeEventList->count() }} event berjalan</div>
                </div>
                <a href="{{ route('events.index') }}" class="link text-xs">Semua</a>
            </div>
            <div class="divide-y" style="border-color: var(--border)">
                @forelse ($activeEventList as $row)
                    <a href="{{ route('events.show', $row['event']) }}" class="block px-5 py-3 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-bold">{{ $row['event']->name }}</div>
                                <div class="truncate text-xs" style="color: var(--muted)">{{ $row['event']->dealer?->name }} · {{ $row['event']->start_date?->format('d/m/Y') }}</div>
                            </div>
                            <span class="badge badge-brand">{{ $row['progress'] }}%</span>
                        </div>
                        <div class="mt-2"><x-progress :value="$row['progress']" /></div>
                        <div class="mt-1.5 flex items-center justify-between text-[11px]" style="color: var(--muted)">
                            <span>P&L</span>
                            <span class="font-bold" style="color: {{ $row['pnl']['gross_profit'] >= 0 ? 'var(--success)' : 'var(--danger)' }}"><x-money :value="$row['pnl']['gross_profit']" /></span>
                        </div>
                    </a>
                @empty
                    <div class="px-5 py-8 text-center text-sm" style="color: var(--muted)">Belum ada event aktif.</div>
                @endforelse
            </div>
        </div>

        <div class="card card-pad">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="section-title">Deadline terdekat</div>
                    <div class="text-xs" style="color: var(--muted)">{{ $deadlineToday }} hari ini · {{ $deadlineSoon }} ≤ 3 hari · {{ $overdueTasks }} terlambat</div>
                </div>
                <a href="{{ route('global.my-tasks') }}" class="link text-xs">Semua</a>
            </div>

            <div class="mt-3 space-y-2">
                @forelse ($deadlineTasks as $task)
                    <a href="{{ route('events.tab', [$task->event, 'tasks']) }}" class="flex items-start justify-between gap-2 rounded-xl border px-3 py-2" style="border-color: var(--border)">
                        <span class="min-w-0">
                            <span class="block truncate text-xs font-bold">{{ $task->name }}</span>
                            <span class="block truncate text-[10px]" style="color: var(--muted)">{{ $task->event?->name }} · {{ $task->due_at?->format('d/m/Y H:i') }}</span>
                        </span>
                        <span class="badge badge-{{ $task->deadlineTone() }} whitespace-nowrap">{{ $task->deadlineLabel() }}</span>
                    </a>
                @empty
                    <div class="text-sm" style="color: var(--muted)">Tidak ada deadline mendatang.</div>
                @endforelse
            </div>

            <a href="{{ route('global.my-tasks') }}" class="mt-3 block rounded-xl px-3 py-2 text-center text-xs font-bold" style="background: var(--brand-soft); color: var(--brand)">Buka My Tasks</a>
        </div>

        <div class="card">
            <div class="flex items-center justify-between border-b px-5 py-4" style="border-color: var(--border)">
                <div>
                    <div class="section-title">Aktivitas tim</div>
                    <div class="text-xs" style="color: var(--muted)">Update task terbaru</div>
                </div>
                <a href="{{ route('activity-logs.index') }}" class="link text-xs">Log</a>
            </div>
            <div class="divide-y" style="border-color: var(--border)">
                @forelse ($teamUpdates as $update)
                    <div class="flex items-start gap-3 px-5 py-3">
                        <div class="avatar-sm">{{ strtoupper(substr($update->user?->name ?? 'S', 0, 1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-semibold">{{ $update->user?->name ?: 'System' }}</div>
                            <div class="truncate text-xs" style="color: var(--muted)">{{ $update->task?->name }}</div>
                            <div class="mt-0.5 text-[10px]" style="color: var(--muted)">{{ $update->created_at?->diffForHumans() }}</div>
                        </div>
                        <x-status :status="$update->to_status" />
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-sm" style="color: var(--muted)">Belum ada update task.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
