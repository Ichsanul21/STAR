<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventMember;
use App\Models\EventPhase;
use App\Models\EventResult;
use App\Models\Expense;
use App\Models\ImportBatch;
use App\Models\MediaItem;
use App\Models\PlanningItem;
use App\Models\ReportVersion;
use App\Models\SalesRecord;
use App\Models\ScheduleItem;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskDependency;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\VisitorEntry;
use App\Observers\AuditObserver;
use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach ([
            Event::class, EventPhase::class, EventDivision::class, EventMember::class,
            Task::class, TaskChecklist::class, TaskDependency::class, TimeEntry::class,
            BudgetVersion::class, BudgetItem::class, Expense::class,
            ClientInvoice::class, ClientPayment::class, MediaItem::class, Attachment::class,
            VisitorEntry::class, SalesRecord::class, EventResult::class,
            ReportVersion::class, Dealer::class, User::class, ImportBatch::class,
            PlanningItem::class, ScheduleItem::class,
        ] as $model) {
            $model::observe(app(AuditObserver::class));
        }

        EventFacade::listen(Login::class, function (Login $event) {
            app(ActivityLogger::class)->log(
                'auth.login',
                'Login berhasil',
                null,
                $event->user,
                ['email' => $event->user->email],
                [],
                [],
                'info',
                $event->user->id
            );
        });

        EventFacade::listen(Failed::class, function (Failed $event) {
            app(ActivityLogger::class)->log(
                'auth.login_failed',
                'Login gagal',
                null,
                null,
                ['email' => $event->credentials['email'] ?? null],
                [],
                [],
                'warning'
            );
        });

        EventFacade::listen(Logout::class, function (Logout $event) {
            if ($event->user) {
                app(ActivityLogger::class)->log(
                    'auth.logout',
                    'Logout',
                    null,
                    $event->user,
                    ['email' => $event->user->email],
                    [],
                    [],
                    'info',
                    $event->user->id
                );
            }
        });
    }
}
