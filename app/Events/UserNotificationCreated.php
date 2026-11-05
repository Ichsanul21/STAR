<?php

namespace App\Events;

use App\Models\AppNotification;
use App\Services\ActionInboxService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserNotificationCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public AppNotification $notification)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $userId = (int) $this->notification->user_id;

        $unreadByType = AppNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $unread = (int) $unreadByType->sum();
        $user = $this->notification->user;
        $actions = $user
            ? app(ActionInboxService::class)->counts($user)
            : ['task' => 0, 'finance' => 0, 'report' => 0, 'total' => 0];

        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'type' => $this->notification->type,
            'event_id' => $this->notification->event_id,
            'url' => $this->urlFor($this->notification),
            'created_at' => $this->notification->created_at?->toIso8601String(),
            'counts' => [
                'unread' => $unread,
                'total' => $unread + $actions['total'],
                'task' => (int) $unreadByType->get('task', 0) + $actions['task'],
                'finance' => (int) $unreadByType->only(['expense', 'approval', 'payment'])->sum() + $actions['finance'],
                'report' => (int) $unreadByType->get('report', 0) + $actions['report'],
            ],
        ];
    }

    private function urlFor(AppNotification $notification): string
    {
        return match ($notification->type) {
            'task' => route('notifications.index', ['type' => 'task']),
            'expense', 'approval', 'payment' => route('notifications.index', ['type' => 'finance']),
            'report' => route('notifications.index', ['type' => 'report']),
            default => route('notifications.index'),
        };
    }
}
