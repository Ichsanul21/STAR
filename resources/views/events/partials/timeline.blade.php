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
    $trackWidth = max(720, (int) ceil($days * $ppd));
    $today = now()->startOfDay();
    $todayLeft = $today->between($start, $end) ? (int) $start->diffInDays($today, false) * $ppd : null;
    $canManage = auth()->user()->canManageEvent($event);

    $months = [];
    $cursor = $start->copy()->startOfMonth();

    while ($cursor->lte($end)) {
        $monthStart = $cursor->copy()->startOfMonth()->max($start);
        $monthEnd = $cursor->copy()->endOfMonth()->min($end);
        $offset = max(0, (int) $start->diffInDays($monthStart, false));
        $span = max(1, (int) $monthStart->diffInDays($monthEnd, false) + 1);

        $months[] = [
            'label' => $cursor->translatedFormat('M Y'),
            'left' => $offset * $ppd,
            'width' => $span * $ppd,
        ];

        $cursor->addMonthNoOverflow()->startOfMonth();
    }

    $ticks = [];
    $weekends = [];

    for ($i = 0; $i < $days; $i++) {
        $tick = $start->copy()->addDays($i);

        if ($tick->isWeekend()) {
            $weekends[] = $i * $ppd;
        }

        $show = $ppd >= 34
            || ($ppd >= 20 && $i % 2 === 0)
            || ($ppd >= 6 && $ppd < 20 && $tick->isMonday())
            || ($ppd < 6 && $tick->day === 1);

        if ($show) {
            $ticks[] = [
                'left' => $i * $ppd,
                'width' => $ppd >= 34 ? $ppd : ($ppd >= 20 ? $ppd * 2 : ($ppd >= 6 ? $ppd * 7 : $ppd * 30)),
                'label' => $ppd >= 20 ? $tick->format('d/m') : $tick->format('d M'),
            ];
        }
    }

    $statusClass = fn (string $status) => match ($status) {
        'done' => 'is-done',
        'blocked' => 'is-blocked',
        'review' => 'is-review',
        'in_progress' => 'is-progress',
        default => 'is-todo',
    };
@endphp

