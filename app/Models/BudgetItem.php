<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BudgetItem extends Model
{
    protected $fillable = [
        'budget_version_id', 'stable_key', 'category', 'description', 'qty', 'unit',
        'unit_price', 'planned_amount', 'actual_amount', 'task_group_id', 'notes', 'sort_order',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'planned_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            $item->stable_key ??= (string) Str::uuid();
        });
    }

    public function version()
    {
        return $this->belongsTo(BudgetVersion::class, 'budget_version_id');
    }

    public function taskGroup()
    {
        return $this->belongsTo(TaskGroup::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
