<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Event;

class ActivityService
{
    public function log(string $action, ?string $description = null, ?Event $event = null, mixed $subject = null): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'event_id' => $event?->id,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
