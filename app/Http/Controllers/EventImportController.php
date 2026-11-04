<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\Event;
use App\Models\PlanningItem;
use App\Models\ScheduleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EventImportController extends Controller
{
    use AuthorizesEvents;

    public function importRundown(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);

        $rows = $this->rows($request);
        $header = array_shift($rows);
        $index = array_flip($header);
        $find = fn (array $names) => $this->findColumn($index, $names);

        $date = $find(['tanggal', 'date']);
        $start = $find(['mulai', 'start', 'start_time']);
        $end = $find(['selesai', 'end', 'end_time']);
        $activity = $find(['aktivitas', 'activity', 'kegiatan']);
        $pic = $find(['pic', 'penanggung_jawab']);
        $location = $find(['lokasi', 'location']);
        $category = $find(['kategori', 'category']);
        $target = $find(['target_peserta', 'target']);
        $actual = $find(['realisasi_peserta', 'actual']);
        $status = $find(['status']);
        $notes = $find(['catatan', 'notes']);

        if ($date === null || $activity === null) {
            return back()->withErrors(['file' => 'Header wajib: tanggal dan aktivitas.']);
        }

        $created = 0;
        $order = (int) $event->scheduleItems()->max('sort_order');
        foreach ($rows as $row) {
            $name = trim((string) ($row[$activity] ?? ''));
            if ($name === '') {
                continue;
            }

            $dateValue = $this->dateValue($row[$date] ?? null);
            if (! $dateValue) {
                continue;
            }

            $startValue = $this->timeValue($start !== null ? ($row[$start] ?? null) : null);
            $endValue = $this->timeValue($end !== null ? ($row[$end] ?? null) : null);

            ScheduleItem::create([
                'event_id' => $event->id,
                'date' => $dateValue,
                'start_time' => $startValue,
                'end_time' => $endValue,
                'activity' => $name,
                'pic' => $pic !== null ? trim((string) ($row[$pic] ?? '')) : null,
                'location' => $location !== null ? trim((string) ($row[$location] ?? '')) : null,
                'category' => $category !== null ? (trim((string) ($row[$category] ?? '')) ?: 'activity') : 'activity',
                'target_participants' => $target !== null ? (int) ($row[$target] ?? 0) : 0,
                'actual_participants' => $actual !== null ? (int) ($row[$actual] ?? 0) : 0,
                'status' => $status !== null && in_array(trim((string) ($row[$status] ?? '')), ['planned', 'ongoing', 'done', 'cancel'], true)
                    ? trim((string) $row[$status]) : 'planned',
                'notes' => $notes !== null ? trim((string) ($row[$notes] ?? '')) : null,
                'sort_order' => ++$order,
            ]);
            $created++;
        }

        return back()->with('success', "Import rundown selesai: {$created} baris.");
    }

    public function importPlanning(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:10240']]);

        $rows = $this->rows($request);
        $header = array_shift($rows);
        $index = array_flip($header);
        $find = fn (array $names) => $this->findColumn($index, $names);

        $aspect = $find(['aspek', 'aspect']);
        $description = $find(['deskripsi', 'uraian', 'description']);
        $target = $find(['target']);
        $pic = $find(['pic', 'penanggung_jawab']);
        $deadline = $find(['deadline', 'tanggal']);
        $status = $find(['status']);
        $notes = $find(['catatan', 'notes']);

        if ($aspect === null || $description === null) {
            return back()->withErrors(['file' => 'Header wajib: aspek dan deskripsi.']);
        }

        $created = 0;
        $order = (int) $event->planningItems()->max('sort_order');
        foreach ($rows as $row) {
            $aspectValue = trim((string) ($row[$aspect] ?? ''));
            $descriptionValue = trim((string) ($row[$description] ?? ''));
            if ($aspectValue === '' || $descriptionValue === '') {
                continue;
            }

            PlanningItem::create([
                'event_id' => $event->id,
                'aspect' => $aspectValue,
                'description' => $descriptionValue,
                'target' => $target !== null ? trim((string) ($row[$target] ?? '')) : null,
                'pic' => $pic !== null ? trim((string) ($row[$pic] ?? '')) : null,
                'deadline' => $deadline !== null ? $this->dateValue($row[$deadline] ?? null) : null,
                'status' => $status !== null && in_array(trim((string) ($row[$status] ?? '')), ['open', 'done', 'cancel'], true)
                    ? trim((string) $row[$status]) : 'open',
                'notes' => $notes !== null ? trim((string) ($row[$notes] ?? '')) : null,
                'sort_order' => ++$order,
            ]);
            $created++;
        }

        return back()->with('success', "Import planning selesai: {$created} baris.");
    }

    private function rows(Request $request): array
    {
        $sheet = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $rows = array_values($rows);

        if (count($rows) < 2) {
            abort(422, 'File XLSX kosong.');
        }

        $rows[0] = array_map(fn ($value) => strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $value))), $rows[0]);

        return $rows;
    }

    private function findColumn(array $index, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($index[$name])) {
                return $index[$name];
            }
        }

        return null;
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
