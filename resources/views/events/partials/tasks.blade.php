@php
    $allTasks = $event->tasks;
    $taskOptions = $allTasks->sortBy('name');
@endphp

<section id="tasks" class="scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-xl font-extrabold">Tasks</h2>
            <p class="text-sm" style="color: var(--muted)">Task group → task → checklist. Progress parent dihitung otomatis.</p>
        </div>
        <div class="flex gap-2">
            <x-status :status="'in_progress'" /> <span class="text-xs" style="color: var(--muted)">Progress event {{ $event->progress() }}%</span>
        </div>
    </div>

    @if (auth()->user()->canManageEvent($event))
    <details class="card mb-4">
        <summary class="cursor-pointer px-5 py-4 text-sm font-bold" style="color: var(--brand)">+ Tambah Task / Task Group</summary>
        <div class="grid gap-4 border-t p-4 lg:grid-cols-2" style="border-color: var(--border)">
            <div class="card card-pad">
                <div class="section-title">Tambah Task Group</div>
                <form method="POST" action="{{ route('task-groups.store', $event) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div>
                        <label class="label">Fase</label>
                        <select name="event_phase_id" class="input" required>
                            @foreach ($event->phases as $phase)
                                <option value="{{ $phase->id }}">{{ $phase->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Divisi</label>
                        <select name="event_division_id" class="input">
                            <option value="">Tanpa divisi</option>
                            @foreach ($event->divisions as $division)
                                <option value="{{ $division->id }}">{{ $division->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Nama Task Group</label>
                        <input name="name" class="input" required placeholder="Produksi & Logistik">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="btn btn-secondary btn-sm" type="submit">Tambah Group</button>
                    </div>
                </form>
            </div>

            <div class="card card-pad">
                <div class="section-title">Tambah Task</div>
                <form method="POST" action="{{ route('tasks.store', $event) }}" class="mt-3 grid gap-3 sm:grid-cols-2">
                    @csrf
                    <div class="sm:col-span-2">
                        <label class="label">Task Group</label>
                        <select name="task_group_id" class="input" required>
                            @foreach ($event->phases as $phase)
                                @foreach ($phase->taskGroups as $group)
                                    <option value="{{ $group->id }}">{{ $phase->name }} · {{ $group->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Nama Task</label>
                        <input name="name" class="input" required placeholder="Cek kelengkapan dokumentasi">
                    </div>
                    <div>
                        <label class="label">Prioritas</label>
                        <select name="priority" class="input">
                            @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Assignee</label>
                        <select name="assigned_to" class="input">
                            <option value="">Belum ditentukan</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->roleLabel() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Due date</label>
                        <input type="datetime-local" name="due_at" class="input">
                    </div>
                    <div class="sm:col-span-2">
                        <button class="btn btn-primary btn-sm" type="submit">Tambah Task</button>
                    </div>
                </form>
            </div>
        </div>
    </details>
    @endif

    <div class="space-y-5">
        @foreach ($event->phases as $phase)
            <div class="card">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4" style="border-color: var(--border)">
                    <div>
                        <div class="section-title">{{ $phase->name }}</div>
                        <div class="text-xs" style="color: var(--muted)">Bobot {{ (float) $phase->weight }} · {{ $phase->taskGroups->count() }} task group</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-status :status="$phase->status" />
                        <span class="text-sm font-bold">{{ (float) $phase->progress }}%</span>
                    </div>
                </div>

                <div class="divide-y" style="border-color: var(--border)">
                    @forelse ($phase->taskGroups as $group)
                        <div class="p-4 sm:p-5">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <div class="font-bold">{{ $group->name }}</div>
                                    <div class="text-xs" style="color: var(--muted)">{{ $group->division?->name ?: 'Tanpa divisi' }} · bobot {{ (float) $group->weight }}</div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <x-status :status="$group->status" />
                                    <span class="text-xs font-semibold">{{ (float) $group->progress }}%</span>
                                </div>
                            </div>
                            <x-progress :value="$group->progress" />

                            <div class="mt-3 space-y-2">
                                @foreach ($group->tasks as $task)
                                    <div class="rounded-xl border p-3" style="border-color: var(--border)">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="font-semibold">{{ $task->name }}</span>
                                                    <x-status :status="$task->status" />
                                                    <x-status :status="$task->priority" />
                                                    @if ($task->isBlocked())
                                                        <span class="badge badge-danger">Blocked</span>
                                                    @endif
                                                </div>
                                                <div class="mt-1 text-xs" style="color: var(--muted)">
                                                    {{ $task->assignee?->name ?: 'Unassigned' }} · due {{ $task->due_at?->format('d/m/Y H:i') ?: '—' }} · progress {{ (float) $task->progress }}%
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($task->is_revision)
                                                    <span class="badge badge-warning">Revisi</span>
                                                @endif
                                                @if ($task->due_at)
                                                    <span class="badge badge-{{ $task->deadlineTone() }}" title="Deadline {{ $task->due_at->format('d/m/Y H:i') }}">{{ $task->deadlineLabel() }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <details class="mt-3">
                                            <summary class="cursor-pointer text-xs font-semibold" style="color: var(--brand)">Update progress, checklist, dependency, dan history</summary>

                                            @php
                                                $nextStatuses = match ($task->status) {
                                                    'todo' => ['in_progress' => 'Mulai / In Progress'],
                                                    'in_progress' => ['review' => 'Kirim ke Review'],
                                                    'review' => ['done' => 'Selesai / Done'],
                                                    'blocked' => ['in_progress' => 'Resume / In Progress'],
                                                    default => [],
                                                };
                                            @endphp

                                            @if (auth()->user()->canAdvanceTask($task) && count($nextStatuses))
                                                <details class="mt-3 rounded-xl border p-3" style="border-color: var(--border)">
                                                    <summary class="cursor-pointer text-xs font-bold" style="color: var(--brand)">+ Update Progress</summary>
                                                    <form method="POST" action="{{ route('tasks.advance', $task) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                                        @csrf
                                                        <input type="hidden" name="lock_version" value="{{ $task->lock_version }}">
                                                        <div>
                                                            <label class="label">Status Berikutnya</label>
                                                            <select name="to_status" class="input" required>
                                                                @foreach ($nextStatuses as $value => $label)
                                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="label">Keterangan Wajib</label>
                                                            <textarea name="note" rows="2" class="input" required placeholder="Apa yang dikerjakan / hasil update…"></textarea>
                                                        </div>
                                                        <div>
                                                            <label class="label">Foto Bukti (opsional)</label>
                                                            <input type="file" name="photo" accept="image/jpeg,image/png" class="input">
                                                        </div>
                                                        <button class="btn btn-primary btn-sm" type="submit">Simpan Update</button>
                                                    </form>
                                                </details>
                                            @endif

                                            @if (auth()->user()->isDev())
                                                <details class="mt-2 rounded-xl border border-amber-300 bg-amber-50 p-3">
                                                    <summary class="cursor-pointer text-xs font-bold text-amber-900">⚙ Force Status (Dev)</summary>
                                                    <form method="POST" action="{{ route('dev.tasks.force', $task) }}" class="mt-3 space-y-2">
                                                        @csrf
                                                        <div>
                                                            <label class="label">Status apa pun</label>
                                                            <select name="status" class="input" required>
                                                                @foreach (['todo' => 'Todo', 'in_progress' => 'In Progress', 'blocked' => 'Blocked', 'review' => 'Review', 'done' => 'Done', 'cancelled' => 'Cancelled'] as $value => $label)
                                                                    <option value="{{ $value }}" @selected($task->status === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <label class="label">Alasan override</label>
                                                            <textarea name="note" rows="2" class="input" required placeholder="Untuk testing / koreksi data"></textarea>
                                                        </div>
                                                        <button class="btn btn-primary btn-sm" type="submit" onclick="return confirm('Paksa ubah status task ini?')">Force Status</button>
                                                    </form>
                                                </details>
                                            @endif

                                            @if (auth()->user()->canRequestRevision($event))
                                                <details class="mt-2 rounded-xl border p-3" style="border-color: var(--border)">
                                                    <summary class="cursor-pointer text-xs font-bold" style="color: var(--danger)">+ Ajukan Revisi</summary>
                                                    <form method="POST" action="{{ route('tasks.revisions.store', $task) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                                        @csrf
                                                        <div>
                                                            <label class="label">Alasan Revisi Wajib</label>
                                                            <textarea name="note" rows="2" class="input" required placeholder="Bagian mana yang perlu diperbaiki…"></textarea>
                                                        </div>
                                                        <div>
                                                            <label class="label">Foto Pendukung (opsional)</label>
                                                            <input type="file" name="photo" accept="image/jpeg,image/png" class="input">
                                                        </div>
                                                        <button class="btn btn-danger btn-sm" type="submit">Buat Task Revisi</button>
                                                    </form>
                                                </details>
                                            @endif

                                            <div class="mt-3 rounded-xl border p-3" style="border-color: var(--border)">
                                                <div class="text-xs font-bold">History Update</div>
                                                <div class="mt-2 space-y-2">
                                                    @forelse ($task->updates->take(5) as $update)
                                                        <div class="rounded-lg bg-slate-50 p-2 text-xs">
                                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                                <span class="font-semibold">{{ $update->user?->name ?: 'System' }}</span>
                                                                <span style="color: var(--muted)">{{ $update->created_at->format('d/m/Y H:i') }}</span>
                                                            </div>
                                                            <div class="mt-1" style="color: var(--muted)">
                                                                {{ $update->from_status ?: '—' }} → {{ $update->to_status ?: '—' }}
                                                                @if ($update->type === 'revision') · <span class="badge badge-warning">Revisi</span> @endif
                                                            </div>
                                                            <div class="mt-1">{{ $update->note }}</div>
                                                            @if ($update->photo_path)
                                                                <a href="{{ route('task-updates.photo', $update) }}" data-preview="image" data-preview-title="Bukti update {{ $task->name }}" data-preview-meta="{{ json_encode(\App\Support\PreviewMeta::taskUpdate($update)) }}" class="link mt-1 inline-block text-[11px]">Lihat foto</a>
                                                            @endif
                                                        </div>
                                                    @empty
                                                        <div class="text-xs" style="color: var(--muted)">Belum ada update.</div>
                                                    @endforelse
                                                </div>
                                            </div>

                                            <div class="mt-3 grid gap-4 lg:grid-cols-2">
                                                <div>
                                                    <div class="mb-2 text-xs font-bold">Checklist</div>
                                                    <div class="space-y-1">
                                                        @forelse ($task->checklists as $item)
                                                            @if ($item->is_done)
                                                                <div class="flex items-center gap-2 text-sm">
                                                                    <span class="flex h-5 w-5 items-center justify-center rounded border text-[10px]" style="border-color: var(--border); background: var(--brand); color: white">✓</span>
                                                                    <span class="line-through opacity-60">{{ $item->label }}</span>
                                                                </div>
                                                            @elseif (auth()->user()->canAdvanceTask($task))
                                                                <details class="rounded-lg border p-2" style="border-color: var(--border)">
                                                                    <summary class="cursor-pointer text-sm">{{ $item->label }}</summary>
                                                                    <form method="POST" action="{{ route('checklists.complete', $item) }}" enctype="multipart/form-data" class="mt-2 space-y-2">
                                                                        @csrf
                                                                        <input type="hidden" name="lock_version" value="{{ $item->lock_version }}">
                                                                        <textarea name="note" rows="2" class="input text-xs" required placeholder="Keterangan menyelesaikan checklist…"></textarea>
                                                                        <input type="file" name="photo" accept="image/jpeg,image/png" class="input text-xs">
                                                                        <button class="btn btn-secondary btn-sm" type="submit">Selesaikan Checklist</button>
                                                                    </form>
                                                                </details>
                                                            @else
                                                                <div class="flex items-center gap-2 text-sm">
                                                                    <span class="flex h-5 w-5 items-center justify-center rounded border text-[10px]" style="border-color: var(--border); background: white; color: white"></span>
                                                                    <span>{{ $item->label }}</span>
                                                                </div>
                                                            @endif
                                                        @empty
                                                            <div class="text-xs" style="color: var(--muted)">Belum ada checklist.</div>
                                                        @endforelse
                                                    </div>
                                                    @if (auth()->user()->canWorkEvent($event))
                                                    <form method="POST" action="{{ route('tasks.checklists.store', $task) }}" class="mt-2 flex gap-2">
                                                        @csrf
                                                        <input name="label" class="input !py-1.5 text-xs" placeholder="Tambah checklist…" required>
                                                        <button class="btn btn-secondary btn-sm" type="submit">+</button>
                                                    </form>
                                                    @endif
                                                </div>

                                                <div>
                                                    <div class="mb-2 text-xs font-bold">Dependency</div>
                                                    <div class="space-y-1 text-xs">
                                                        @forelse ($task->dependencies as $dependency)
                                                            <div class="rounded-lg bg-slate-50 px-2 py-1">
                                                                Menunggu: {{ $dependency->dependsOn?->name }} ({{ $dependency->type }})
                                                            </div>
                                                        @empty
                                                            <div style="color: var(--muted)">Tidak ada dependency.</div>
                                                        @endforelse
                                                    </div>
                                                    @if (auth()->user()->canManageEvent($event))
                                                    <form method="POST" action="{{ route('tasks.dependencies.store', $task) }}" class="mt-2 flex flex-wrap gap-2">
                                                        @csrf
                                                        <select name="depends_on_task_id" class="input !w-auto !py-1.5 text-xs" required>
                                                            <option value="">Pilih task…</option>
                                                            @foreach ($taskOptions->where('id', '!=', $task->id) as $option)
                                                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                                                            @endforeach
                                                        </select>
                                                        <select name="type" class="input !w-auto !py-1.5 text-xs">
                                                            <option value="finish_to_start">Finish → Start</option>
                                                            <option value="start_to_start">Start → Start</option>
                                                        </select>
                                                        <button class="btn btn-secondary btn-sm" type="submit">Tambah</button>
                                                    </form>
                                                    @endif
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="p-5"><x-empty title="Belum ada task group" body="Tambahkan task group untuk fase ini." /></div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</section>
