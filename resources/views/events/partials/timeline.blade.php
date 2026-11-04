@php
    $timelineTasks = $event->tasks->filter(fn ($t) => $t->due_at || $t->start_at);

    if ($timelineTasks->isEmpty()) {
        $timelineTasks = $event->tasks;
    }

    $dates = $timelineTasks->flatMap(fn ($t) => array_filter([$t->start_at, $t->due_at]))->filter();
    $start = ($dates->min()?->copy() ?? $event->start_date?->copy() ?? now())->startOfDay();
    $end = ($dates->max()?->copy() ?? $event->end_date?->copy() ?? $start->copy()->addDays(7))->endOfDay();

    if ($end->lt($start)) {
        $end = $start->copy()->endOfDay();
    }

    $days = max(1, (int) $start->diffInDays($end) + 1);
    $zoom = request('zoom', 'auto');
    $ppd = match ($zoom) {
        'day' => 30,
        'week' => 8,
        'month' => 3,
        default => $days <= 45 ? 26 : ($days <= 120 ? 9 : 3),
    };
    $trackWidth = max(640, (int) ceil($days * $ppd));
    $today = now()->startOfDay();
    $todayLeft = $today->between($start, $end) ? $start->diffInDays($today) * $ppd : null;
    $canManage = auth()->user()->canManageEvent($event);
@endphp

