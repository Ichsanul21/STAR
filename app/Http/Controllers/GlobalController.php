<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\AppSetting;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\Expense;
use App\Models\MediaItem;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\NumberingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GlobalController extends Controller
{
    public function myTasks(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isDealer(), 403);

        $tasks = Task::with(['event.dealer', 'group', 'assignee'])
            ->where('assigned_to', $user->id)
            ->whereHas('event', fn ($q) => $q->visibleTo($user))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->paginate(20)
            ->withQueryString();

        return view('global.my-tasks', compact('tasks'));
    }

    public function budget(Request $request, FinanceService $finance)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $events = Event::visibleTo(auth()->user())
            ->with(['dealer', 'phases', 'finalBudget', 'planningBudget'])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->integer('dealer_id'), fn ($q, $id) => $q->where('dealer_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('start_date')
            ->paginate(12)
            ->withQueryString();

        $financials = $events->getCollection()->mapWithKeys(fn ($event) => [$event->id => $finance->eventFinancials($event)]);
        $pnls = $events->getCollection()->mapWithKeys(fn ($event) => [$event->id => $finance->eventPnl($event)]);
        $dealers = \App\Models\Dealer::orderBy('name')->get();

        return view('global.budget', compact('events', 'financials', 'pnls', 'dealers'));
    }

    public function expenses(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $expenses = Expense::with(['event.dealer', 'budgetItem', 'creator'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('description', 'like', "%{$search}%")->orWhere('vendor', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest('expense_date')
            ->paginate(20)
            ->withQueryString();

        $events = Event::visibleTo(auth()->user())->orderBy('name')->get();
        $creatableEvents = $events->filter(fn ($event) => auth()->user()->canCreateExpense($event))->values();

        return view('global.expenses', compact('expenses', 'events', 'creatableEvents'));
    }

    public function payments(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $invoices = ClientInvoice::with(['event.dealer', 'payments'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('invoice_number', 'like', "%{$search}%")->orWhere('term', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $events = Event::visibleTo(auth()->user())->orderBy('name')->get();
        $financeEvents = $events->filter(fn ($event) => auth()->user()->canFinanceEvent($event))->values();
        $unpaidInvoices = ClientInvoice::with(['event', 'payments'])
            ->whereHas('event', fn ($query) => $query->visibleTo(auth()->user()))
            ->latest()
            ->limit(300)
            ->get()
            ->filter(fn (ClientInvoice $invoice) => $invoice->balance() > 0)
            ->values();

        return view('global.payments', compact('invoices', 'events', 'financeEvents', 'unpaidInvoices'));
    }

    public function documents(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $media = MediaItem::with(['event.dealer', 'phase', 'uploader'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($qq) => $qq->where('caption', 'like', "%{$search}%")->orWhere('address', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->latest('captured_at')
            ->paginate(24)
            ->withQueryString();

        $events = Event::visibleTo(auth()->user())->orderBy('name')->get();

        return view('global.documents', compact('media', 'events'));
    }

    public function reports(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $reports = ReportVersion::with(['event.dealer', 'generator'])
            ->whereHas('event', fn ($q) => $q->visibleTo(auth()->user()))
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->whereHas('event', fn ($qq) => $qq->where('name', 'like', "%{$search}%")))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('status')->toString(), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $events = Event::visibleTo(auth()->user())->orderBy('name')->get();

        return view('global.reports', compact('reports', 'events'));
    }

    public function masterData()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $dealers = Dealer::withCount('events')->orderBy('name')->get();
        $users = User::with('dealer')->orderBy('name')->get();
        $vehicleModels = \App\Models\Lookup::query()->group('vehicle_models')->orderBy('sort_order')->orderBy('label')->get();

        return view('global.master-data', compact('dealers', 'users', 'vehicleModels'));
    }

    public function storeLookup(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'group' => ['required', 'in:vehicle_models'],
            'label' => ['required', 'string', 'max:120'],
        ]);

        $value = \Illuminate\Support\Str::slug($data['label'], '_');

        if ($value === '') {
            return back()->withErrors(['label' => 'Label tidak valid.']);
        }

        \App\Models\Lookup::updateOrCreate(
            ['group' => $data['group'], 'value' => $value],
            ['label' => $data['label'], 'sort_order' => (int) \App\Models\Lookup::group($data['group'])->max('sort_order') + 1, 'is_active' => true]
        );

        return back()->with('success', 'Pilihan ditambahkan.');
    }

    public function updateLookup(Request $request, \App\Models\Lookup $lookup)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $lookup->update($data);

        return back()->with('success', 'Pilihan diperbarui.');
    }

    public function toggleLookup(\App\Models\Lookup $lookup)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $lookup->update(['is_active' => ! $lookup->is_active]);

        return back()->with('success', $lookup->is_active ? 'Pilihan diaktifkan.' : 'Pilihan dinonaktifkan.');
    }

    public function storeDealer(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:dealers,code'],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'pic_name' => ['nullable', 'string', 'max:120'],
        ]);

        Dealer::create($data + ['is_active' => true]);

        return back()->with('success', 'Dealer ditambahkan.');
    }

    public function storeUser(Request $request)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:dev,super_admin,eo_admin,finance,pm_event,division_pic,field_crew,reviewer,dealer_admin,dealer_viewer'],
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        if ($data['role'] === 'dev' && ! auth()->user()->isDev()) {
            return back()->withErrors(['role' => 'Hanya Developer yang dapat membuat akun Developer.']);
        }

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = true;

        User::create($data);

        return back()->with('success', 'User ditambahkan.');
    }

    public function updateDealer(Request $request, Dealer $dealer)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:dealers,code,'.$dealer->id],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'pic_name' => ['nullable', 'string', 'max:120'],
        ]);

        $dealer->update($data);

        return back()->with('success', 'Dealer diperbarui.');
    }

    public function toggleDealer(Dealer $dealer)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        if ($dealer->is_active && $dealer->events()->where('status', 'active')->exists()) {
            return back()->withErrors(['dealer' => 'Dealer masih punya event aktif. Selesaikan event terlebih dahulu.']);
        }

        $dealer->update(['is_active' => ! $dealer->is_active]);

        return back()->with('success', $dealer->is_active ? 'Dealer diaktifkan.' : 'Dealer dinonaktifkan.');
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        if ($user->isDev() && ! auth()->user()->isDev()) {
            abort(403, 'Hanya Developer yang dapat mengubah akun Developer.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:dev,super_admin,eo_admin,finance,pm_event,division_pic,field_crew,reviewer,dealer_admin,dealer_viewer'],
            'dealer_id' => ['nullable', 'exists:dealers,id'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if ($data['role'] === 'dev' && ! auth()->user()->isDev()) {
            return back()->withErrors(['role' => 'Hanya Developer yang dapat memberikan role Developer.']);
        }

        if ($user->isDev() && $data['role'] !== 'dev' && User::where('role', 'dev')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'Tidak bisa menurunkan Developer aktif terakhir.']);
        }

        if (in_array($data['role'], ['dealer_admin', 'dealer_viewer'], true) && empty($data['dealer_id'])) {
            return back()->withErrors(['dealer_id' => 'Role dealer wajib memilih dealer.']);
        }

        if ($user->isSuperAdmin() && $data['role'] !== 'super_admin' && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'Tidak bisa menurunkan Super Admin aktif terakhir.']);
        }

        if (auth()->id() === $user->id && $data['role'] !== 'super_admin' && $user->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Tidak bisa menurunkan role akun sendiri.']);
        }

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (! in_array($data['role'], ['dealer_admin', 'dealer_viewer'], true)) {
            $data['dealer_id'] = null;
        }

        $user->update($data);

        return back()->with('success', 'User diperbarui.');
    }

    public function toggleUser(User $user)
    {
        abort_unless(auth()->user()->isSuperAdmin() || auth()->user()->isDev(), 403);

        if ($user->isDev() && ! auth()->user()->isDev()) {
            abort(403, 'Hanya Developer yang dapat menonaktifkan akun Developer.');
        }

        if (auth()->id() === $user->id) {
            return back()->withErrors(['user' => 'Tidak bisa menonaktifkan akun sendiri.']);
        }

        if ($user->is_active && $user->isDev() && User::where('role', 'dev')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Tidak bisa menonaktifkan Developer aktif terakhir.']);
        }

        if ($user->is_active && $user->isSuperAdmin() && User::where('role', 'super_admin')->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Tidak bisa menonaktifkan Super Admin aktif terakhir.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? 'User diaktifkan.' : 'User dinonaktifkan.');
    }

    public function updateEventView(Request $request)
    {
        $data = $request->validate(['view' => ['required', 'in:cards,list']]);

        $request->user()->update(['default_event_view' => $data['view']]);

        $filters = collect($request->only(['search', 'dealer_id', 'status', 'date_from', 'date_to']))
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return redirect()
            ->route('events.index', $filters + ['view' => $data['view']])
            ->with('success', 'Tampilan default event disimpan.');
    }

    public function settings(NumberingService $numbering)
    {
        $user = auth()->user();

        $numberingFormats = [];
        $numberingPreviews = [];

        foreach (array_keys(NumberingService::DEFAULTS) as $type) {
            $numberingFormats[$type] = $numbering->format($type);
            $numberingPreviews[$type] = $numbering->preview($type);
        }

        return view('global.settings', compact('user', 'numberingFormats', 'numberingPreviews'));
    }

    public function updateNumbering(Request $request, NumberingService $numbering)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($request->filled('reset')) {
            $type = (string) $request->input('reset');
            abort_unless(array_key_exists($type, NumberingService::DEFAULTS), 422);

            $numbering->reset($type);

            return back()->with('success', 'Format penomoran '.NumberingService::LABELS[$type].' dikembalikan ke default.');
        }

        $data = $request->validate([
            'event_format' => ['required', 'string', 'max:80'],
            'invoice_format' => ['required', 'string', 'max:80'],
            'spk_format' => ['required', 'string', 'max:80'],
            'report_format' => ['required', 'string', 'max:80'],
        ]);

        foreach (array_keys(NumberingService::DEFAULTS) as $type) {
            $field = $type.'_format';

            if (! $numbering->isValidFormat($data[$field])) {
                return back()
                    ->withErrors([$field => 'Format wajib memakai token yang valid dan mengandung {seq}.'])
                    ->withInput();
            }
        }

        foreach (array_keys(NumberingService::DEFAULTS) as $type) {
            AppSetting::put('numbering.'.$type, $data[$type.'_format']);
        }

        return back()->with('success', 'Format penomoran otomatis disimpan.');
    }

    public function updateSettings(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['notification_preferences'] = [
            'notify_tasks' => $request->boolean('prefs.notify_tasks'),
            'notify_expenses' => $request->boolean('prefs.notify_expenses'),
            'notify_reports' => $request->boolean('prefs.notify_reports'),
            'notify_approvals' => $request->boolean('prefs.notify_approvals'),
        ];

        $user->update($data);

        return back()->with('success', 'Profil dan preferensi diperbarui.');
    }
}
