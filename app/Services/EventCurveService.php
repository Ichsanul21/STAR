<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Task;
use Carbon\CarbonInterface;

class EventCurveService
{
    /**
     * Build a planned vs actual S-curve for an event.
     *
     * Planned: each task's weight is accrued linearly from start_at to due_at.
     * Actual: each task's weight is recognised when it is completed (completed_at).
     *
     * @return array<string, mixed>
     */
    public function build(Event $event): array
    {
        $tasks = $event->tasks
            ->filter(fn (Task $task) => $task->start_at || $task->due_at || $task->completed_at)
            ->values();

        if ($tasks->isEmpty()) {
            return $this->emptyPayload($event);
        }

        $eventStart = $event->start_date?->copy()->startOfDay();
        $eventEnd = $event->end_date?->copy()->endOfDay();

        $taskDates = $tasks
            ->flatMap(fn (Task $task) => array_filter([$task->start_at, $task->due_at, $task->completed_at]))
            ->sort()
            ->values();

        $start = collect([$eventStart, $taskDates->first()])->filter()->min() ?? now()->startOfDay();
        $end = collect([$eventEnd, $taskDates->last()])->filter()->max() ?? $start->copy()->addDays(7);

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        if ($end->lt($start)) {
            $end = $start->copy()->endOfDay();
        }

        $days = max(1, (int) $start->diffInDays($end) + 1);
        $totalWeight = (float) $tasks->sum(fn (Task $task) => max(0.01, (float) $task->weight));

        if ($totalWeight <= 0) {
            $totalWeight = max(1, $tasks->count());
        }

        $prepared = $tasks->map(function (Task $task) use ($start, $eventStart) {
            $weight = max(0.01, (float) $task->weight);
            $plannedStart = ($task->start_at ?? $eventStart ?? $start)->copy()->startOfDay();
            $plannedEnd = ($task->due_at ?? $task->start_at ?? $plannedStart)->copy()->endOfDay();

            if ($plannedEnd->lt($plannedStart)) {
                $plannedEnd = $plannedStart->copy()->endOfDay();
            }

            $completedAt = $task->completed_at
                ?? ($task->status === 'done' ? $task->updated_at : null);

            return [
                'weight' => $weight,
                'start' => $plannedStart,
                'end' => $plannedEnd,
                'completed' => $completedAt?->copy()->endOfDay(),
            ];
        });

        $daily = [];
        $today = now()->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $plannedWeight = 0.0;
            $actualWeight = 0.0;

            foreach ($prepared as $task) {
                $plannedWeight += $task['weight'] * $this->plannedRatio($day, $task['start'], $task['end']);

                if ($task['completed'] && $task['completed']->lte($day)) {
                    $actualWeight += $task['weight'];
                }
            }

            $daily[] = [
                'date' => $day->copy(),
                'label' => $day->translatedFormat('d M'),
                'planned' => round($plannedWeight / $totalWeight * 100, 2),
                'actual' => round($actualWeight / $totalWeight * 100, 2),
            ];
        }

        $plannedToday = 0.0;

        foreach ($daily as $row) {
            if ($row['date']->lte($today)) {
                $plannedToday = $row['planned'];
            }
        }

        if ($today->lt($start)) {
            $plannedToday = 0.0;
        } elseif ($today->gt($end)) {
            $plannedToday = 100.0;
        }

        $current = round((float) $event->progress(), 1);
        $completion = round((float) $daily[count($daily) - 1]['actual'], 1);
        $sampled = $this->sample($daily);

        return [
            'labels' => array_column($sampled, 'label'),
            'planned' => array_column($sampled, 'planned'),
            'actual' => array_column($sampled, 'actual'),
            'current' => $completion,
            'event_progress' => $current,
            'planned_today' => round($plannedToday, 1),
            'variance' => round($completion - $plannedToday, 1),
            'done' => $tasks->where('status', 'done')->count(),
            'total' => $tasks->count(),
            'range' => $start->format('d M Y').' — '.$end->format('d M Y'),
            'empty' => false,
        ];
    }

    private function plannedRatio(CarbonInterface $day, CarbonInterface $start, CarbonInterface $end): float
    {
        if ($day->lt($start)) {
            return 0.0;
        }

        if ($day->gt($end)) {
            return 1.0;
        }

        $span = max(1, (int) $start->diffInDays($end) + 1);
        $elapsed = (int) $start->diffInDays($day) + 1;

        return min(1.0, max(0.0, $elapsed / $span));
    }

    /**
     * Keep the chart readable on long events without losing the trend.
     *
     * @param  array<int, array<string, mixed>>  $daily
     * @return array<int, array<string, mixed>>
     */
    private function sample(array $daily): array
    {
        $count = count($daily);

        if ($count <= 75) {
            return $daily;
        }

        $step = (int) ceil($count / 60);
        $sampled = [];

        foreach ($daily as $index => $row) {
            if ($index % $step === 0 || $index === $count - 1) {
                $sampled[] = $row;
            }
        }

        return $sampled;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(Event $event): array
    {
        return [
            'labels' => [],
            'planned' => [],
            'actual' => [],
            'current' => round((float) $event->progress(), 1),
            'event_progress' => round((float) $event->progress(), 1),
            'planned_today' => 0.0,
            'variance' => 0.0,
            'done' => 0,
            'total' => 0,
            'range' => null,
            'empty' => true,
        ];
    }
}
