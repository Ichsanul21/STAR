<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesRecord extends Model
{
    protected $fillable = ['event_id', 'model', 'unit_price', 'spk_number', 'status', 'sold_at', 'notes'];

    protected $casts = ['unit_price' => 'decimal:2', 'sold_at' => 'date'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
