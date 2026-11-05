<?php

namespace App\Services;

use App\Events\UserNotificationCreated;
use App\Models\AppNotification;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function send(User|Collection $users, string $title, ?string $body = null, ?Event $event = null, string $type = 'info'): void
    {
        $users = $users instanceof User ? collect([$users]) : $users;

        foreach ($users as $user) {
            if (! $user->notificationEnabled($type)) {
                continue;
            }

            $notification = AppNotification::create([
                'user_id' => $user->id,
                'event_id' => $event?->id,
                'title' => $title,
                'body' => $body,
                'type' => $type,
            ]);

            try {
                UserNotificationCreated::dispatch($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function dealerUsers(Event $event): Collection
    {
        return User::where('dealer_id', $event->dealer_id)
            ->whereIn('role', ['dealer_admin', 'dealer_viewer'])
            ->get();
    }
}
