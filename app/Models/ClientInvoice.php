<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientInvoice extends Model
{
    protected $fillable = ['event_id', 'invoice_number', 'term', 'description', 'amount', 'due_date', 'status'];

    protected $casts = ['due_date' => 'date', 'amount' => 'decimal:2'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function payments()
    {
        return $this->hasMany(ClientPayment::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->where('status', 'verified')->sum('amount');
    }

    public function balance(): float
    {
        return max(0, (float) $this->amount - $this->paidAmount());
    }

    public function derivedStatus(): string
    {
        if ($this->status === 'void') {
            return 'void';
        }

        $paid = $this->paidAmount();

        if ($paid <= 0) {
            return $this->due_date && $this->due_date->isPast() ? 'overdue' : 'unpaid';
        }

        if ($paid + 0.01 >= (float) $this->amount) {
            return 'paid';
        }

        return 'partial';
    }
}
