<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Http\Controllers\Concerns\ChecksVersion;
use App\Models\BudgetVersion;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventMember;
use App\Models\EventPhase;
use App\Models\User;
use App\Services\EventSetupService;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventController extends Controller
{
    use AuthorizesEvents, ChecksVersion;

    public function index(Request $request)
    {
        $user = auth()->user();
        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();

        $events = Event::with(['dealer', 'phases', 'finalBudget'])
            ->when($user->isDealer(), fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->when(! $user->isDealer() && ! $user->isDev() && ! $user->isSuperAdmin() && ! $user->isEoAdmin() && ! $user->isFinance(), fn ($q) => $q->whereHas('members', fn ($qq) => $qq->where('user_id', $user->id)))
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhereHas('dealer', fn ($d) => $d->where('name', 'like', "%{$search}%"))))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->integer('dealer_id'), fn ($q, $dealerId) => $q->where('dealer_id', $dealerId))
            ->when($request->date('date_from'), fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($request->date('date_to'), fn ($q, $date) => $q->whereDate('start_date', '<=', $date))
            ->latest('start_date')
            ->paginate(12)
            ->withQueryString();

        $dealers = Dealer::orderBy('name')->get();

        $defaultView = in_array($user->default_event_view, ['cards', 'list'], true) ? $user->default_event_view : 'cards';

        return view('events.index', compact('events', 'dealers', 'search', 'status', 'defaultView'));
    }

    public function store(Request $request, EventSetupService $setup)
    {
        abort_unless(in_array(auth()->user()->role, ['super_admin', 'eo_admin', 'pm_event', 'dev'], true), 403);

        $data = $request->validate([
            'dealer_id' => ['required', 'exists:dealers,id'],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', 'string', 'max:60'],
            'location' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'capture_mode' => ['required', 'in:upload_or_camera,camera_only'],
            'target_visitors' => ['nullable', 'integer', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0'],
            'target_units' => ['nullable', 'integer', 'min:0'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $data['code'] = 'EVT-'.now()->format('Ym').'-'.Str::upper(Str::random(4));
        $data['created_by'] = auth()->id();
        $data['status'] = 'active';

        $event = Event::create($data);
        $setup->bootstrap($event);

        BudgetVersion::create([
            'event_id' => $event->id,
            'stage' => 'planning',
            'version' => 1,
            'status' => 'draft',
        ]);

        return redirect()->route('events.show', $event)->with('success', 'Event berhasil dibuat.');
    }

    public function show(Event $event, FinanceService $finance)
    {
        return $this->renderWorkspace($event, 'overview', $finance);
    }

    public function tab(Event $event, string $tab, FinanceService $finance)
    {
        abort_unless(in_array($tab, ['overview', 'plan', 'tasks', 'finance', 'documentation', 'reports', 'team'], true), 404);

        return $this->renderWorkspace($event, $tab, $finance);
    }

    private function renderWorkspace(Event $event, string $tab, FinanceService $finance)
    {
        $this->authorizeEvent($event);

        $event->load([
            'dealer', 'phases.taskGroups.tasks.assignee', 'divisions', 'members.user', 'members.division',
            'budgetVersions.items.taskGroup', 'expenses.budgetItem', 'expenses.task', 'invoices.payments',
            'media.phase', 'media.uploader', 'visitors', 'result', 'reports.generator', 'attachments', 'creator',
            'scheduleItems', 'planningItems',
        ]);

        $financials = $finance->eventFinancials($event);
        $budget = request('budget_version')
            ? $event->budgetVersions->firstWhere('id', (int) request('budget_version'))
            : ($event->finalBudget ?: $event->planningBudget);
        $users = User::where('is_active', true)->orderBy('name')->get();
        $dealers = Dealer::orderBy('name')->get();
        $activities = \App\Models\ActivityLog::with('user')
            ->where('event_id', $event->id)
            ->visibleTo(auth()->user())
            ->latest()
            ->take(20)
            ->get();
        $view = request('view', $tab === 'tasks' ? 'board' : ($tab === 'finance' ? 'budget' : null));

        return view('events.workspace', compact('event', 'tab', 'view', 'financials', 'budget', 'users', 'dealers', 'activities'));
    }

    public function update(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'status' => ['required', 'in:draft,active,closed'],
            'capture_mode' => ['required', 'in:upload_or_camera,camera_only'],
            'location' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string'],
            'target_visitors' => ['nullable', 'integer', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0'],
            'target_units' => ['nullable', 'integer', 'min:0'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'lock_version' => ['nullable', 'integer'],
        ]);

        if ($this->hasVersionConflict($event, $data['lock_version'] ?? null)) {
            return $this->conflictResponse($request, 'Event sudah diubah orang lain. Muat ulang halaman.');
        }

        unset($data['lock_version']);
        $event->update($data);
        $this->bumpVersion($event);
        $event->save();

        return back()->with('success', 'Event diperbarui.');
    }

    public function storePhase(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'weight' => ['required', 'numeric', 'min:0.1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $event->phases()->create([
            'name' => $data['name'],
            'code' => Str::slug($data['name']),
            'weight' => $data['weight'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'sort_order' => (int) $event->phases()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Fase ditambahkan.');
    }

    public function storeDivision(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);

        EventDivision::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'code' => Str::slug($data['name']),
            'sort_order' => (int) $event->divisions()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Divisi ditambahkan.');
    }

    public function storeMember(Request $request, Event $event)
    {
        $this->authorizeManage($event);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', 'in:pm_event,division_pic,field_crew,reviewer,viewer'],
            'division_id' => ['nullable', 'exists:event_divisions,id'],
        ]);

        EventMember::firstOrCreate([
            'event_id' => $event->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'],
            'division_id' => $data['division_id'] ?? null,
        ]);

        return back()->with('success', 'Anggota tim ditambahkan.');
    }
}
