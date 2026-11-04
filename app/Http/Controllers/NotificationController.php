<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\ActionInboxService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, ActionInboxService $inbox)
    {
        $type = $request->string('type')->toString();
        $base = auth()->user()->notifications();

        $notifications = (clone $base)
            ->when($type === 'task', fn ($query) => $query->where('type', 'task'))
            ->when($type === 'finance', fn ($query) => $query->whereIn('type', ['expense', 'approval', 'payment']))
            ->when($type === 'report', fn ($query) => $query->where('type', 'report'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = (clone $base)
            ->whereNull('read_at')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $allActions = $inbox->items(auth()->user());
        $actions = match ($type) {
            'task' => $allActions->where('category', 'task')->values(),
            'finance' => $allActions->where('category', 'finance')->values(),
            'report' => $allActions->where('category', 'report')->values(),
            default => $allActions,
        };
        $actionCounts = $inbox->counts(auth()->user());

        $storedTask = (int) $counts->get('task', 0);
        $storedFinance = (int) $counts->only(['expense', 'approval', 'payment'])->sum();
        $storedReport = (int) $counts->get('report', 0);

        $tabCounts = [
            'all' => (int) $counts->sum() + $actionCounts['total'],
            'task' => $storedTask + $actionCounts['task'],
            'finance' => $storedFinance + $actionCounts['finance'],
            'report' => $storedReport + $actionCounts['report'],
        ];

        return view('notifications.index', compact('notifications', 'type', 'tabCounts', 'actions'));
    }

    public function read(AppNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) auth()->id(), 403);
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function readAll()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }
}
