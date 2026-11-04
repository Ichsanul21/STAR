<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesEvents;
use App\Models\ClientInvoice;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\User;
use App\Models\VisitorEntry;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    use AuthorizesEvents;

    public function events(Request $request)
    {
        $user = auth()->user();
        abort_if($user->isDealer(), 403);

        $q = $request->string('q')->toString();

        $events = Event::visibleTo($user)
            ->with('dealer')
            ->when($q, fn ($query) => $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhereHas('dealer', fn ($d) => $d->where('name', 'like', "%{$q}%"))))
            ->orderByDesc('start_date')
            ->limit(25)
            ->get()
            ->map(fn (Event $event) => [
                'value' => (string) $event->id,
                'text' => $event->name.' · '.($event->dealer?->name ?? '—'),
            ]);

        return response()->json(['items' => $events]);
    }

    public function users(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $q = $request->string('q')->toString();

        $users = User::query()
            ->where('is_active', true)
            ->when($q, fn ($query) => $query->where(fn ($qq) => $qq->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (User $user) => [
                'value' => (string) $user->id,
                'text' => $user->name.' · '.$user->roleLabel(),
            ]);

        return response()->json(['items' => $users]);
    }

    public function vendors(Request $request)
    {
        $q = $request->string('q')->toString();

        $vendors = Expense::query()
            ->whereNotNull('vendor')
            ->where('vendor', '!=', '')
            ->whereHas('event', fn ($query) => $query->visibleTo(auth()->user()))
            ->when($q, fn ($query) => $query->where('vendor', 'like', "%{$q}%"))
            ->distinct()
            ->orderBy('vendor')
            ->limit(30)
            ->pluck('vendor')
            ->map(fn ($vendor) => ['value' => $vendor, 'text' => $vendor]);

        return response()->json(['items' => $vendors]);
    }

    public function cities(Request $request)
    {
        $q = $request->string('q')->toString();

        $cities = collect(config('star_options.cities', []))
            ->merge(Dealer::query()->whereNotNull('city')->distinct()->pluck('city'))
            ->merge(Event::query()->whereNotNull('city')->distinct()->pluck('city'))
            ->merge(VisitorEntry::query()->whereNotNull('city')->distinct()->limit(200)->pluck('city'))
            ->filter()
            ->unique()
            ->when($q, fn ($collection) => $collection->filter(fn ($city) => str_contains(strtolower($city), strtolower($q))))
            ->sort()
            ->take(30)
            ->map(fn ($city) => ['value' => $city, 'text' => $city])
            ->values();

        return response()->json(['items' => $cities]);
    }

    public function locations(Request $request)
    {
        $q = $request->string('q')->toString();

        $locations = Event::query()
            ->visibleTo(auth()->user())
            ->whereNotNull('location')
            ->when($q, fn ($query) => $query->where('location', 'like', "%{$q}%"))
            ->distinct()
            ->orderBy('location')
            ->limit(30)
            ->pluck('location')
            ->map(fn ($location) => ['value' => $location, 'text' => $location]);

        return response()->json(['items' => $locations]);
    }

    public function models(Request $request)
    {
        $q = $request->string('q')->toString();
        $disabled = Lookup::query()->group('vehicle_models')->where('is_active', false)->pluck('value')->all();

        $models = collect(Lookup::options('vehicle_models'))
            ->merge(
                VisitorEntry::query()
                    ->whereNotNull('interest_model')
                    ->distinct()
                    ->limit(200)
                    ->pluck('interest_model')
                    ->reject(fn ($model) => in_array($model, $disabled, true))
                    ->mapWithKeys(fn ($model) => [$model => $model])
                    ->all()
            )
            ->when($q, fn ($collection) => $collection->filter(fn ($label) => str_contains(strtolower($label), strtolower($q))))
            ->sort()
            ->take(30)
            ->map(fn ($label, $value) => ['value' => (string) $value, 'text' => $label])
            ->values();

        return response()->json(['items' => $models]);
    }

    public function invoices(Request $request)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $q = $request->string('q')->toString();
        $eventId = $request->integer('event_id');

        $invoices = ClientInvoice::with(['event', 'payments'])
            ->whereHas('event', fn ($query) => $query->visibleTo(auth()->user()))
            ->when($eventId, fn ($query) => $query->where('event_id', $eventId))
            ->when($q, fn ($query) => $query->where(fn ($qq) => $qq->where('invoice_number', 'like', "%{$q}%")->orWhere('term', 'like', "%{$q}%")))
            ->latest()
            ->limit(30)
            ->get()
            ->filter(fn (ClientInvoice $invoice) => $invoice->balance() > 0)
            ->map(fn (ClientInvoice $invoice) => [
                'value' => (string) $invoice->id,
                'text' => $invoice->invoice_number.' · sisa Rp'.number_format($invoice->balance(), 0, ',', '.'),
            ])
            ->values();

        return response()->json(['items' => $invoices]);
    }

    public function budgetItems(Event $event)
    {
        $this->authorizeEvent($event);

        $budget = $event->finalBudget ?: $event->planningBudget;

        $items = $budget
            ? $budget->items()->orderBy('sort_order')->get()->map(fn ($item) => [
                'value' => (string) $item->id,
                'text' => $item->category.' · '.$item->description.' (Rp'.number_format((float) $item->planned_amount, 0, ',', '.').')',
            ])->values()
            : collect();

        return response()->json(['items' => $items]);
    }

    public function financeContext(Event $event, FinanceService $finance)
    {
        $this->authorizeFinance($event);

        $pnl = $finance->eventPnl($event);
        $sequence = $event->invoices()->count() + 1;

        return response()->json([
            'contract' => $pnl['contract'],
            'invoiced' => $pnl['invoiced'],
            'remaining' => max(0, $pnl['contract'] - $pnl['invoiced']),
            'outstanding' => $pnl['outstanding'],
            'suggested_number' => 'INV-'.$event->code.'-'.str_pad((string) $sequence, 2, '0', STR_PAD_LEFT),
        ]);
    }
}
