<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()->canViewAudit(), 403);

        $logs = ActivityLog::with(['user', 'event'])
            ->visibleTo(auth()->user())
            ->when($request->string('search')->toString(), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->integer('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->integer('event_id'), fn ($q, $id) => $q->where('event_id', $id))
            ->when($request->string('action')->toString(), fn ($q, $action) => $q->where('action', 'like', "{$action}%"))
            ->when($request->string('severity')->toString(), fn ($q, $severity) => $q->where('severity', $severity))
            ->when($request->date('from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->date('to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $users = User::orderBy('name')->get();
        $events = Event::visibleTo(auth()->user())->orderBy('name')->get();
        $actions = ActivityLog::query()->visibleTo(auth()->user())->distinct()->orderBy('action')->pluck('action');

        return view('activity-logs.index', [
            'logs' => $logs,
            'users' => $users,
            'events' => $events,
            'actions' => $actions,
            'showRequestDetails' => auth()->user()->isSuperAdmin() || auth()->user()->isDev(),
        ]);
    }

    public function show(ActivityLog $activityLog)
    {
        abort_unless(auth()->user()->canViewAudit(), 403);
        $activityLog->load(['user', 'event']);

        abort_if($activityLog->user?->isDev() && ! auth()->user()->isDev(), 404);

        return view('activity-logs.show', [
            'log' => $activityLog,
            'showRequestDetails' => auth()->user()->isSuperAdmin() || auth()->user()->isDev(),
        ]);
    }
}
