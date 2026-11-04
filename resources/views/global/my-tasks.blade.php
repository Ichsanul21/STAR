@extends('layouts.app')

@section('title', 'My Tasks')

@section('content')
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold tracking-tight">My Tasks</h1>
        <p class="mt-1 text-sm" style="color: var(--muted)">Task yang di-assign ke kamu, lintas event.</p>
    </div>
    <form method="GET" class="flex gap-2">
        <select name="status" class="input !w-auto" onchange="this.form.submit()">
            <option value="">Semua status</option>
            @foreach (['todo' => 'Todo', 'in_progress' => 'Berjalan', 'blocked' => 'Blocked', 'review' => 'Review', 'done' => 'Done'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="flex flex-wrap gap-2 border-b p-3" style="border-color: var(--border)">
        <input data-table-filter="myTasksTable" class="input max-w-xs" placeholder="Cari task / event…">
        <select data-table-filter-status="myTasksTable" class="input !w-auto">
            <option value="">Semua status</option>
            @foreach (['todo' => 'Todo', 'in_progress' => 'Berjalan', 'blocked' => 'Blocked', 'review' => 'Review', 'done' => 'Done'] as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="overflow-x-auto">
        <table class="table" id="myTasksTable">
            <thead><tr><th>Task</th><th>Event</th><th>Status</th><th>Prioritas</th><th>Due</th><th>Progress</th></tr></thead>
            <tbody>
                @forelse ($tasks as $task)
                    <tr data-search="{{ strtolower($task->name.' '.($task->group?->name ?? '').' '.($task->event?->name ?? '')) }}" data-status="{{ $task->status }}">
                        <td>
                            <a href="{{ route('events.tab', [$task->event, 'tasks']) }}" class="font-semibold hover:underline">{{ $task->name }}</a>
                            <div class="text-xs" style="color: var(--muted)">{{ $task->group?->name }}</div>
                        </td>
                        <td><a href="{{ route('events.tab', [$task->event, 'overview']) }}" class="link text-xs">{{ $task->event?->name }}</a></td>
                        <td><x-status :status="$task->status" /></td>
                        <td><x-status :status="$task->priority" /></td>
                        <td class="whitespace-nowrap text-xs">{{ $task->due_at?->format('d/m/Y H:i') ?: '—' }}</td>
                        <td class="min-w-28"><x-progress :value="$task->progress" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-sm" style="color: var(--muted)">Tidak ada task assigned.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4">{{ $tasks->links() }}</div>
@endsection