<section id="timeline" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Timeline & Dependency</h2>
            <p class="text-sm" style="color: var(--muted)">
                Gantt dari start/due task.
                Klik salah satu bar atau baris untuk melihat detail dan mengubah jadwal.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="text-xs" style="color: var(--muted)">{{ $start->format('d M') }} — {{ $end->format('d M Y') }}</div>
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
        <div class="gantt-legend">
            @foreach (['done' => ['Selesai', 'var(--success)'], 'in_progress' => ['Berjalan', 'var(--brand)'], 'review' => ['Review', 'var(--warning)'], 'blocked' => ['Blocked', 'var(--danger)'], 'todo' => ['Todo', 'var(--chart-2)']] as $key => [$label, $color])
                <span class="gantt-legend-item"><span class="gantt-legend-dot" style="background: {{ $color }}"></span>{{ $label }}</span>
            @endforeach
            <span class="ml-auto text-[11px]" style="color: var(--muted)">Klik bar untuk edit detail agenda</span>
        </div>

        <div class="gantt-scroll" data-gantt-scroll>
            <div class="gantt-canvas" style="--gantt-track: {{ $trackWidth }}px;">
                <div class="gantt-head">
                    <div class="gantt-head-label">Task</div>
                    <div class="gantt-head-track">
                        <div class="gantt-months">
                            @foreach ($months as $month)
                                <span class="gantt-month" style="left: {{ $month['left'] }}px; width: {{ $month['width'] }}px">{{ $month['label'] }}</span>
                            @endforeach
                        </div>
                        <div class="gantt-ticks">
                            @foreach ($ticks as $tick)
                                <span class="gantt-tick" style="left: {{ $tick['left'] }}px; width: {{ $tick['width'] }}px">{{ $tick['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="gantt-body">
                    <div class="gantt-backdrop" aria-hidden="true">
                        <div class="gantt-backdrop-label"></div>
                        <div class="gantt-backdrop-track">
                            @foreach ($weekends as $weekendLeft)
                                <span class="gantt-weekend" style="left: {{ $weekendLeft }}px; width: {{ $ppd }}px"></span>
                            @endforeach
                            @foreach ($ticks as $tick)
                                <span class="gantt-gridline" style="left: {{ $tick['left'] }}px"></span>
                            @endforeach
                            @if ($todayLeft !== null)
                                <span id="ganttToday" class="gantt-today" style="left: {{ $todayLeft }}px">
                                    <span class="gantt-today-label">Hari ini</span>
                                </span>
                            @endif
                        </div>
                    </div>

                    @foreach ($event->phases as $phase)
                        <div class="gantt-phase">
                            <div class="gantt-phase-label">
                                <span class="gantt-phase-name">{{ $phase->name }}</span>
                                <span class="gantt-phase-progress">{{ (float) $phase->progress }}%</span>
                            </div>
                            <div class="gantt-phase-track"></div>
                        </div>

                        @foreach ($phase->taskGroups as $group)
                            <div class="gantt-group">
                                <div class="gantt-group-label">
                                    <span class="truncate">{{ $group->name }}</span>
                                    <span class="gantt-group-progress">{{ (float) $group->progress }}%</span>
                                </div>
                                <div class="gantt-group-track"></div>
                            </div>

                            @forelse ($group->tasks as $task)
                                @php
                                    $taskStart = ($task->start_at ?? $task->due_at ?? $start)->copy()->startOfDay();
                                    $taskEnd = ($task->due_at ?? $task->start_at ?? $taskStart)->copy()->endOfDay();

                                    if ($taskEnd->lt($taskStart)) {
                                        $taskEnd = $taskStart->copy()->endOfDay();
                                    }

                                    $offsetDays = (int) $start->diffInDays($taskStart, false);
                                    $spanDays = max(1, (int) $taskStart->diffInDays($taskEnd, false) + 1);
                                    $left = max(0, $offsetDays) * $ppd;
                                    $barWidth = max(8, $spanDays * $ppd);
                                    $progress = min(100, max(0, (float) $task->progress));
                                @endphp

                                <button type="button"
                                        class="gantt-row"
                                        id="task-{{ $task->id }}"
                                        data-gantt-task
                                        data-task-id="{{ $task->id }}"
                                        data-task-name="{{ $task->name }}"
                                        data-task-description="{{ $task->description }}"
                                        data-task-start="{{ $taskStart->format('Y-m-d\TH:i') }}"
                                        data-task-due="{{ $taskEnd->format('Y-m-d\TH:i') }}"
                                        data-task-priority="{{ $task->priority }}"
                                        data-task-assignee="{{ $task->assigned_to }}"
                                        data-task-lock="{{ $task->lock_version }}"
                                        data-task-updated="{{ $task->updated_at?->toDateTimeString() }}"
                                        data-task-status="{{ $task->status }}"
                                        data-task-url="{{ route('tasks.update', $task) }}"
                                        data-task-open="{{ route('events.tab', [$event, 'tasks']) }}#task-{{ $task->id }}"
                                        aria-label="Buka detail agenda {{ $task->name }}">
                                    <span class="gantt-row-label">
                                        <span class="gantt-status-dot {{ $statusClass($task->status) }}"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="gantt-task-name">{{ $task->name }}</span>
                                            <span class="gantt-task-meta">
                                                {{ $task->assignee?->name ?: 'Unassigned' }}
                                            </span>
                                        </span>
                                        <span class="gantt-task-badge">{{ $progress }}%</span>
                                    </span>
                                    <span class="gantt-row-track">
                                        <span class="gantt-bar {{ $statusClass($task->status) }}"
                                              style="left: {{ $left }}px; width: {{ $barWidth }}px">
                                            <span class="gantt-bar-fill" style="width: {{ $progress }}%"></span>
                                            @if ($barWidth >= 56)
                                                <span class="gantt-bar-text">{{ $progress }}%</span>
                                            @endif
                                        </span>
                                        <span class="gantt-due-label" style="left: {{ $left + $barWidth + 8 }}px">{{ $taskEnd->format('d/m') }}</span>
                                    </span>
                                </button>
                            @empty
                                <div class="gantt-empty-row">Belum ada task di group ini.</div>
                            @endforelse
                        @endforeach
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <x-modal id="ganttTaskModal" title="Detail Agenda" size="lg">
        <form id="ganttTaskForm" method="POST" action="" class="space-y-3" data-can-edit="{{ $canManage ? '1' : '0' }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="lock_version" id="ganttTaskLock">
            <input type="hidden" name="expected_updated_at" id="ganttTaskUpdated">

            <div class="flex flex-wrap items-center gap-2">
                <span id="ganttTaskStatus" class="badge badge-muted">Task</span>
                <span class="text-[11px]" style="color: var(--muted)">Perubahan status tetap lewat Update Progress.</span>
            </div>

            <div>
                <label class="label" for="ganttTaskName">Nama Task</label>
                <input type="text" name="name" id="ganttTaskName" class="input" required maxlength="160" @disabled(! $canManage)>
            </div>

            <div>
                <label class="label" for="ganttTaskDescription">Deskripsi</label>
                <textarea name="description" id="ganttTaskDescription" rows="2" class="input" @disabled(! $canManage)></textarea>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label" for="ganttTaskStart">Mulai</label>
                    <input type="datetime-local" name="start_at" id="ganttTaskStart" class="input" @disabled(! $canManage)>
                </div>
                <div>
                    <label class="label" for="ganttTaskDue">Deadline</label>
                    <input type="datetime-local" name="due_at" id="ganttTaskDue" class="input" @disabled(! $canManage)>
                </div>
                <div>
                    <label class="label" for="ganttTaskPriority">Prioritas</label>
                    <select name="priority" id="ganttTaskPriority" class="input" @disabled(! $canManage)>
                        @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="ganttTaskAssignee">Assignee</label>
                    <select name="assigned_to" id="ganttTaskAssignee" class="input" @disabled(! $canManage)>
                        <option value="">Belum ditentukan</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 border-t pt-3" style="border-color: var(--border)">
                <a id="ganttTaskOpen" href="#" class="link text-xs">Buka di Tasks</a>
                @if ($canManage)
                    <button class="btn btn-primary btn-sm" type="submit">Simpan Perubahan</button>
                @endif
            </div>
        </form>
    </x-modal>

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
