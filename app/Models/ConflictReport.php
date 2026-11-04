<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConflictReport extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'model_type', 'model_id', 'expected_version', 'actual_version',
        'base_snapshot', 'attempted', 'snapshot', 'merge_result', 'route', 'url',
        'resolved_at', 'resolved_by', 'merged_at', 'merged_by',
    ];

    protected $casts = [
        'base_snapshot' => 'array',
        'attempted' => 'array',
        'snapshot' => 'array',
        'merge_result' => 'array',
        'resolved_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function merger()
    {
        return $this->belongsTo(User::class, 'merged_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function modelLabel(): string
    {
        return match ($this->model_type) {
            'Expense' => 'Expense',
            'Task' => 'Task',
            'TaskChecklist' => 'Checklist',
            'Event' => 'Event',
            'ReportVersion' => 'Report',
            'ClientInvoice' => 'Invoice',
            'BudgetVersion' => 'Budget',
            default => $this->model_type,
        };
    }

    public function diffRows(): array
    {
        $attempted = $this->attempted ?? [];
        $snapshot = $this->snapshot ?? [];

        $keys = collect($attempted)->keys()
            ->reject(fn ($key) => in_array($key, ['_token', '_method', 'password', 'password_confirmation', 'conflict_id'], true))
            ->merge(collect($snapshot)->keys())
            ->unique()
            ->values();

        return $keys->map(function ($key) use ($attempted, $snapshot) {
            $submitted = $attempted[$key] ?? null;
            $current = $snapshot[$key] ?? null;

            return [
                'field' => $key,
                'submitted' => is_bool($submitted) ? ($submitted ? 'ya' : 'tidak') : (string) $submitted,
                'current' => is_bool($current) ? ($current ? 'ya' : 'tidak') : (string) $current,
                'differs' => ! $this->valuesMatch($submitted, $current),
            ];
        })->all();
    }

    private function valuesMatch(mixed $submitted, mixed $current): bool
    {
        $left = trim((string) $submitted);
        $right = trim((string) $current);

        if ($left === $right) {
            return true;
        }

        if ($left === '' && $right === '') {
            return true;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.00001;
        }

        foreach ([' 00:00:00', 'T00:00:00.000000Z'] as $suffix) {
            if (str_starts_with($right, $left) && str_ends_with($right, $suffix)) {
                return true;
            }
            if (str_starts_with($left, $right) && str_ends_with($left, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
