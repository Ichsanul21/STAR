@php
    $curve = $curve ?? ['labels' => [], 'planned' => [], 'actual' => [], 'current' => 0, 'event_progress' => 0, 'planned_today' => 0, 'variance' => 0, 'done' => 0, 'total' => 0, 'range' => null, 'empty' => true];
    $variance = (float) $curve['variance'];
@endphp

<section id="scurve" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">S-Curve Progress</h2>
            <p class="text-sm" style="color: var(--muted)">
                Rencana vs realisasi kumulatif berbobot task.
                @if ($curve['range'])
                    · {{ $curve['range'] }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-brand">Planned</span>
            <span class="badge badge-success">Actual</span>
            <a href="{{ route('events.tab', [$event, 'tasks']) }}?view=timeline" class="btn btn-secondary btn-sm">Lihat Gantt</a>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Task selesai (bobot)</div>
            <div class="mt-1 text-2xl font-extrabold" style="color: var(--brand)">{{ $curve['current'] }}%</div>
            <div class="mt-1 text-[11px]" style="color: var(--muted)">{{ $curve['done'] }} dari {{ $curve['total'] }} task selesai · progress event {{ $curve['event_progress'] ?? 0 }}%</div>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Rencana hari ini</div>
            <div class="mt-1 text-2xl font-extrabold">{{ $curve['planned_today'] }}%</div>
            <div class="mt-1 text-[11px]" style="color: var(--muted)">Target kumulatif</div>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Deviasi</div>
            <div class="mt-1 text-2xl font-extrabold" style="color: {{ $variance < 0 ? 'var(--danger)' : 'var(--success)' }}">
                {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 1, ',', '.') }}%
            </div>
            <div class="mt-1 text-[11px]" style="color: var(--muted)">{{ $variance < 0 ? 'Di bawah rencana' : 'Sesuai / di atas rencana' }}</div>
        </div>
        <div class="card card-pad">
            <div class="text-[10px] font-bold uppercase tracking-wide" style="color: var(--muted)">Task</div>
            <div class="mt-1 text-2xl font-extrabold">{{ $curve['total'] }}</div>
            <div class="mt-1 text-[11px]" style="color: var(--muted)">Total task dalam kurva</div>
        </div>
    </div>

    <div class="card card-pad mt-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="section-title">Kurva kumulatif</div>
                <div class="text-xs" style="color: var(--muted)">Actual dihitung dari task yang sudah selesai (completed_at).</div>
            </div>
            <div class="flex items-center gap-3 text-[11px]" style="color: var(--muted)">
                <span class="flex items-center gap-1.5"><span class="h-0.5 w-5 border-t-2 border-dashed" style="border-color: var(--chart-2)"></span>Planned</span>
                <span class="flex items-center gap-1.5"><span class="h-0.5 w-5" style="background: var(--success)"></span>Actual</span>
            </div>
        </div>

        <div class="mt-4 h-80" data-scurve data-series="{{ json_encode($curve) }}">
            @if ($curve['empty'])
                <x-empty title="Belum ada data kurva" body="Isi start/due date dan selesaikan task untuk melihat S-Curve." />
            @else
                <canvas id="scurveChart" aria-label="S-Curve progress rencana vs aktual" role="img"></canvas>
            @endif
        </div>
    </div>
</section>
