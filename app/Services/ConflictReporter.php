<?php

namespace App\Services;

use App\Models\ConflictReport;
use Illuminate\Database\Eloquent\Model;

class ConflictReporter
{
    public function record(Model $model, mixed $expected, ?array $attempted = null): ?ConflictReport
    {
        try {
            $report = ConflictReport::create([
                'user_id' => auth()->id(),
                'event_id' => $this->eventId($model),
                'model_type' => class_basename($model),
                'model_id' => $model->getKey(),
                'expected_version' => $expected === null || $expected === '' ? null : (int) $expected,
                'actual_version' => (int) ($model->lock_version ?? 0),
                'base_snapshot' => $this->baseSnapshot(),
                'attempted' => $this->sanitize($attempted ?? request()->all()),
                'snapshot' => $this->snapshot($model),
                'route' => request()->route()?->getName(),
                'url' => substr((string) (request()->headers->get('referer') ?: request()->fullUrl()), 0, 255),
            ]);

            if (request()->hasSession()) {
                request()->session()->flash('conflict_id', $report->id);
            }

            return $report;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function eventId(Model $model): ?int
    {
        return match (true) {
            $model instanceof \App\Models\Event => $model->id,
            $model instanceof \App\Models\TaskChecklist => $model->task?->event_id,
            default => $model->getAttribute('event_id') ?? $model->event?->id,
        };
    }

    private function sanitize(array $input): array
    {
        return collect($input)
            ->reject(fn ($value, $key) => in_array($key, ['_token', '_method', 'password', 'password_confirmation', 'photo', 'receipt', 'proof', 'base_snapshot', 'base_version', 'lock_version'], true))
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->take(60)
            ->all();
    }

    private function snapshot(Model $model): array
    {
        return collect($model->getAttributes())
            ->except(['created_at', 'updated_at', 'password', 'remember_token'])
            ->map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value))
            ->all();
    }

    private function baseSnapshot(): ?array
    {
        $raw = request()->input('base_snapshot');

        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return collect($decoded)
            ->filter(fn ($value) => is_scalar($value) || $value === null)
            ->take(60)
            ->all();
    }
}
