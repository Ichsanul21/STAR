<?php

namespace App\Services;

use App\Models\ConflictReport;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConflictMergeService
{
    private const MODELS = [
        'Event' => [
            'fields' => ['name', 'type', 'location', 'city', 'start_date', 'end_date', 'capture_mode', 'target_visitors', 'target_leads', 'target_units', 'contract_value', 'status', 'description'],
            'dates' => ['start_date', 'end_date'],
            'numbers' => ['target_visitors', 'target_leads', 'target_units', 'contract_value'],
            'authorize' => 'manage',
        ],
        'Task' => [
            'fields' => ['name', 'description', 'priority', 'assigned_to', 'start_at', 'due_at'],
            'dates' => ['start_at', 'due_at'],
            'numbers' => [],
            'authorize' => 'manage',
        ],
        'Expense' => [
            'fields' => ['expense_date', 'category', 'description', 'qty', 'unit_price', 'vendor', 'payment_method', 'paid_by', 'notes', 'budget_item_id'],
            'dates' => ['expense_date'],
            'numbers' => ['qty', 'unit_price', 'budget_item_id'],
            'authorize' => 'expense',
        ],
    ];

    private const LABELS = [
        'name' => 'Nama',
        'type' => 'Jenis',
        'location' => 'Lokasi',
        'city' => 'Kota',
        'start_date' => 'Tanggal mulai',
        'end_date' => 'Tanggal selesai',
        'capture_mode' => 'Capture mode',
        'target_visitors' => 'Target pengunjung',
        'target_leads' => 'Target lead',
        'target_units' => 'Target unit',
        'contract_value' => 'Contract value',
        'status' => 'Status',
        'description' => 'Deskripsi',
        'priority' => 'Prioritas',
        'assigned_to' => 'Assignee',
        'start_at' => 'Mulai',
        'due_at' => 'Jatuh tempo',
        'expense_date' => 'Tanggal expense',
        'category' => 'Kategori',
        'qty' => 'Qty',
        'unit_price' => 'Harga satuan',
        'vendor' => 'Vendor',
        'payment_method' => 'Metode bayar',
        'paid_by' => 'Dibayar oleh',
        'notes' => 'Catatan',
        'budget_item_id' => 'Budget item',
    ];

    public function __construct(
        private ActivityLogger $logger,
        private ConflictReporter $reporter
    ) {
    }

    public function isMergeable(ConflictReport $report): bool
    {
        return isset(self::MODELS[$report->model_type])
            && is_array($report->base_snapshot)
            && $report->base_snapshot !== [];
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function plan(ConflictReport $report): ?array
    {
        if (! $this->isMergeable($report)) {
            return null;
        }

        $config = self::MODELS[$report->model_type];
        $base = $report->base_snapshot ?? [];
        $submitted = $report->attempted ?? [];
        $server = $report->snapshot ?? [];

        $rows = [];

        foreach ($config['fields'] as $field) {
            $baseValue = $base[$field] ?? null;
            $serverValue = $server[$field] ?? null;
            $hasSubmitted = array_key_exists($field, $submitted);
            $submittedValue = $hasSubmitted ? $submitted[$field] : $baseValue;

            $userChanged = $hasSubmitted && ! $this->equal($submittedValue, $baseValue);
            $serverChanged = ! $this->equal($serverValue, $baseValue);

            if ($userChanged && $serverChanged) {
                $resolution = $this->equal($submittedValue, $serverValue) ? 'same' : 'manual';
            } elseif ($userChanged) {
                $resolution = 'user';
            } else {
                $resolution = 'server';
            }

            $rows[] = [
                'field' => $field,
                'label' => self::LABELS[$field] ?? $field,
                'base' => $this->display($baseValue),
                'submitted' => $hasSubmitted ? $this->display($submittedValue) : null,
                'server' => $this->display($serverValue),
                'raw_submitted' => $submittedValue,
                'raw_server' => $serverValue,
                'user_changed' => $userChanged,
                'server_changed' => $serverChanged,
                'resolution' => $resolution,
                'value' => $resolution === 'user' ? $submittedValue : $serverValue,
                'disputed' => $resolution === 'manual',
            ];
        }

        return $rows;
    }

    public function apply(ConflictReport $report, array $decisions, User $user): ConflictReport
    {
        $plan = $this->plan($report);

        if ($plan === null) {
            throw ValidationException::withMessages(['merge' => 'Konflik ini tidak punya base snapshot, jadi belum bisa di-merge otomatis.']);
        }

        $config = self::MODELS[$report->model_type];
        $model = $this->resolveModel($report);

        if (! $model) {
            throw ValidationException::withMessages(['merge' => 'Data sumber sudah tidak ada. Tandai konflik selesai setelah dicek.']);
        }

        $this->authorize($model, $config['authorize'], $user);

        $updates = [];

        foreach ($plan as $row) {
            if (! $row['disputed']) {
                $updates[$row['field']] = $row['value'];

                continue;
            }

            $choice = $decisions[$row['field']] ?? null;

            if (! in_array($choice, ['user', 'server'], true)) {
                throw ValidationException::withMessages([
                    'decisions' => 'Pilih nilai untuk field '.$row['label'].'.',
                ]);
            }

            $updates[$row['field']] = $choice === 'user' ? $row['raw_submitted'] : $row['raw_server'];
        }

        $this->validateUpdates($report->model_type, $model, $updates);

        return DB::transaction(function () use ($report, $model, $updates, $user, $config) {
            $fresh = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $fresh->lock_version !== (int) $report->actual_version) {
                $this->reporter->record($fresh, $report->actual_version);

                throw ValidationException::withMessages(['merge' => 'Data sudah berubah lagi sejak konflik dicatat. Muat ulang halaman dan coba merge kembali.']);
            }

            $before = [];
            $after = [];

            foreach ($updates as $field => $value) {
                if ($this->equal($fresh->getAttribute($field), $value)) {
                    continue;
                }

                $before[$field] = $fresh->getAttribute($field);
                $after[$field] = $value;
            }

            if ($after !== []) {
                $fresh->forceFill($this->castValues($config, $after));
                $fresh->lock_version = (int) $fresh->lock_version + 1;
                $fresh->save();
            }

            $report->update([
                'merge_result' => ['before' => $before, 'after' => $after],
                'merged_at' => now(),
                'merged_by' => $user->id,
                'resolved_at' => now(),
                'resolved_by' => $user->id,
            ]);

            $this->logger->log(
                'conflict.merged',
                'Konflik '.$report->modelLabel().' #'.$report->model_id.' diselesaikan dengan merge',
                $model instanceof Event ? $model : $model->event,
                $fresh,
                ['conflict_id' => $report->id, 'fields' => array_keys($after)],
                $before,
                $after,
                'info',
                $user->id
            );

            return $report->fresh();
        });
    }

    private function validateUpdates(string $type, Model $model, array $updates): void
    {
        if ($type === 'Event') {
            if (isset($updates['status']) && ! in_array($updates['status'], ['draft', 'active', 'closed'], true)) {
                throw ValidationException::withMessages(['status' => 'Status event tidak valid.']);
            }

            if (isset($updates['type']) && ! array_key_exists($updates['type'], config('star_options.event_types', []))) {
                throw ValidationException::withMessages(['type' => 'Jenis event tidak valid.']);
            }

            if (isset($updates['capture_mode']) && ! in_array($updates['capture_mode'], ['camera_only', 'upload_or_camera'], true)) {
                throw ValidationException::withMessages(['capture_mode' => 'Capture mode tidak valid.']);
            }

            if (! empty($updates['start_date']) && ! empty($updates['end_date']) && strtotime($updates['end_date']) < strtotime($updates['start_date'])) {
                throw ValidationException::withMessages(['end_date' => 'Tanggal selesai harus setelah tanggal mulai.']);
            }
        }

        if ($type === 'Task') {
            if (isset($updates['priority']) && ! in_array($updates['priority'], ['low', 'medium', 'high', 'urgent'], true)) {
                throw ValidationException::withMessages(['priority' => 'Prioritas tidak valid.']);
            }

            if (! empty($updates['assigned_to']) && ! User::whereKey($updates['assigned_to'])->exists()) {
                throw ValidationException::withMessages(['assigned_to' => 'Assignee tidak ditemukan.']);
            }

            if (! empty($updates['start_at']) && ! empty($updates['due_at']) && strtotime($updates['due_at']) < strtotime($updates['start_at'])) {
                throw ValidationException::withMessages(['due_at' => 'Due date harus setelah start date.']);
            }
        }

        if ($type === 'Expense') {
            if (isset($updates['payment_method']) && ! in_array($updates['payment_method'], ['cash', 'transfer', 'card', 'petty_cash'], true)) {
                throw ValidationException::withMessages(['payment_method' => 'Metode pembayaran tidak valid.']);
            }

            if (! empty($updates['budget_item_id'])) {
                $valid = \App\Models\BudgetItem::query()
                    ->whereKey($updates['budget_item_id'])
                    ->whereHas('version', fn ($query) => $query->where('event_id', $model->event_id))
                    ->exists();

                if (! $valid) {
                    throw ValidationException::withMessages(['budget_item_id' => 'Budget item bukan milik event ini.']);
                }
            }
        }
    }

    private function castValues(array $config, array $values): array
    {
        foreach ($config['dates'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $values[$field] ? Carbon::parse($values[$field]) : null;
            }
        }

        foreach ($config['numbers'] as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = $values[$field] === null || $values[$field] === '' ? null : (float) $values[$field];
            }
        }

        return $values;
    }

    private function resolveModel(ConflictReport $report): ?Model
    {
        return match ($report->model_type) {
            'Event' => Event::find($report->model_id),
            'Task' => Task::find($report->model_id),
            'Expense' => Expense::find($report->model_id),
            default => null,
        };
    }

    private function authorize(Model $model, string $ability, User $user): void
    {
        $event = $model instanceof Event ? $model : $model->event;

        $allowed = match ($ability) {
            'manage' => $user->canManageEvent($event),
            'expense' => $user->canCreateExpense($event) || $user->canApproveExpense($event),
            default => false,
        };

        abort_unless($allowed, 403);
    }

    private function equal(mixed $left, mixed $right): bool
    {
        if ($left === null || $left === '') {
            $left = null;
        }

        if ($right === null || $right === '') {
            $right = null;
        }

        if ($left === null && $right === null) {
            return true;
        }

        if ($left === null || $right === null) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) < 0.00001;
        }

        $leftDate = strtotime((string) $left);
        $rightDate = strtotime((string) $right);

        if ($leftDate !== false && $rightDate !== false && strlen((string) $left) >= 8 && strlen((string) $right) >= 8) {
            return $leftDate === $rightDate;
        }

        return trim((string) $left) === trim((string) $right);
    }

    private function display(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        return (string) $value;
    }
}
