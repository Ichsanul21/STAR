<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'dealer_id', 'name', 'type', 'location', 'city', 'start_date', 'end_date',
        'status', 'capture_mode', 'target_visitors', 'target_leads', 'target_units', 'contract_value',
        'description', 'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'contract_value' => 'decimal:2',
    ];

    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isDealer()) {
            return $query->where('dealer_id', $user->dealer_id);
        }

        if ($user->isDev() || $user->isSuperAdmin() || $user->isEoAdmin() || $user->isFinance()) {
            return $query;
        }

        return $query->whereHas('members', fn ($member) => $member->where('user_id', $user->id));
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function phases()
    {
        return $this->hasMany(EventPhase::class)->orderBy('sort_order');
    }

    public function divisions()
    {
        return $this->hasMany(EventDivision::class)->orderBy('sort_order');
    }

    public function members()
    {
        return $this->hasMany(EventMember::class);
    }

    public function taskGroups()
    {
        return $this->hasMany(TaskGroup::class)->orderBy('sort_order');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function budgetVersions()
    {
        return $this->hasMany(BudgetVersion::class)->orderByDesc('version');
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function invoices()
    {
        return $this->hasMany(ClientInvoice::class);
    }

    public function media()
    {
        return $this->hasMany(MediaItem::class);
    }

    public function visitors()
    {
        return $this->hasMany(VisitorEntry::class);
    }

    public function sales()
    {
        return $this->hasMany(SalesRecord::class);
    }

    public function result()
    {
        return $this->hasOne(EventResult::class);
    }

    public function reports()
    {
        return $this->hasMany(ReportVersion::class)->orderByDesc('version');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class)->latest();
    }

    public function scheduleItems()
    {
        return $this->hasMany(ScheduleItem::class)->orderBy('date')->orderBy('start_time');
    }

    public function planningItems()
    {
        return $this->hasMany(PlanningItem::class)->orderBy('sort_order');
    }

    public function importBatches()
    {
        return $this->hasMany(ImportBatch::class)->latest();
    }

    public function finalBudget()
    {
        return $this->hasOne(BudgetVersion::class)->where('stage', 'final')->where('status', 'approved')->latestOfMany('version');
    }

    public function planningBudget()
    {
        return $this->hasOne(BudgetVersion::class)->where('stage', 'planning')->latestOfMany('version');
    }

    public function progress(): float
    {
        $phases = $this->phases;
        $weight = (float) $phases->sum('weight');

        if ($weight <= 0) {
            return 0;
        }

        return round((float) $phases->sum(fn ($p) => (float) $p->weight * (float) $p->progress) / $weight, 2);
    }

    public function latestReport()
    {
        return $this->reports()->first();
    }
}
