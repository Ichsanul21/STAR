<h2>Task</h2>
<table class="data">
    <thead><tr><th>Fase</th><th>Task Group</th><th>Task</th><th>Status</th><th class="right">Progress</th><th>Assignee</th></tr></thead>
    <tbody>
        @foreach ($event->phases as $phase)
            @foreach ($phase->taskGroups as $group)
                @foreach ($group->tasks as $task)
                    <tr>
                        <td>{{ $phase->name }}</td>
                        <td>{{ $group->name }}</td>
                        <td>{{ $task->name }}</td>
                        <td>{{ $task->status }}</td>
                        <td class="right">{{ (float) $task->progress }}%</td>
                        <td>{{ $task->assignee?->name ?: '—' }}</td>
                    </tr>
                @endforeach
            @endforeach
        @endforeach
    </tbody>
</table>
