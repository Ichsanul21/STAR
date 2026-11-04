<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'event_id', 'subject_type', 'subject_id', 'action', 'description',
        'old_values', 'new_values', 'meta', 'ip_address', 'user_agent', 'device_type',
        'browser', 'platform', 'route', 'http_method', 'status_code', 'duration_ms',
        'request_id', 'session_id', 'severity', 'is_system',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
        'is_system' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo($query, User $viewer)
    {
        if ($viewer->isDev()) {
            return $query;
        }

        return $query->where(function ($inner) {
            $inner->whereNull('user_id')
                ->orWhereHas('user', fn ($user) => $user->where('role', '!=', 'dev'));
        });
    }
}
