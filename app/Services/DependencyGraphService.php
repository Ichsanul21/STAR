<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use Illuminate\Support\Collection;

class DependencyGraphService
{
    public function build(Event $event): array
    {
        $tasks = $event->tasks()->with(['group.phase', 'dependencies'])->get()->keyBy('id');
        $dependencies = $event->tasks()->with('dependencies')->get()
            ->flatMap(fn (Task $task) => $task->dependencies->map(fn ($dependency) => [
                'from' => $dependency->depends_on_task_id,
                'to' => $task->id,
                'type' => $dependency->type,
                'lag' => (int) $dependency->lag_minutes,
            ]))
            ->filter(fn ($edge) => $tasks->has($edge['from']) && $tasks->has($edge['to']))
            ->values();

        $predecessors = [];
        $successors = [];
        foreach ($dependencies as $edge) {
            $predecessors[$edge['to']][] = $edge;
            $successors[$edge['from']][] = $edge;
        }

        $duration = [];
        foreach ($tasks as $id => $task) {
            $start = $task->start_at ?? $task->due_at;
            $end = $task->due_at ?? $task->start_at;
            $duration[$id] = ($start && $end) ? max(1, (int) $start->diffInDays($end) + 1) : 1;
        }

        $topo = $this->topological($tasks->keys()->all(), $dependencies);

        if ($topo === null) {
            return $this->graph($tasks, $dependencies, [], $duration, [], [], [], []);
        }

        $es = array_fill_keys($tasks->keys()->all(), 0);
        $ef = [];
        foreach ($topo as $id) {
            foreach ($predecessors[$id] ?? [] as $edge) {
                $from = $edge['from'];
                $lag = $edge['lag'] / 1440;
                $candidate = match ($edge['type']) {
                    'start_to_start' => ($es[$from] ?? 0) + $lag,
                    'finish_to_finish' => ($ef[$from] ?? 0) - $duration[$id] + $lag,
                    'start_to_finish' => ($es[$from] ?? 0) - $duration[$id] + $lag,
                    default => ($ef[$from] ?? 0) + $lag,
                };
                $es[$id] = max($es[$id], $candidate);
            }
            $ef[$id] = $es[$id] + $duration[$id];
        }

        $projectEnd = $ef ? max($ef) : 0;
        $lf = [];
        $ls = [];
        foreach (array_reverse($topo) as $id) {
            $candidates = [];
            foreach ($successors[$id] ?? [] as $edge) {
                $to = $edge['to'];
                $lag = $edge['lag'] / 1440;
                $candidates[] = match ($edge['type']) {
                    'start_to_start' => ($ls[$to] ?? $projectEnd) - $lag + $duration[$id],
                    'finish_to_finish' => ($lf[$to] ?? $projectEnd) - $lag,
                    'start_to_finish' => ($lf[$to] ?? $projectEnd) - $lag,
                    default => ($ls[$to] ?? $projectEnd) - $lag,
                };
            }
            $lf[$id] = $candidates ? min($candidates) : $projectEnd;
            $ls[$id] = $lf[$id] - $duration[$id];
        }

        $critical = [];
        foreach ($tasks as $id => $task) {
            if (isset($ls[$id], $es[$id]) && abs($ls[$id] - $es[$id]) < 0.01) {
                $critical[] = $id;
            }
        }

        return $this->graph($tasks, $dependencies, $critical, $duration, $es, $ef, $ls, $lf);
    }

    private function topological(array $ids, Collection $dependencies): ?array
    {
        $indegree = array_fill_keys($ids, 0);
        $adjacency = array_fill_keys($ids, []);

        foreach ($dependencies as $edge) {
            $adjacency[$edge['from']][] = $edge['to'];
            $indegree[$edge['to']] = ($indegree[$edge['to']] ?? 0) + 1;
        }

        $queue = array_keys(array_filter($indegree, fn ($degree) => $degree === 0));
        $order = [];

        while ($queue) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($adjacency[$id] ?? [] as $next) {
                $indegree[$next]--;
                if ($indegree[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        return count($order) === count($ids) ? $order : null;
    }

    private function graph(Collection $tasks, Collection $dependencies, array $critical, array $duration, array $es, array $ef, array $ls, array $lf): array
    {
        $phases = $tasks->map(fn (Task $task) => $task->group?->phase)
            ->filter()
            ->unique('id')
            ->sortBy('sort_order')
            ->values();
        $phaseIndex = $phases->pluck('id')->flip();

        $nodes = [];
        $perPhase = [];
        foreach ($tasks as $id => $task) {
            $phase = $task->group?->phase;
            $index = $phaseIndex[$phase?->id] ?? 0;
            $row = $perPhase[$index] ?? 0;
            $perPhase[$index] = $row + 1;

            $nodes[$id] = [
                'id' => $id,
                'name' => $task->name,
                'status' => $task->status,
                'priority' => $task->priority,
                'phase' => $phase?->name ?? '—',
                'group' => $task->group?->name ?? '—',
                'progress' => (float) $task->progress,
                'duration' => $duration[$id] ?? 1,
                'es' => round($es[$id] ?? 0, 2),
                'ef' => round($ef[$id] ?? 0, 2),
                'ls' => round($ls[$id] ?? 0, 2),
                'lf' => round($lf[$id] ?? 0, 2),
                'slack' => round(($ls[$id] ?? 0) - ($es[$id] ?? 0), 2),
                'critical' => in_array($id, $critical, true),
                'x' => $task->graph_x !== null ? (float) $task->graph_x : 40 + ($index * 300),
                'y' => $task->graph_y !== null ? (float) $task->graph_y : 40 + ($row * 110),
                'width' => 230,
                'height' => 64,
            ];
        }

        $edges = [];
        foreach ($dependencies as $edge) {
            if (! isset($nodes[$edge['from']], $nodes[$edge['to']])) {
                continue;
            }
            $edges[] = [
                'from' => $edge['from'],
                'to' => $edge['to'],
                'type' => $edge['type'],
                'critical' => in_array($edge['from'], $critical, true) && in_array($edge['to'], $critical, true),
            ];
        }

        $maxRows = $perPhase ? max($perPhase) : 1;

        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'critical' => $critical,
            'width' => max(620, $phases->count() * 300 + 80),
            'height' => max(360, $maxRows * 110 + 80),
            'hasCycle' => $critical === [] && $dependencies->isNotEmpty() && count($nodes) > 0 && $this->topological(array_keys($nodes), $dependencies) === null,
        ];
    }
}
