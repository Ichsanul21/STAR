<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevSession extends Model
{
    protected $fillable = [
        'dev_id', 'target_id', 'started_at', 'ended_at', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function dev()
    {
        return $this->belongsTo(User::class, 'dev_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }
}
