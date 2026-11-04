<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;

trait AuthorizesEvents
{
    protected function authorizeEvent(Event $event): void
    {
        abort_unless(auth()->user()?->canViewEvent($event), 403);
    }

    protected function authorizeManage(Event $event): void
    {
        abort_unless(auth()->user()?->canManageEvent($event), 403, 'Hanya PM event atau admin yang dapat mengubah event ini.');
    }

    protected function authorizeFinance(Event $event): void
    {
        abort_unless(auth()->user()?->canFinanceEvent($event), 403, 'Akses finance event ditolak.');
    }

    protected function authorizeWork(Event $event): void
    {
        abort_unless(auth()->user()?->canWorkEvent($event), 403, 'Anda bukan anggota event ini.');
    }

    protected function authorizeReview(Event $event): void
    {
        abort_unless(auth()->user()?->canReviewEvent($event), 403, 'Hanya reviewer atau PM yang dapat melakukan approval.');
    }

    protected function authorizeExpenseCreate(Event $event): void
    {
        abort_unless(auth()->user()?->canCreateExpense($event), 403, 'Akses expense ditolak.');
    }

    protected function authorizeExpenseApprove(Event $event): void
    {
        abort_unless(auth()->user()?->canApproveExpense($event), 403, 'Hanya finance atau PM yang dapat approve expense.');
    }
}
