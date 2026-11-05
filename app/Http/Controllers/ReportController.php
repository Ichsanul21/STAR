<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Http\Controllers\Concerns\ChecksVersion;
use App\Models\Event;
use App\Models\ReportVersion;
use App\Services\ActivityService;
use App\Services\NotificationService;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    use AuthorizesEvents, ChecksVersion;

    public function generate(Request $request, Event $event, ReportService $reportService, ActivityService $activity, NotificationService $notifier)
    {
        $this->authorizeManage($event);

        $report = $reportService->generate($event, auth()->user());
        $label = $report->report_number ?: 'v'.$report->version;

        $activity->log('report.generated', 'Generate laporan '.$label, $event, $report);
        $notifier->send($notifier->dealerUsers($event), 'Laporan baru tersedia', $event->name.' '.$label, $event, 'report');

        return back()->with('success', 'Laporan '.$label.' berhasil dibuat.');
    }

    public function preview(ReportVersion $report, ReportService $reportService, \App\Services\FinanceService $financeService)
    {
        $this->authorizeEvent($report->event);

        $event = $report->event->load([
            'dealer', 'phases.taskGroups.tasks.assignee', 'tasks.assignee', 'budgetVersions.items',
            'expenses.budgetItem', 'invoices.payments', 'media.phase', 'visitors', 'result',
        ]);

        $finance = $financeService->eventFinancials($event);
        $snapshot = $report->snapshot ?? [];

        return view('reports.pdf', $reportService->viewData($event, $report, $finance, $snapshot));
    }

    public function pdf(ReportVersion $report)
    {
        $this->authorizeEvent($report->event);

        if (! $report->pdf_path || ! Storage::disk('local')->exists($report->pdf_path)) {
            abort(404);
        }

        $number = str_replace(['/', '\\'], '-', $report->report_number ?: 'laporan-'.$report->event->code.'-v'.$report->version);

        return Storage::disk('local')->response($report->pdf_path, $number.'.pdf');
    }

    public function xlsx(ReportVersion $report)
    {
        $this->authorizeEvent($report->event);

        if (! $report->xlsx_path || ! Storage::disk('local')->exists($report->xlsx_path)) {
            abort(404);
        }

        $number = str_replace(['/', '\\'], '-', $report->report_number ?: 'laporan-'.$report->event->code.'-v'.$report->version);

        return Storage::disk('local')->download($report->xlsx_path, $number.'.xlsx');
    }

    public function publish(ReportVersion $report, ActivityService $activity, NotificationService $notifier)
    {
        $this->authorizeManage($report->event);

        $report->update([
            'status' => 'published',
            'approved_by' => auth()->id(),
            'published_at' => now(),
        ]);

        $activity->log('report.published', 'Publish laporan v'.$report->version, $report->event, $report);
        $notifier->send($notifier->dealerUsers($report->event), 'Laporan dipublikasikan', $report->event->name.' v'.$report->version, $report->event, 'report');

        return back()->with('success', 'Laporan dipublikasikan ke dealer.');
    }

    public function updateNarrative(Request $request, ReportVersion $report, ActivityService $activity, \App\Services\HtmlSanitizer $sanitizer)
    {
        $this->authorizeManage($report->event);

        if ($report->status === 'published' && ! auth()->user()->isDev()) {
            return back()->withErrors(['narrative' => 'Laporan published bersifat immutable. Buat versi baru untuk revisi.']);
        }

        $data = $request->validate([
            'summary' => ['nullable', 'string', 'max:20000'],
            'evaluation' => ['nullable', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:20000'],
            'conclusion' => ['nullable', 'string', 'max:20000'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($this->hasVersionConflict($report, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Laporan sudah diubah orang lain. Muat ulang halaman.');
        }

        unset($data['lock_version']);
        $data = array_map(fn ($value) => $sanitizer->clean($value), $data);

        $report->update([
            'narrative' => array_merge($report->narrative ?? [], $data),
        ]);
        $this->bumpVersion($report);
        $report->save();

        $activity->log('report.narrative', 'Update narasi laporan v'.$report->version, $report->event, $report);

        return back()->with('success', 'Narasi laporan disimpan. Generate versi baru untuk memperbarui PDF/Excel.');
    }

    public function updateSectionOrder(Request $request, ReportVersion $report, ActivityService $activity)
    {
        $this->authorizeManage($report->event);

        if ($report->status === 'published' && ! auth()->user()->isDev()) {
            return back()->withErrors(['sections' => 'Laporan published bersifat immutable. Buat versi baru.']);
        }

        $data = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string', 'in:'.implode(',', array_keys(ReportService::SECTIONS))],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($this->hasVersionConflict($report, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Laporan sudah diubah orang lain. Muat ulang halaman.');
        }

        $sections = array_values(array_unique($data['sections']));

        $report->update(['section_order' => $sections]);
        $this->bumpVersion($report);
        $report->save();
        $activity->log('report.sections', 'Update urutan section laporan v'.$report->version, $report->event, $report);

        return back()->with('success', 'Urutan section disimpan.');
    }

    public function acknowledge(ReportVersion $report, ActivityService $activity)
    {
        $this->authorizeEvent($report->event);

        $report->update([
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);

        $activity->log('report.acknowledged', 'Dealer acknowledge laporan v'.$report->version, $report->event, $report);

        return back()->with('success', 'Laporan ditandai sudah diterima.');
    }
}