<section id="timeline" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Timeline & Dependency</h2>
            <p class="text-sm" style="color: var(--muted)">
                Gantt dari start/due task.
                @if ($canManage)
                    Geser bar untuk mengubah jadwal task.
                @else
                    Hanya PM/admin yang dapat menggeser jadwal.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="text-xs" style="color: var(--muted)">{{ $start->format('d M') }} – {{ $end->format('d M Y') }}</div>
            <div class="flex rounded-xl border p-1" style="border-color: var(--border)">
                @foreach (['auto' => 'Auto', 'day' => 'Hari', 'week' => 'Minggu', 'month' => 'Bulan'] as $value => $label)
                    <a href="{{ request()->fullUrlWithQuery(['zoom' => $value]) }}#timeline"
                       class="rounded-lg px-2.5 py-1 text-[11px] font-semibold {{ $zoom === $value ? 'text-white' : 'text-slate-600' }}"
                       @if ($zoom === $value) style="background: var(--brand)" @endif>{{ $label }}</a>
                @endforeach
            </div>
            @if ($todayLeft !== null)
                <a href="#ganttToday" class="btn btn-secondary btn-sm">Hari ini</a>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto" data-gantt-scroll>
            <div class="relative" style="min-width: {{ $trackWidth }}px">
                <div class="grid border-b text-xs font-semibold" style="grid-template-columns: 220px 1fr; border-color: var(--border); color: var(--muted)">
                    <div class="sticky left-0 z-10 bg-white px-4 py-2">Task</div>
                    <div class="relative h-8">
                        @for ($i = 0; $i < $days; $i++)
                            @php $tick = $start->copy()->addDays($i); @endphp
                            @if (($ppd >= 20) || ($ppd >= 6 && $i % 7 === 0) || ($ppd < 6 && $tick->day === 1))
                                <span class="absolute top-1.5 h-5 border-l pl-1 text-[9px] leading-5" style="left: {{ $i * $ppd }}px; border-color: var(--border)">
                                    {{ $ppd >= 20 ? $tick->format('d/m') : $tick->format('d M') }}
                                </span>
                            @endif
                        @endfor
                    </div>
                </div>

                @if ($todayLeft !== null)
                    <div id="ganttToday" class="pointer-events-none absolute bottom-0 top-0 z-0 w-px" style="left: {{ 220 + $todayLeft }}px; background: var(--danger); opacity: .4"></div>
                @endif

                <div class="relative z-10 divide-y" style="border-color: var(--border)">
                    @foreach ($event->phases as $phase)
                        <div class="grid bg-slate-50/70 text-[11px] font-bold uppercase tracking-wide" style="grid-template-columns: 220px 1fr; color: var(--muted)">
                            <div class="sticky left-0 z-10 truncate bg-slate-50 px-4 py-1.5">{{ $phase->name }}</div>
                            <div></div>
                        </div>
                        @foreach ($phase->taskGroups as $group)
                            @foreach ($group->tasks as $task)
                                @php
                                    $taskStart = ($task->start_at ?? $task->due_at ?? $start)->copy()->startOfDay();
                                    $taskEnd = ($task->due_at ?? $task->start_at ?? $taskStart)->copy()->endOfDay();
                                    if ($taskEnd->lt($taskStart)) {
                                        $taskEnd = $taskStart->copy()->endOfDay();
                                    }
                                    $offsetDays = $start->diffInDays($taskStart, false);
                                    $spanDays = max(1, $taskStart->diffInDays($taskEnd, false) + 1);
                                    $left = max(0, $offsetDays) * $ppd;
                                    $barWidth = max(6, $spanDays * $ppd);
                                    $barColor = match ($task->status) {
                                        'done' => 'var(--success)',
                                        'blocked' => 'var(--danger)',
                                        'review' => 'var(--warning)',
                                        'in_progress' => 'var(--brand)',
                                        default => 'var(--chart-2)',
                                    };
                                @endphp
                                <div class="grid items-center" style="grid-template-columns: 220px 1fr" id="task-{{ $task->id }}">
                                    <div class="sticky left-0 z-10 truncate bg-white px-4 py-2 text-xs">
                                        <div class="truncate font-semibold">{{ $task->name }}</div>
                                        <div class="truncate" style="color: var(--muted)">{{ $group->name }} · {{ $task->progress }}%</div>
                                    </div>
                                    <div class="relative my-2 h-7">
                                        <div class="absolute inset-x-0 top-1/2 border-t" style="border-color: var(--border)"></div>
                                        <div class="gantt-bar absolute top-1 h-5 overflow-hidden rounded-full"
                                             style="left: {{ $left }}px; width: {{ $barWidth }}px; background: {{ $barColor }}; opacity: .9"
                                             title="{{ $task->name }} · {{ $taskStart->format('d/m/Y') }} → {{ $taskEnd->format('d/m/Y') }}"
                                             data-gantt-bar
                                             data-task-id="{{ $task->id }}"
                                             data-start="{{ $taskStart->toDateString() }}"
                                             data-due="{{ $taskEnd->toDateString() }}"
                                             data-ppd="{{ $ppd }}"
                                             data-left="{{ $left }}"
                                             data-draggable="{{ $canManage ? '1' : '0' }}">
                                            <div class="h-full rounded-full" style="width: {{ min(100, (float) $task->progress) }}%; background: rgb(255 255 255 / 0.35)"></div>
                                        </div>
                                        <div class="pointer-events-none absolute top-0.5 text-[9px] whitespace-nowrap" style="left: {{ $left + $barWidth + 6 }}px; color: var(--muted)">
                                            {{ $taskEnd->format('d/m') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="border-b px-5 py-4" style="border-color: var(--border)"><div class="section-title">Dependency Graph</div></div>
        @php
            $dependencyRows = $event->tasks->flatMap(fn ($task) => $task->dependencies->map(fn ($dependency) => ['task' => $task, 'dependency' => $dependency]));
        @endphp
        <div class="grid gap-2 p-5 text-xs sm:grid-cols-2">
            @forelse ($dependencyRows as $row)
                <div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2">
                    <span class="badge badge-brand">{{ $row['task']->name }}</span>
                    <span style="color: var(--muted)">menunggu</span>
                    <span class="badge badge-muted">{{ $row['dependency']->dependsOn?->name }}</span>
                    <span class="ml-auto" style="color: var(--muted)">{{ $row['dependency']->type }}</span>
                </div>
            @empty
                <div class="text-sm" style="color: var(--muted)">Belum ada dependency.</div>
            @endforelse
        </div>
    </div>
</section>
