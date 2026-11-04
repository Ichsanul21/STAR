<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaItem extends Model
{
    protected $table = 'media';

    protected $fillable = [
        'event_id', 'event_phase_id', 'task_id', 'path', 'watermarked_path', 'original_name',
        'mime', 'size', 'width', 'height', 'caption', 'captured_at', 'gps_lat', 'gps_lng',
        'gps_accuracy', 'address', 'checksum', 'status', 'uploaded_by',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'gps_lat' => 'decimal:7',
        'gps_lng' => 'decimal:7',
        'gps_accuracy' => 'decimal:2',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function phase()
    {
        return $this->belongsTo(EventPhase::class, 'event_phase_id');
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function displayPath(): ?string
    {
        return $this->watermarked_path ?: $this->path;
    }
}
