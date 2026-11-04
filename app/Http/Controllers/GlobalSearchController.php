<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\Expense;
use App\Models\MediaItem;
use App\Models\ReportVersion;
use App\Models\Task;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isDealer(), 403);

        $q = trim($request->string('q')->toString());
        $type = $request->string('type')->toString();
        $groups = [];

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $wants = fn (string $key) => $type === '' || $type === $key;

            if ($wants('events')) {
                $items = Event::visibleTo($user)
                    ->with('dealer')
                    ->where(fn ($qq) => $qq->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('location', 'like', $like)->orWhere('city', 'like', $like))
                    ->orderByDesc('start_date')
                    ->limit(8)
                    ->get()
                    ->map(fn (Event $event) => [
                        'title' => $event->name,
                        'subtitle' => $event->code.' · '.($event->dealer?->name ?? '—'),
                        'meta' => $event->start_date?->format('d/m/Y'),
                        'status' => $event->status,
                        'url' => route('events.show', $event),
                    ])->all();

                $groups[] = ['key' => 'events', 'label' => 'Events', 'items' => $items];
            }

            if ($wants('tasks')) {
                $items = Task::with('event')
                    ->whereHas('event', fn ($qq) => $qq->visibleTo($user))
                    ->where(fn ($qq) => $qq->where('name', 'like', $like)->orWhere('description', 'like', $like))
                    ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
                    ->limit(8)
                    ->get()
                    ->map(fn (Task $task) => [
                        'title' => $task->name,
                        'subtitle' => $task->event?->name,
                        'meta' => $task->due_at?->format('d/m/Y'),
                        'status' => $task->status,
                        'url' => route('events.tab', [$task->event, 'tasks']).'#task-'.$task->id,
                    ])->all();

                $groups[] = ['key' => 'tasks', 'label' => 'Tasks', 'items' => $items];
            }

            if ($wants('dealers')) {
                $items = Dealer::where(fn ($qq) => $qq->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('city', 'like', $like)->orWhere('pic_name', 'like', $like))
                    ->orderBy('name')
                    ->limit(8)
                    ->get()
                    ->map(fn (Dealer $dealer) => [
                        'title' => $dealer->name,
                        'subtitle' => trim($dealer->code.' · '.($dealer->city ?? '')),
                        'meta' => $dealer->pic_name,
                        'status' => $dealer->is_active ? 'active' : 'inactive',
                        'url' => route('clients.show', $dealer),
                    ])->all();

                $groups[] = ['key' => 'dealers', 'label' => 'Dealer', 'items' => $items];
            }

            if ($wants('expenses')) {
                $items = Expense::with('event')
                    ->whereHas('event', fn ($qq) => $qq->visibleTo($user))
                    ->where(fn ($qq) => $qq->where('description', 'like', $like)->orWhere('vendor', 'like', $like))
                    ->latest('expense_date')
                    ->limit(8)
                    ->get()
                    ->map(fn (Expense $expense) => [
                        'title' => $expense->description,
                        'subtitle' => $expense->event?->name.' · '.($expense->vendor ?: 'tanpa vendor'),
                        'meta' => 'Rp'.number_format((float) $expense->amount, 0, ',', '.'),
                        'status' => $expense->status,
                        'url' => route('global.expenses', ['search' => $expense->description]),
                    ])->all();

                $groups[] = ['key' => 'expenses', 'label' => 'Expenses', 'items' => $items];
            }

            if ($wants('invoices')) {
                $items = ClientInvoice::with(['event', 'payments'])
                    ->whereHas('event', fn ($qq) => $qq->visibleTo($user))
                    ->where(fn ($qq) => $qq->where('invoice_number', 'like', $like)->orWhere('term', 'like', $like))
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (ClientInvoice $invoice) => [
                        'title' => $invoice->invoice_number,
                        'subtitle' => $invoice->event?->name.' · '.$invoice->term,
                        'meta' => 'Rp'.number_format((float) $invoice->amount, 0, ',', '.'),
                        'status' => $invoice->derivedStatus(),
                        'url' => route('global.payments', ['search' => $invoice->invoice_number]),
                    ])->all();

                $groups[] = ['key' => 'invoices', 'label' => 'Invoice', 'items' => $items];
            }

            if ($wants('reports')) {
                $items = ReportVersion::with('event')
                    ->whereHas('event', fn ($qq) => $qq->visibleTo($user)->where('name', 'like', $like))
                    ->latest()
                    ->limit(8)
                    ->get()
                    ->map(fn (ReportVersion $report) => [
                        'title' => 'Laporan v'.$report->version,
                        'subtitle' => $report->event?->name,
                        'meta' => $report->created_at?->format('d/m/Y'),
                        'status' => $report->status,
                        'url' => route('reports.preview', $report),
                    ])->all();

                $groups[] = ['key' => 'reports', 'label' => 'Reports', 'items' => $items];
            }

            if ($wants('documents')) {
                $items = MediaItem::with('event')
                    ->whereHas('event', fn ($qq) => $qq->visibleTo($user))
                    ->where(fn ($qq) => $qq->where('caption', 'like', $like)->orWhere('address', 'like', $like))
                    ->latest('captured_at')
                    ->limit(8)
                    ->get()
                    ->map(fn (MediaItem $media) => [
                        'title' => $media->caption ?: $media->original_name,
                        'subtitle' => $media->event?->name.' · '.($media->address ?: 'tanpa alamat'),
                        'meta' => $media->captured_at?->format('d/m/Y H:i'),
                        'status' => $media->status,
                        'url' => route('media.file', $media),
                    ])->all();

                $groups[] = ['key' => 'documents', 'label' => 'Dokumentasi', 'items' => $items];
            }
        }

        $total = collect($groups)->sum(fn ($group) => count($group['items']));

        return view('global.search', compact('q', 'type', 'groups', 'total'));
    }
}
