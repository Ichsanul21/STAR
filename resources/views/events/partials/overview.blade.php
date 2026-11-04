@php
    $allTasks = $event->tasks;
    $doneTasks = $allTasks->where('status', 'done')->count();
    $overdue = $allTasks->filter(fn ($t) => $t->due_at && $t->due_at->isPast() && $t->status !== 'done')->count();
@endphp

<section id="overview" class="scroll-mt-24">
    <div class="mb-3 flex items-end justify-between">
        <div>
            <h2 class="text-xl font-extrabold">Overview</h2>
            <p class="text-sm" style="color: var(--muted)">Progress per fase, hasil event, dan aktivitas.</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card card-pad">
            <div class="text-xs font-semibold" style="color: var(--muted)">Task</div>
            <div class="kpi-value mt-1">{{ $doneTasks }}/{{ $allTasks->count() }}</div>
            <div class="mt-1 text-xs" style="color: var(--muted)">{{ $overdue }} overdue</div>
        </div>
        <div class="card card-pad">
            <div class="text-xs font-semibold" style="color: var(--muted)">Dokumentasi</div>
            <div class="kpi-value mt-1">{{ $event->media->count() }}</div>
            <div class="mt-1 text-xs" style="color: var(--muted)">{{ $event->media->where('status', 'approved')->count() }} approved</div>
        </div>
        <div class="card card-pad">
            <div class="text-xs font-semibold" style="color: var(--muted)">Pengunjung</div>
            <div class="kpi-value mt-1">{{ $event->visitors->count() }}</div>
            <div class="mt-1 text-xs" style="color: var(--muted)">Target {{ $event->target_visitors }}</div>
        </div>
        <div class="card card-pad">
            <div class="text-xs font-semibold" style="color: var(--muted)">Leads</div>
            <div class="kpi-value mt-1">{{ $event->visitors->where('lead_status', '!=', 'lost')->count() }}</div>
            <div class="mt-1 text-xs" style="color: var(--muted)">Target {{ $event->target_leads }}</div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Progress per fase</div>
                <div class="text-xs" style="color: var(--muted)">Rollup task kecil → task group → fase → event</div>
            </div>
            <div class="space-y-5 p-5">
                @forelse ($event->phases as $phase)
                    <div>
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="font-semibold">{{ $phase->name }}</div>
                            <div class="flex items-center gap-2 text-xs" style="color: var(--muted)">
                                <x-status :status="$phase->status" />
                                <span>{{ (float) $phase->progress }}%</span>
                            </div>
                        </div>
                        <x-progress :value="$phase->progress" />
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($phase->taskGroups as $group)
                                <span class="rounded-full px-2.5 py-1 text-[11px]" style="background: var(--brand-soft); color: var(--brand)">
                                    {{ $group->name }} · {{ (float) $group->progress }}%
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <x-empty title="Belum ada fase" body="Tambahkan fase di tab Team atau gunakan event template." />
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Hasil Event</div>
                <div class="text-xs" style="color: var(--muted)">Target vs aktual</div>
            </div>
            <div class="space-y-3 p-5 text-sm">
                @php $result = $event->result; @endphp
                @foreach ([
                    'Pengunjung' => [$result?->visitors ?? $event->visitors->count(), $event->target_visitors],
                    'Leads' => [$result?->leads ?? $event->visitors->where('lead_status', '!=', 'lost')->count(), $event->target_leads],
                    'Test Drive' => [$result?->test_drives ?? $event->visitors->where('test_drive', true)->count(), null],
                    'SPK' => [$result?->spk ?? 0, null],
                    'Unit Terjual' => [$result?->units_sold ?? 0, $event->target_units],
                    'Revenue' => [$result?->revenue ?? 0, null],
                ] as $label => [$actual, $target])
                    <div class="flex items-center justify-between gap-3">
                        <span style="color: var(--muted)">{{ $label }}</span>
                        <span class="font-bold">
                            @if ($label === 'Revenue')
                                <x-money :value="$actual" />
                            @else
                                {{ $actual }}
                            @endif
                            @if ($target)
                                <span class="text-xs font-normal" style="color: var(--muted)">/ {{ $target }}</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-2">
        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Aktivitas</div>
            </div>
            <div class="space-y-3 p-5">
                @forelse (($event->activityLogs ?? collect())->take(6) as $activity)
                    <div class="flex gap-3 text-sm">
                        <div class="mt-1 h-2 w-2 rounded-full" style="background: var(--brand)"></div>
                        <div>
                            <div class="font-medium">{{ $activity->description ?: $activity->action }}</div>
                            <div class="text-xs" style="color: var(--muted)">{{ $activity->user?->name }} · {{ $activity->created_at->diffForHumans() }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-sm" style="color: var(--muted)">Belum ada aktivitas.</div>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="border-b px-5 py-4" style="border-color: var(--border)">
                <div class="section-title">Dokumentasi terbaru</div>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                @forelse ($event->media->take(6) as $media)
                    <a href="{{ route('media.file', $media) }}" target="_blank" class="group">
                        <div class="aspect-square overflow-hidden rounded-xl bg-slate-100">
                            <img src="{{ route('media.file', $media) }}" alt="{{ $media->caption }}" class="h-full w-full object-cover transition group-hover:scale-105">
                        </div>
                        <div class="mt-1 truncate text-[10px]" style="color: var(--muted)">{{ $media->caption ?: $media->phase?->name }}</div>
                    </a>
                @empty
                    <div class="col-span-full text-sm" style="color: var(--muted)">Belum ada foto. Upload di tab Documentation.</div>
                @endforelse
            </div>
        </div>
    </div>
</section>
