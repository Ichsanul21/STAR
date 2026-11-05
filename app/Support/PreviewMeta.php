<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Expense;
use App\Models\MediaItem;
use App\Models\ReportVersion;
use App\Models\TaskUpdate;

class PreviewMeta
{
    public static function media(MediaItem $media): array
    {
        return [
            'groups' => self::filter([
                self::group('File', [
                    ['Nama', $media->original_name],
                    ['Tipe', $media->mime],
                    ['Ukuran', self::bytes($media->size)],
                    ['Dimensi', $media->width && $media->height ? $media->width.' × '.$media->height.' px' : null],
                ]),
                self::group('Waktu & lokasi', [
                    ['Diambil', $media->captured_at?->format('d/m/Y H:i:s')],
                    ['Alamat', $media->address],
                    ['Akurasi GPS', $media->gps_accuracy !== null ? round((float) $media->gps_accuracy, 1).' m' : null],
                ], $media->gps_lat !== null && $media->gps_lng !== null ? [
                    'lat' => (float) $media->gps_lat,
                    'lng' => (float) $media->gps_lng,
                    'label' => $media->address ?: $media->caption,
                ] : null),
                self::group('Unggahan', [
                    ['Oleh', $media->uploader?->name],
                    ['Role', $media->uploader?->roleLabel()],
                    ['Status', $media->status],
                ]),
                self::group('Konteks', [
                    ['Event', $media->event?->name],
                    ['Fase', $media->phase?->name],
                    ['Task', $media->task?->name],
                    ['Caption', $media->caption],
                ]),
            ]),
        ];
    }

    public static function attachment(Attachment $attachment): array
    {
        return [
            'groups' => self::filter([
                self::group('File', [
                    ['Nama', $attachment->original_name],
                    ['Tipe', $attachment->mime],
                    ['Ukuran', self::bytes($attachment->size)],
                    ['Kategori', $attachment->category],
                ]),
                self::group('Unggahan', [
                    ['Oleh', $attachment->uploader?->name],
                    ['Role', $attachment->uploader?->roleLabel()],
                    ['Tanggal', $attachment->created_at?->format('d/m/Y H:i')],
                ]),
                self::group('Konteks', [
                    ['Event', $attachment->event?->name],
                ]),
            ]),
        ];
    }

    public static function expense(Expense $expense): array
    {
        return [
            'groups' => self::filter([
                self::group('File', [
                    ['Nama', $expense->receipt_path ? basename($expense->receipt_path) : null],
                    ['Tipe', $expense->receipt_path ? strtoupper(pathinfo($expense->receipt_path, PATHINFO_EXTENSION)) : null],
                ]),
                self::group('Expense', [
                    ['Deskripsi', $expense->description],
                    ['Kategori', $expense->category],
                    ['Vendor', $expense->vendor],
                    ['Tanggal', $expense->expense_date?->format('d/m/Y')],
                    ['Amount', 'Rp'.number_format((float) $expense->amount, 0, ',', '.')],
                    ['Status', $expense->status],
                ]),
                self::group('Unggahan', [
                    ['Oleh', $expense->creator?->name],
                    ['Role', $expense->creator?->roleLabel()],
                ]),
                self::group('Konteks', [
                    ['Event', $expense->event?->name],
                    ['Budget item', $expense->budgetItem?->description],
                ]),
            ]),
        ];
    }

    public static function taskUpdate(TaskUpdate $update): array
    {
        return [
            'groups' => self::filter([
                self::group('File', [
                    ['Tipe', $update->photo_path ? strtoupper(pathinfo($update->photo_path, PATHINFO_EXTENSION)) : null],
                ]),
                self::group('Update', [
                    ['Tipe', $update->type],
                    ['Dari', $update->from_status],
                    ['Ke', $update->to_status],
                    ['Catatan', $update->note],
                    ['Waktu', $update->created_at?->format('d/m/Y H:i')],
                ]),
                self::group('Oleh', [
                    ['User', $update->user?->name],
                    ['Role', $update->user?->roleLabel()],
                ]),
                self::group('Konteks', [
                    ['Event', $update->event?->name],
                    ['Fase', $update->phase?->name],
                    ['Task', $update->task?->name],
                ]),
            ]),
        ];
    }

    public static function report(ReportVersion $report): array
    {
        return [
            'groups' => self::filter([
                self::group('Laporan', [
                    ['No. Laporan', $report->report_number ?: '—'],
                    ['Versi', 'v'.$report->version],
                    ['Status', $report->status],
                    ['Dibuat', $report->created_at?->format('d/m/Y H:i')],
                    ['Published', $report->published_at?->format('d/m/Y H:i')],
                ]),
                self::group('Dibuat oleh', [
                    ['User', $report->generator?->name],
                    ['Role', $report->generator?->roleLabel()],
                ]),
                self::group('Konteks', [
                    ['Event', $report->event?->name],
                    ['Dealer', $report->event?->dealer?->name],
                ]),
            ]),
        ];
    }

    private static function group(string $title, array $rows, ?array $map = null): array
    {
        return [
            'title' => $title,
            'rows' => array_values(array_filter($rows, fn ($row) => isset($row[1]) && $row[1] !== '' && $row[1] !== null)),
            'map' => $map,
        ];
    }

    private static function filter(array $groups): array
    {
        return array_values(array_filter($groups, fn ($group) => $group['rows'] !== [] || ! empty($group['map'])));
    }

    private static function bytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $bytes;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, $index === 0 ? 0 : 1, ',', '.').' '.$units[$index];
    }
}
