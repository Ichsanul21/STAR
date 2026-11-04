<?php

namespace App\Http\Controllers;

use App\Models\Dealer;
use App\Services\FinanceService;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request, FinanceService $finance)
    {
        abort_if(auth()->user()->isDealer(), 403);

        $search = $request->string('search')->toString();
        $city = $request->string('city')->toString();

        $dealers = Dealer::with(['events.invoices.payments', 'events.expenses', 'events.finalBudget', 'events.planningBudget'])
            ->when($search, fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")->orWhere('pic_name', 'like', "%{$search}%")))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        $summaries = $dealers->getCollection()->mapWithKeys(function ($dealer) use ($finance) {
            $totals = ['contract' => 0, 'invoiced' => 0, 'received' => 0, 'cost' => 0, 'profit' => 0];
            foreach ($dealer->events as $event) {
                $pnl = $finance->eventPnl($event);
                $totals['contract'] += $pnl['contract'];
                $totals['invoiced'] += $pnl['invoiced'];
                $totals['received'] += $pnl['received'];
                $totals['cost'] += $pnl['cost'];
                $totals['profit'] += $pnl['gross_profit'];
            }

            return [$dealer->id => $totals];
        });

        $cities = Dealer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city');

        return view('clients.index', compact('dealers', 'summaries', 'cities', 'search', 'city'));
    }

    public function show(Dealer $dealer, FinanceService $finance)
    {
        abort_if(auth()->user()->isDealer() && (int) auth()->user()->dealer_id !== (int) $dealer->id, 403);

        $dealer->load(['events' => fn ($q) => $q->latest('start_date'), 'events.invoices.payments', 'events.expenses', 'events.finalBudget', 'events.planningBudget', 'events.reports']);

        $financials = $dealer->events->mapWithKeys(fn ($event) => [$event->id => $finance->eventPnl($event)]);
        $totals = ['contract' => 0, 'invoiced' => 0, 'received' => 0, 'cost' => 0, 'profit' => 0, 'outstanding' => 0];
        foreach ($financials as $pnl) {
            $totals['contract'] += $pnl['contract'];
            $totals['invoiced'] += $pnl['invoiced'];
            $totals['received'] += $pnl['received'];
            $totals['cost'] += $pnl['cost'];
            $totals['profit'] += $pnl['gross_profit'];
            $totals['outstanding'] += $pnl['outstanding'];
        }

        return view('clients.show', compact('dealer', 'financials', 'totals'));
    }

    public function update(Request $request, Dealer $dealer)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'pic_name' => ['nullable', 'string', 'max:120'],
        ]);

        $dealer->update($data);

        return back()->with('success', 'Profil dealer diperbarui.');
    }
}
