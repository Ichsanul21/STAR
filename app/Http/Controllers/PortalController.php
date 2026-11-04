<?php

namespace App\Http\Controllers;

use App\Models\ClientInvoice;
use App\Models\Event;
use App\Models\ReportVersion;

class PortalController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isDealer(), 403);

        $events = Event::with(['dealer', 'reports' => fn ($q) => $q->where('status', 'published')->latest('version')])
            ->where('dealer_id', $user->dealer_id)
            ->latest('start_date')
            ->get();

        $reports = ReportVersion::with('event.dealer')
            ->where('status', 'published')
            ->whereHas('event', fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->latest()
            ->get();

        $invoices = ClientInvoice::with('payments', 'event')
            ->whereHas('event', fn ($q) => $q->where('dealer_id', $user->dealer_id))
            ->latest()
            ->get();

        $notifications = $user->notifications()->latest()->take(10)->get();

        return view('portal.index', compact('events', 'reports', 'invoices', 'notifications'));
    }
}
