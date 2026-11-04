<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\ConflictReport;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\TaskChecklist;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ConflictController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isDealer(), 403);

        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();
        $search = $request->string('search')->toString();

        $conflicts = ConflictReport::with(['user', 'event', 'resolver'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->when($status === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($status === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->when($type, fn ($q) => $q->where('model_type', $type))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq->where('model_type', 'like', "%{$search}%")->orWhere('url', 'like', "%{$search}%")->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $types = ConflictReport::query()->distinct()->orderBy('model_type')->pluck('model_type');

        return view('conflicts.index', compact('conflicts', 'types', 'status', 'type', 'search'));
    }

    public function show(ConflictReport $conflict, \App\Services\ConflictMergeService $merge)
    {
        $this->authorizeConflict($conflict);

        $conflict->load(['user', 'event', 'resolver', 'merger']);

        return view('conflicts.show', [
            'conflict' => $conflict,
            'rows' => $conflict->diffRows(),
            'plan' => $merge->plan($conflict),
        ]);
    }

    public function merge(Request $request, ConflictReport $conflict, \App\Services\ConflictMergeService $merge)
    {
        $this->authorizeConflict($conflict);

        $report = $merge->apply($conflict, $request->input('decisions', []), auth()->user());

        return redirect()
            ->route('conflicts.show', $report)
            ->with('success', 'Hasil merge diterapkan. Konflik ditandai selesai.');
    }

    public function resolve(ConflictReport $conflict)
    {
        $this->authorizeConflict($conflict);

        $conflict->update([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Konflik ditandai selesai.');
    }

    public function reopen(ConflictReport $conflict)
    {
        $this->authorizeConflict($conflict);

        $conflict->update(['resolved_at' => null, 'resolved_by' => null]);

        return back()->with('success', 'Konflik dibuka kembali.');
    }

    public function current(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isDealer(), 403);

        $data = $request->validate([
            'type' => ['required', 'in:expense,task,checklist,event,report,invoice'],
            'id' => ['required', 'integer'],
        ]);

        $model = $this->resolveModel($data['type'], (int) $data['id']);

        if (! $model) {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $event = $this->eventFor($model);

        if ($event) {
            $allowed = match ($data['type']) {
                'expense' => $user->canCreateExpense($event) || $user->canApproveExpense($event),
                'task', 'checklist' => $user->canWorkEvent($event),
                'invoice' => $user->canFinanceEvent($event),
                default => $user->canViewEvent($event),
            };

            abort_unless($allowed, 403);
        }

        return response()->json([
            'type' => $data['type'],
            'id' => $model->getKey(),
            'label' => class_basename($model),
            'lock_version' => (int) ($model->lock_version ?? 0),
            'updated_at' => optional($model->updated_at)->toDateTimeString(),
            'attributes' => $this->attributesFor($model),
        ]);
    }

    private function authorizeConflict(ConflictReport $conflict): void
    {
        $user = auth()->user();

        abort_if($user->isDealer(), 403);
        abort_unless($user->isAdmin() || (int) $conflict->user_id === (int) $user->id, 403);
    }

    private function resolveModel(string $type, int $id): ?Model
    {
        return match ($type) {
            'expense' => Expense::find($id),
            'task' => Task::find($id),
            'checklist' => TaskChecklist::with('task')->find($id),
            'event' => Event::find($id),
            'report' => ReportVersion::find($id),
            'invoice' => ClientInvoice::find($id),
            default => null,
        };
    }

    private function eventFor(Model $model): ?Event
    {
        return match (true) {
            $model instanceof Event => $model,
            $model instanceof TaskChecklist => $model->task?->event,
            default => $model->event,
        };
    }

    private function attributesFor(Model $model): array
    {
        $keys = match (true) {
            $model instanceof Expense => ['expense_date', 'category', 'description', 'qty', 'unit_price', 'amount', 'vendor', 'payment_method', 'paid_by', 'notes', 'status', 'budget_item_id'],
            $model instanceof Task => ['name', 'description', 'priority', 'assigned_to', 'start_at', 'due_at', 'status'],
            $model instanceof Event => ['name', 'location', 'city', 'start_date', 'end_date', 'contract_value', 'status'],
            $model instanceof ReportVersion => ['version', 'status', 'published_at'],
            $model instanceof ClientInvoice => ['invoice_number', 'term', 'amount', 'due_date', 'status'],
            $model instanceof TaskChecklist => ['label', 'is_done'],
            default => [],
        };

        $attributes = ['id' => $model->getKey(), 'lock_version' => (int) ($model->lock_version ?? 0)];

        foreach ($keys as $key) {
            $value = $model->getAttribute($key);
            $attributes[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }

        return $attributes;
    }
}
