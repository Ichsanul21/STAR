<?php

namespace App\Services;

use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\ImportBatch;
use App\Models\PlanningItem;
use App\Models\ScheduleItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class XlsxImportService
{
    public function parse(string $absolutePath): array
    {
        $sheet = IOFactory::load($absolutePath)->getActiveSheet();
        $rows = array_values($sheet->toArray(null, true, true, true));

        if ($rows === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($rows);
        $headers = [];
        $labels = [];
        foreach ($headerRow as $column => $value) {
            $headers[$column] = $this->normalize($value);
            $labels[$column] = trim((string) $value);
        }

        return ['headers' => $headers, 'labels' => $labels, 'rows' => $rows];
    }

    public function targets(string $type): array
    {
        return match ($type) {
            'rab' => [
                'category' => 'Kategori',
                'description' => 'Uraian',
                'qty' => 'Volume / Qty',
                'unit' => 'Satuan',
                'unit_price' => 'Harga Satuan',
                'planned_amount' => 'Jumlah Rencana (opsional)',
            ],
            'rundown' => [
                'date' => 'Tanggal',
                'start_time' => 'Mulai',
                'end_time' => 'Selesai',
                'activity' => 'Aktivitas',
                'pic' => 'PIC',
                'location' => 'Lokasi',
                'category' => 'Kategori',
                'target_participants' => 'Target Peserta',
                'actual_participants' => 'Realisasi Peserta',
                'status' => 'Status',
                'notes' => 'Catatan',
            ],
            'planning' => [
                'aspect' => 'Aspek',
                'description' => 'Deskripsi',
                'target' => 'Target',
                'pic' => 'PIC',
                'deadline' => 'Deadline',
                'status' => 'Status',
                'notes' => 'Catatan',
            ],
            default => [],
        };
    }

    public function suggest(string $type, array $headers): array
    {
        $aliases = [
            'category' => ['kategori', 'category'],
            'description' => ['uraian', 'deskripsi', 'description', 'item'],
            'qty' => ['volume', 'qty', 'quantity'],
            'unit' => ['satuan', 'unit'],
            'unit_price' => ['harga_satuan', 'unit_price', 'harga'],
            'planned_amount' => ['jumlah_rencana', 'jumlah', 'total'],
            'date' => ['tanggal', 'date'],
            'start_time' => ['mulai', 'start', 'start_time'],
            'end_time' => ['selesai', 'end', 'end_time'],
            'activity' => ['aktivitas', 'activity', 'kegiatan'],
            'pic' => ['pic', 'penanggung_jawab'],
            'location' => ['lokasi', 'location'],
            'target_participants' => ['target_peserta', 'target'],
            'actual_participants' => ['realisasi_peserta', 'actual'],
            'status' => ['status'],
            'notes' => ['catatan', 'notes'],
            'aspect' => ['aspek', 'aspect'],
            'target' => ['target'],
            'deadline' => ['deadline', 'tanggal'],
        ];

        $mapping = [];
        foreach (array_keys($this->targets($type)) as $target) {
            foreach ($aliases[$target] ?? [$target] as $alias) {
                $column = array_search($alias, $headers, true);
                if ($column !== false) {
                    $mapping[$target] = $column;
                    break;
                }
            }
        }

        return $mapping;
    }

    public function import(ImportBatch $batch, array $mapping): int
    {
        $absolute = Storage::disk('local')->path($batch->path);
        $parsed = $this->parse($absolute);
        $rows = $parsed['rows'];

        return match ($batch->type) {
            'rab' => $this->importRab($batch, $rows, $mapping),
            'rundown' => $this->importRundown($batch, $rows, $mapping),
            'planning' => $this->importPlanning($batch, $rows, $mapping),
            default => throw ValidationException::withMessages(['type' => 'Tipe import tidak dikenal.']),
        };
    }

    private function importRab(ImportBatch $batch, array $rows, array $mapping): int
    {
        $this->requireMapping($mapping, ['category', 'description', 'qty', 'unit']);

        $version = (int) $batch->event->budgetVersions()->where('stage', 'planning')->max('version') + 1;
        $budget = BudgetVersion::create([
            'event_id' => $batch->event_id,
            'stage' => 'planning',
            'version' => $version,
            'status' => 'draft',
            'notes' => 'Imported via mapping',
        ]);

        $created = 0;
        foreach ($rows as $row) {
            $description = trim((string) $this->value($row, $mapping, 'description'));
            if ($description === '') {
                continue;
            }

            $qty = (float) $this->value($row, $mapping, 'qty') ?: 1;
            $unitPrice = (float) $this->value($row, $mapping, 'unit_price');
            $planned = $this->value($row, $mapping, 'planned_amount');
            $amount = $planned !== null && $planned !== '' ? (float) $planned : $qty * $unitPrice;

            BudgetItem::create([
                'budget_version_id' => $budget->id,
                'category' => trim((string) $this->value($row, $mapping, 'category')) ?: 'Lainnya',
                'description' => $description,
                'qty' => $qty,
                'unit' => trim((string) $this->value($row, $mapping, 'unit')) ?: 'unit',
                'unit_price' => $unitPrice,
                'planned_amount' => $amount,
                'sort_order' => $created,
            ]);
            $created++;
        }

        app(FinanceService::class)->recalcVersion($budget);

        return $created;
    }

    private function importRundown(ImportBatch $batch, array $rows, array $mapping): int
    {
        $this->requireMapping($mapping, ['date', 'activity']);

        $created = 0;
        $order = (int) $batch->event->scheduleItems()->max('sort_order');
        foreach ($rows as $row) {
            $activity = trim((string) $this->value($row, $mapping, 'activity'));
            $date = $this->dateValue($this->value($row, $mapping, 'date'));
            if ($activity === '' || ! $date) {
                continue;
            }

            $status = trim((string) $this->value($row, $mapping, 'status')) ?: 'planned';

            ScheduleItem::create([
                'event_id' => $batch->event_id,
                'date' => $date,
                'start_time' => $this->timeValue($this->value($row, $mapping, 'start_time')),
                'end_time' => $this->timeValue($this->value($row, $mapping, 'end_time')),
                'activity' => $activity,
                'pic' => $this->value($row, $mapping, 'pic'),
                'location' => $this->value($row, $mapping, 'location'),
                'category' => $this->value($row, $mapping, 'category') ?: 'activity',
                'target_participants' => (int) $this->value($row, $mapping, 'target_participants'),
                'actual_participants' => (int) $this->value($row, $mapping, 'actual_participants'),
                'status' => in_array($status, ['planned', 'ongoing', 'done', 'cancel'], true) ? $status : 'planned',
                'notes' => $this->value($row, $mapping, 'notes'),
                'sort_order' => ++$order,
            ]);
            $created++;
        }

        return $created;
    }

    private function importPlanning(ImportBatch $batch, array $rows, array $mapping): int
    {
        $this->requireMapping($mapping, ['aspect', 'description']);

        $created = 0;
        $order = (int) $batch->event->planningItems()->max('sort_order');
        foreach ($rows as $row) {
            $aspect = trim((string) $this->value($row, $mapping, 'aspect'));
            $description = trim((string) $this->value($row, $mapping, 'description'));
            if ($aspect === '' || $description === '') {
                continue;
            }

            $status = trim((string) $this->value($row, $mapping, 'status')) ?: 'open';

            PlanningItem::create([
                'event_id' => $batch->event_id,
                'aspect' => $aspect,
                'description' => $description,
                'target' => $this->value($row, $mapping, 'target'),
                'pic' => $this->value($row, $mapping, 'pic'),
                'deadline' => $this->dateValue($this->value($row, $mapping, 'deadline')),
                'status' => in_array($status, ['open', 'done', 'cancel'], true) ? $status : 'open',
                'notes' => $this->value($row, $mapping, 'notes'),
                'sort_order' => ++$order,
            ]);
            $created++;
        }

        return $created;
    }

    private function requireMapping(array $mapping, array $keys): void
    {
        foreach ($keys as $key) {
            if (empty($mapping[$key])) {
                throw ValidationException::withMessages(['mapping' => "Kolom wajib belum dipetakan: {$key}."]);
            }
        }
    }

    private function value(array $row, array $mapping, string $key): mixed
    {
        $column = $mapping[$key] ?? null;

        return $column !== null ? ($row[$column] ?? null) : null;
    }

    private function normalize(mixed $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $value)));
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            }

            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function timeValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('H:i:s');
            }

            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }
}
