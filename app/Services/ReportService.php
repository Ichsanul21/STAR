<?php

namespace App\Services;

use App\Models\Event;
use App\Models\ReportVersion;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportService
{
    public const SECTIONS = [
        'cover' => 'Cover',
        'summary' => 'Ringkasan Eksekutif',
        'progress' => 'Progress per Fase',
        'budget' => 'RAB vs Realisasi',
        'documentation' => 'Dokumentasi',
        'expenses' => 'Expense',
        'payments' => 'Client Payment',
        'tasks' => 'Task',
        'result' => 'Hasil Event',
        'evaluation' => 'Evaluasi & Rekomendasi',
        'signoff' => 'Sign-off',
    ];

    public function __construct(
        private FinanceService $finance,
        private NumberingService $numbering,
        private UiWatermarkService $watermark,
    ) {
    }

    public function generate(Event $event, User $user, array $narrative = []): ReportVersion
    {
        $event->load([
            'dealer', 'phases.taskGroups.tasks', 'tasks.assignee', 'budgetVersions.items',
            'expenses.budgetItem', 'invoices.payments', 'media.phase', 'visitors', 'result',
        ]);

        $version = ((int) $event->reports()->max('version')) + 1;
        $finance = $this->finance->eventFinancials($event);

        $snapshot = [
            'event' => [
                'code' => $event->code,
                'name' => $event->name,
                'dealer' => $event->dealer?->name,
                'location' => $event->location,
                'city' => $event->city,
                'start_date' => $event->start_date?->toDateString(),
                'end_date' => $event->end_date?->toDateString(),
            ],
            'progress' => $event->progress(),
            'finance' => $finance,
            'tasks' => [
                'total' => $event->tasks->count(),
                'done' => $event->tasks->where('status', 'done')->count(),
                'overdue' => $event->tasks->filter(fn ($t) => $t->due_at && $t->due_at->isPast() && $t->status !== 'done')->count(),
            ],
            'media' => $event->media->count(),
            'visitors' => $event->visitors->count(),
            'result' => $event->result?->toArray(),
            'watermark' => $this->watermark->payload($user, request()),
        ];

        $previousNarrative = $event->reports()->latest('version')->first()?->narrative ?? [];

        $report = ReportVersion::create([
            'event_id' => $event->id,
            'version' => $version,
            'report_number' => $this->numbering->next(
                'report',
                [
                    'event' => $event,
                    'event_code' => $event->code,
                ],
                fn (string $number) => ReportVersion::query()->where('report_number', $number)->exists(),
            ),
            'status' => 'draft',
            'snapshot' => $snapshot,
            'narrative' => array_merge([
                'summary' => '',
                'evaluation' => '',
                'recommendations' => '',
                'conclusion' => '',
            ], $previousNarrative, $narrative),
            'section_order' => array_keys(self::SECTIONS),
            'generated_by' => $user->id,
        ]);

        $pdfPath = "reports/{$event->id}/v{$version}.pdf";
        $xlsxPath = "reports/{$event->id}/v{$version}.xlsx";

        $this->writePdf($event, $report, $finance, $snapshot, $pdfPath);
        $this->writeXlsx($event, $report, $finance, $snapshot, $xlsxPath);

        $report->update([
            'pdf_path' => $pdfPath,
            'xlsx_path' => $xlsxPath,
            'checksum' => hash_file('sha256', Storage::disk('local')->path($pdfPath)),
        ]);

        return $report;
    }

    public function viewData(Event $event, ReportVersion $report, array $finance, array $snapshot): array
    {
        $photos = $event->media
            ->filter(fn ($m) => $m->displayPath() && Storage::disk('local')->exists($m->displayPath()))
            ->take(24)
            ->map(function ($media) {
                $absolute = Storage::disk('local')->path($media->displayPath());
                $mime = mime_content_type($absolute) ?: 'image/jpeg';

                return [
                    'caption' => $media->caption,
                    'phase' => $media->phase?->name,
                    'time' => $media->captured_at?->format('d/m/Y H:i'),
                    'data' => 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absolute)),
                ];
            })
            ->values();

        $sectionOrder = array_values(array_intersect($report->section_order ?? array_keys(self::SECTIONS), array_keys(self::SECTIONS)));

        $narrative = $report->narrative ?? [];
        $sanitizer = app(HtmlSanitizer::class);
        foreach (['summary', 'evaluation', 'recommendations', 'conclusion'] as $key) {
            $narrative[$key] = $sanitizer->clean($narrative[$key] ?? '');
        }
        $report->setAttribute('narrative', $narrative);

        return [
            'event' => $event,
            'report' => $report,
            'finance' => $finance,
            'snapshot' => $snapshot,
            'photos' => $photos,
            'tasks' => $event->tasks,
            'budgetItems' => $event->finalBudget?->items ?? $event->budgetVersions->first()?->items ?? collect(),
            'expenses' => $event->expenses,
            'invoices' => $event->invoices,
            'sectionOrder' => $sectionOrder ?: array_keys(self::SECTIONS),
            'sectionLabels' => self::SECTIONS,
            'watermark' => $snapshot['watermark'] ?? ['enabled' => false, 'line1' => '', 'line2' => ''],
        ];
    }

    private function writePdf(Event $event, ReportVersion $report, array $finance, array $snapshot, string $path): void
    {
        $html = view('reports.pdf', $this->viewData($event, $report, $finance, $snapshot))->render();

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        Storage::disk('local')->put($path, $dompdf->output());
    }

    private function writeXlsx(Event $event, ReportVersion $report, array $finance, array $snapshot, string $path): void
    {
        $book = new Spreadsheet();
        $book->removeSheetByIndex(0);

        $this->sheet($book, 'Ringkasan', [
            ['Kode Event', $event->code],
            ['Nama Event', $event->name],
            ['Dealer', $event->dealer?->name],
            ['Lokasi', $event->location],
            ['Tanggal', $event->start_date?->format('d/m/Y')],
            ['Progress', $event->progress().'%'],
            ['Budget Rencana', $finance['planned']],
            ['Budget Realisasi', $finance['actual']],
            ['Variance', $finance['variance']],
            ['Serapan', $finance['absorption'].'%'],
            ['Outstanding Client', $finance['outstanding']],
            ['Total Task', $snapshot['tasks']['total']],
            ['Task Selesai', $snapshot['tasks']['done']],
            ['Foto', $snapshot['media']],
            ['Pengunjung', $snapshot['visitors']],
            ['Ringkasan Naratif', $report->narrative['summary'] ?? ''],
            ['Evaluasi', $report->narrative['evaluation'] ?? ''],
            ['Rekomendasi', $report->narrative['recommendations'] ?? ''],
            ['Kesimpulan', $report->narrative['conclusion'] ?? ''],
        ]);

        $rabSheet = $book->createSheet();
        $rabSheet->setTitle('RAB');
        $rabSheet->fromArray(['Kategori', 'Uraian', 'Qty', 'Satuan', 'Harga Satuan', 'Rencana', 'Realisasi', 'Variance'], null, 'A1');
        $row = 2;
        $rabItems = $event->finalBudget?->items ?? $event->budgetVersions->first()?->items ?? collect();
        foreach ($rabItems as $item) {
            $rabSheet->fromArray([
                $item->category, $item->description, (float) $item->qty, $item->unit,
                (float) $item->unit_price, (float) $item->planned_amount, (float) $item->actual_amount,
                (float) $item->actual_amount - (float) $item->planned_amount,
            ], null, 'A'.$row++);
        }
        $this->styleHeader($rabSheet, 'A1:H1');

        $expenseSheet = $book->createSheet();
        $expenseSheet->setTitle('Expense');
        $expenseSheet->fromArray(['Tanggal', 'Kategori', 'Deskripsi', 'Vendor', 'Jumlah', 'Status'], null, 'A1');
        $row = 2;
        foreach ($event->expenses as $expense) {
            $expenseSheet->fromArray([
                $expense->expense_date?->format('d/m/Y'), $expense->category, $expense->description,
                $expense->vendor, (float) $expense->amount, $expense->status,
            ], null, 'A'.$row++);
        }
        $this->styleHeader($expenseSheet, 'A1:F1');

        $invoiceSheet = $book->createSheet();
        $invoiceSheet->setTitle('Client Payment');
        $invoiceSheet->fromArray(['Invoice', 'Termin', 'Jumlah', 'Jatuh Tempo', 'Dibayar', 'Sisa', 'Status'], null, 'A1');
        $row = 2;
        foreach ($event->invoices as $invoice) {
            $invoiceSheet->fromArray([
                $invoice->invoice_number, $invoice->term, (float) $invoice->amount,
                $invoice->due_date?->format('d/m/Y'), $invoice->paidAmount(), $invoice->balance(), $invoice->derivedStatus(),
            ], null, 'A'.$row++);
        }
        $this->styleHeader($invoiceSheet, 'A1:G1');

        $taskSheet = $book->createSheet();
        $taskSheet->setTitle('Task');
        $taskSheet->fromArray(['Fase', 'Task Group', 'Task', 'Status', 'Progress', 'Prioritas', 'Assignee', 'Due'], null, 'A1');
        $row = 2;
        foreach ($event->phases as $phase) {
            foreach ($phase->taskGroups as $group) {
                foreach ($group->tasks as $task) {
                    $taskSheet->fromArray([
                        $phase->name, $group->name, $task->name, $task->status,
                        (float) $task->progress, $task->priority, $task->assignee?->name,
                        $task->due_at?->format('d/m/Y H:i'),
                    ], null, 'A'.$row++);
                }
            }
        }
        $this->styleHeader($taskSheet, 'A1:H1');

        $docSheet = $book->createSheet();
        $docSheet->setTitle('Dokumentasi');
        $docSheet->fromArray(['Fase', 'Caption', 'Waktu', 'Latitude', 'Longitude', 'Akurasi', 'Alamat', 'Status'], null, 'A1');
        $row = 2;
        foreach ($event->media as $media) {
            $docSheet->fromArray([
                $media->phase?->name, $media->caption, $media->captured_at?->format('d/m/Y H:i'),
                (float) $media->gps_lat, (float) $media->gps_lng, (float) $media->gps_accuracy,
                $media->address, $media->status,
            ], null, 'A'.$row++);
        }
        $this->styleHeader($docSheet, 'A1:H1');

        $visitorSheet = $book->createSheet();
        $visitorSheet->setTitle('Pengunjung');
        $visitorSheet->fromArray(['Nama', 'Telepon', 'Kota', 'Sumber', 'Minat Model', 'Test Drive', 'Status Lead', 'Sales PIC'], null, 'A1');
        $row = 2;
        foreach ($event->visitors as $visitor) {
            $visitorSheet->fromArray([
                $visitor->name, $visitor->phone, $visitor->city, $visitor->source,
                $visitor->interest_model, $visitor->test_drive ? 'Ya' : 'Tidak',
                $visitor->lead_status, $visitor->sales_pic,
            ], null, 'A'.$row++);
        }
        $this->styleHeader($visitorSheet, 'A1:H1');

        foreach ($book->getAllSheets() as $sheet) {
            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $writer = new Xlsx($book);
        $temp = tempnam(sys_get_temp_dir(), 'star_xlsx_');
        $writer->save($temp);
        Storage::disk('local')->put($path, file_get_contents($temp));
        @unlink($temp);
    }

    private function sheet(Spreadsheet $book, string $title, array $rows): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($rows, null, 'A1');
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getStyle('A1:A'.count($rows))->getFont()->setBold(true);
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0A2540');
    }
}
