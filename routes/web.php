<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConflictController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevController;
use App\Http\Controllers\DocumentationController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventImportController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GlobalController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\IntegrityController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskUpdateController;
use App\Http\Controllers\VisitorController;
use App\Http\Controllers\XlsxImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->isDealer() ? 'portal.index' : 'dashboard')
        : redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/demo-login/{account}', [AuthController::class, 'demoLogin'])->name('demo.login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/preferences/event-view', [GlobalController::class, 'updateEventView'])->name('preferences.event-view');
    Route::get('/lookup/events', [LookupController::class, 'events'])->name('lookup.events');
    Route::get('/lookup/users', [LookupController::class, 'users'])->name('lookup.users');
    Route::get('/lookup/vendors', [LookupController::class, 'vendors'])->name('lookup.vendors');
    Route::get('/lookup/cities', [LookupController::class, 'cities'])->name('lookup.cities');
    Route::get('/lookup/locations', [LookupController::class, 'locations'])->name('lookup.locations');
    Route::get('/lookup/models', [LookupController::class, 'models'])->name('lookup.models');
    Route::get('/lookup/invoices', [LookupController::class, 'invoices'])->name('lookup.invoices');
    Route::get('/search', [GlobalSearchController::class, 'index'])->name('global.search');
    Route::get('/exports/{type}', [ExportController::class, 'export'])->whereIn('type', ['budget', 'expenses', 'payments', 'reports', 'clients'])->name('global.export');
    Route::get('/my-tasks', [GlobalController::class, 'myTasks'])->name('global.my-tasks');
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{dealer}', [ClientController::class, 'show'])->name('clients.show');
    Route::patch('/clients/{dealer}', [ClientController::class, 'update'])->name('clients.update');
    Route::get('/budget', [GlobalController::class, 'budget'])->name('global.budget');
    Route::get('/expenses', [GlobalController::class, 'expenses'])->name('global.expenses');
    Route::get('/payments', [GlobalController::class, 'payments'])->name('global.payments');
    Route::get('/documents', [GlobalController::class, 'documents'])->name('global.documents');
    Route::get('/reports', [GlobalController::class, 'reports'])->name('global.reports');
    Route::get('/master-data', [GlobalController::class, 'masterData'])->name('global.master-data');
    Route::post('/master-data/dealers', [GlobalController::class, 'storeDealer'])->name('global.dealers.store');
    Route::patch('/master-data/dealers/{dealer}', [GlobalController::class, 'updateDealer'])->name('global.dealers.update');
    Route::post('/master-data/dealers/{dealer}/toggle', [GlobalController::class, 'toggleDealer'])->name('global.dealers.toggle');
    Route::post('/master-data/users', [GlobalController::class, 'storeUser'])->name('global.users.store');
    Route::patch('/master-data/users/{user}', [GlobalController::class, 'updateUser'])->name('global.users.update');
    Route::post('/master-data/users/{user}/toggle', [GlobalController::class, 'toggleUser'])->name('global.users.toggle');
    Route::post('/master-data/lookups', [GlobalController::class, 'storeLookup'])->name('global.lookups.store');
    Route::patch('/master-data/lookups/{lookup}', [GlobalController::class, 'updateLookup'])->name('global.lookups.update');
    Route::post('/master-data/lookups/{lookup}/toggle', [GlobalController::class, 'toggleLookup'])->name('global.lookups.toggle');
    Route::get('/settings', [GlobalController::class, 'settings'])->name('global.settings');
    Route::patch('/settings', [GlobalController::class, 'updateSettings'])->name('global.settings.update');
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
    Route::get('/offline', fn () => view('offline.index'))->name('offline.index');
    Route::get('/conflicts/current', [ConflictController::class, 'current'])->name('conflicts.current');
    Route::get('/conflicts', [ConflictController::class, 'index'])->name('conflicts.index');
    Route::get('/conflicts/{conflict}', [ConflictController::class, 'show'])->name('conflicts.show');
    Route::post('/conflicts/{conflict}/resolve', [ConflictController::class, 'resolve'])->name('conflicts.resolve');
    Route::post('/conflicts/{conflict}/merge', [ConflictController::class, 'merge'])->name('conflicts.merge');
    Route::post('/conflicts/{conflict}/reopen', [ConflictController::class, 'reopen'])->name('conflicts.reopen');
    Route::get('/integrity', [IntegrityController::class, 'index'])->name('integrity.index');
    Route::post('/integrity/reconcile', [IntegrityController::class, 'reconcile'])->name('artisan.reconcile');

    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/{tab}', [EventController::class, 'tab'])
        ->whereIn('tab', ['overview', 'plan', 'tasks', 'finance', 'documentation', 'reports', 'team'])
        ->name('events.tab');
    Route::patch('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/phases', [EventController::class, 'storePhase'])->name('events.phases.store');
    Route::post('/events/{event}/divisions', [EventController::class, 'storeDivision'])->name('events.divisions.store');
    Route::post('/events/{event}/members', [EventController::class, 'storeMember'])->name('events.members.store');
    Route::post('/events/{event}/import/rundown', [EventImportController::class, 'importRundown'])->name('imports.rundown');
    Route::post('/events/{event}/import/planning', [EventImportController::class, 'importPlanning'])->name('imports.planning');
    Route::post('/events/{event}/imports/preview', [XlsxImportController::class, 'preview'])->name('imports.preview');
    Route::get('/imports/{batch}/map', [XlsxImportController::class, 'map'])->name('imports.map');
    Route::post('/imports/{batch}/commit', [XlsxImportController::class, 'commit'])->name('imports.commit');

    Route::post('/events/{event}/graph/positions', [TaskController::class, 'updateGraphPositions'])->name('events.graph.positions');
    Route::post('/events/{event}/task-groups', [TaskController::class, 'storeGroup'])->name('task-groups.store');
    Route::post('/events/{event}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/schedule', [TaskController::class, 'reschedule'])->name('tasks.reschedule');
    Route::post('/tasks/{task}/advance', [TaskUpdateController::class, 'advance'])->name('tasks.advance');
    Route::post('/tasks/{task}/revisions', [TaskUpdateController::class, 'requestRevision'])->name('tasks.revisions.store');
    Route::post('/tasks/{task}/checklists', [TaskController::class, 'storeChecklist'])->name('tasks.checklists.store');
    Route::post('/checklists/{checklist}/complete', [TaskUpdateController::class, 'completeChecklist'])->name('checklists.complete');
    Route::get('/task-updates/{update}/photo', [TaskUpdateController::class, 'photo'])->name('task-updates.photo');
    Route::post('/tasks/{task}/dependencies', [TaskController::class, 'storeDependency'])->name('tasks.dependencies.store');

    Route::post('/events/{event}/budget/versions', [BudgetController::class, 'storeVersion'])->name('budget.versions.store');
    Route::post('/budget/{budget}/items', [BudgetController::class, 'storeItem'])->name('budget.items.store');
    Route::post('/events/{event}/budget/import', [BudgetController::class, 'import'])->name('budget.import');
    Route::post('/budget/{budget}/finalize', [BudgetController::class, 'finalize'])->name('budget.finalize');

    Route::post('/events/{event}/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::get('/events/{event}/budget-items', [LookupController::class, 'budgetItems'])->name('lookup.budget-items');
    Route::get('/events/{event}/finance-context', [LookupController::class, 'financeContext'])->name('lookup.finance-context');
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('/expenses', [ExpenseController::class, 'storeGlobal'])->name('expenses.store-global');
    Route::post('/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->name('expenses.approve');
    Route::post('/expenses/bulk', [ExpenseController::class, 'bulk'])->name('expenses.bulk');
    Route::post('/expenses/{expense}/reject', [ExpenseController::class, 'reject'])->name('expenses.reject');
    Route::get('/expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');

    Route::post('/events/{event}/invoices', [PaymentController::class, 'storeInvoice'])->name('invoices.store');
    Route::post('/invoices', [PaymentController::class, 'storeInvoiceGlobal'])->name('invoices.store-global');
    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'storePayment'])->name('payments.store');
    Route::post('/payments', [PaymentController::class, 'storePaymentGlobal'])->name('payments.store-global');

    Route::post('/events/{event}/media', [DocumentationController::class, 'store'])->name('media.store');
    Route::get('/media/{media}/file', [DocumentationController::class, 'file'])->name('media.file');
    Route::post('/media/{media}/approve', [DocumentationController::class, 'approve'])->name('media.approve');
    Route::post('/media/{media}/reject', [DocumentationController::class, 'reject'])->name('media.reject');
    Route::post('/events/{event}/attachments', [DocumentationController::class, 'storeAttachment'])->name('attachments.store');
    Route::get('/attachments/{attachment}', [DocumentationController::class, 'attachment'])->name('attachments.show');

    Route::post('/events/{event}/visitors', [VisitorController::class, 'store'])->name('visitors.store');
    Route::post('/events/{event}/sales', [VisitorController::class, 'storeSale'])->name('sales.store');
    Route::patch('/events/{event}/result', [VisitorController::class, 'updateResult'])->name('results.update');

    Route::post('/events/{event}/reports', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('/reports/{report}/preview', [ReportController::class, 'preview'])->name('reports.preview');
    Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');
    Route::get('/reports/{report}/xlsx', [ReportController::class, 'xlsx'])->name('reports.xlsx');
    Route::post('/reports/{report}/publish', [ReportController::class, 'publish'])->name('reports.publish');
    Route::post('/reports/{report}/narrative', [ReportController::class, 'updateNarrative'])->name('reports.narrative');
    Route::post('/reports/{report}/sections', [ReportController::class, 'updateSectionOrder'])->name('reports.sections');
    Route::post('/reports/{report}/acknowledge', [ReportController::class, 'acknowledge'])->name('reports.acknowledge');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/portal', [PortalController::class, 'index'])->name('portal.index');

    Route::post('/dev/stop-impersonation', [DevController::class, 'stopImpersonation'])->name('dev.stop');

    Route::middleware(['dev.enabled'])->prefix('dev')->group(function () {
        Route::middleware(['dev.role'])->group(function () {
            Route::get('/', [DevController::class, 'index'])->name('dev.index');
            Route::post('/impersonate/{user}', [DevController::class, 'impersonate'])->name('dev.impersonate');
            Route::post('/cache/clear', [DevController::class, 'clearCache'])->name('dev.cache');
            Route::post('/reconcile', [DevController::class, 'reconcile'])->name('dev.reconcile');
            Route::post('/reset/demo', [DevController::class, 'reseed'])->name('dev.reset.demo');
            Route::post('/reset/empty', [DevController::class, 'resetEmpty'])->name('dev.reset.empty');
            Route::post('/tasks/{task}/force-status', [DevController::class, 'forceTaskStatus'])->name('dev.tasks.force');
            Route::post('/reports/{report}/unpublish', [DevController::class, 'unpublishReport'])->name('dev.reports.unpublish');
            Route::delete('/records/{type}/{id}', [DevController::class, 'deleteRecord'])->name('dev.records.delete');
        });
    });
});
