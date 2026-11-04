<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'dealer_id', 'role', 'phone', 'is_active', 'notification_preferences', 'default_event_view'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
        ];
    }

    public function dealer()
    {
        return $this->belongsTo(Dealer::class);
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    public function eventMemberships()
    {
        return $this->hasMany(EventMember::class);
    }

    public function notifications()
    {
        return $this->hasMany(AppNotification::class);
    }

    public function notificationEnabled(string $type): bool
    {
        $key = match ($type) {
            'task' => 'notify_tasks',
            'expense' => 'notify_expenses',
            'report' => 'notify_reports',
            'approval' => 'notify_approvals',
            default => null,
        };

        if ($key === null) {
            return true;
        }

        return (bool) ($this->notification_preferences[$key] ?? true);
    }

    public function isDealer(): bool
    {
        return in_array($this->role, ['dealer_admin', 'dealer_viewer'], true);
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'eo_admin', 'dev'], true);
    }

    public function isDev(): bool
    {
        return $this->role === 'dev';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isEoAdmin(): bool
    {
        return $this->role === 'eo_admin';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    public function isEventMember(Event $event): bool
    {
        return $this->eventMemberships()->where('event_id', $event->id)->exists();
    }

    public function eventRole(Event $event): ?string
    {
        return $this->eventMemberships()->where('event_id', $event->id)->value('role');
    }

    public function canViewEvent(Event $event): bool
    {
        if ($this->isDealer()) {
            return (int) $this->dealer_id === (int) $event->dealer_id;
        }

        if ($this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin() || $this->isFinance()) {
            return true;
        }

        return $this->isEventMember($event);
    }

    public function canManageEvent(Event $event): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        return $this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin() || $this->eventRole($event) === 'pm_event';
    }

    public function canFinanceEvent(Event $event): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        return $this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin() || $this->isFinance() || $this->eventRole($event) === 'pm_event';
    }

    public function canWorkEvent(Event $event): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        return $this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin() || in_array($this->eventRole($event), ['pm_event', 'division_pic', 'field_crew', 'reviewer'], true);
    }

    public function canReviewEvent(Event $event): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        return $this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin() || in_array($this->eventRole($event), ['pm_event', 'reviewer'], true);
    }

    public function canApproveExpense(Event $event): bool
    {
        return $this->canFinanceEvent($event);
    }

    public function canCreateExpense(Event $event): bool
    {
        if ($this->canFinanceEvent($event)) {
            return true;
        }

        return in_array($this->eventRole($event), ['division_pic', 'field_crew'], true);
    }

    public function canAdvanceTask(Task $task): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        if ($this->isDev()) {
            return true;
        }

        if ((int) $task->assigned_to === (int) $this->id) {
            return true;
        }

        $membership = $this->eventMemberships()->where('event_id', $task->event_id)->get();

        if ($membership->contains(fn ($member) => $member->role === 'field_crew')) {
            return true;
        }

        $divisionId = $task->group?->event_division_id;

        return $divisionId !== null && $membership->contains(
            fn ($member) => $member->role === 'division_pic' && (int) $member->division_id === (int) $divisionId
        );
    }

    public function canRequestRevision(Event $event): bool
    {
        if ($this->isDealer()) {
            return false;
        }

        return $this->isDev()
            || $this->isSuperAdmin()
            || $this->isEoAdmin()
            || in_array($this->eventRole($event), ['pm_event', 'reviewer'], true);
    }

    public function canViewAudit(): bool
    {
        return $this->isDev() || $this->isSuperAdmin() || $this->isEoAdmin();
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'dev' => 'Developer',
            'super_admin' => 'Super Admin',
            'eo_admin' => 'EO Admin',
            'finance' => 'Finance',
            'pm_event' => 'PM Event',
            'division_pic' => 'PIC Divisi',
            'field_crew' => 'Field Crew',
            'reviewer' => 'Reviewer',
            'dealer_admin' => 'Dealer Admin',
            'dealer_viewer' => 'Dealer Viewer',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }
}
