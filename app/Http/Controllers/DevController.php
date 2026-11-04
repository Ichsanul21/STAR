<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ClientInvoice;
use App\Models\Dealer;
use App\Models\DevSession;
use App\Models\Event;
use App\Models\Expense;
use App\Models\MediaItem;
use App\Models\ReportVersion;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\ProgressService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;

class DevController extends Controller
{
    public function index()
    {
        $logFile = storage_path('logs/laravel.log');
        $logTail = File::exists($logFile) ? collect(File::lines($logFile))->take(-140)->implode("\n") : 'Belum ada log.';

        $failedJobs = DB::table('failed_jobs')->latest('id')->take(10)->get();

        $routes = collect(RouteFacade::getRoutes())
            ->map(fn ($route) => [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'middleware' => implode(', ', $route->gatherMiddleware()),
            ])
            ->sortBy('uri')
            ->values();

        $counts = [
            'users' => User::count(),
            'events' => Event::count(),
            'tasks' => Task::count(),
            'expenses' => Expense::count(),
            'invoices' => ClientInvoice::count(),
            'reports' => ReportVersion::count(),
            'media' => MediaItem::count(),
            'activity_logs' => ActivityLog::count(),
        ];

        $devSessions = DevSession::with(['dev', 'target'])->latest()->take(20)->get();
        $users = User::orderBy('role')->orderBy('name')->get();

        return view('dev.index', [
            'counts' => $counts,
            'logTail' => $logTail,
            'failedJobs' => $failedJobs,
            'routes' => $routes,
            'devSessions' => $devSessions,
            'users' => $users,
            'environment' => [
                'app_env' => config('app.env'),
                'debug' => config('app.debug') ? 'true' : 'false',
                'laravel' => app()->version(),
                'php' => PHP_VERSION,
                'db' => config('database.default'),
                'cache' => config('cache.default'),
                'queue' => config('queue.default'),
                'session' => config('session.driver'),
                'timezone' => config('app.timezone'),
                'dev_tools' => config('app.dev_tools') ? 'enabled' : 'disabled',
                'dev_reset' => config('app.dev_reset') ? 'enabled' : 'disabled',
                'impersonation' => config('app.dev_impersonation') ? 'enabled' : 'disabled',
            ],
        ]);
    }

    public function impersonate(Request $request, User $user)
    {
        abort_unless(config('app.dev_impersonation'), 404);

        $dev = auth()->user();
        abort_unless($dev?->isDev(), 403);

        if (! $user->is_active) {
            return back()->withErrors(['impersonate' => 'Akun target nonaktif.']);
        }

        $this->closeCurrentDevSession();

        $session = DevSession::create([
            'dev_id' => $dev->id,
            'target_id' => $user->id,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('dev_original_id', $dev->id);
        $request->session()->put('dev_session_id', $session->id);

        return redirect()
            ->route($user->isDealer() ? 'portal.index' : 'dashboard')
            ->with('success', 'Mode simulasi: '.$user->name.' ('.$user->roleLabel().').');
    }

    public function stopImpersonation(Request $request)
    {
        $devId = $request->session()->get('dev_original_id');
        $dev = $devId ? User::find($devId) : null;

        if (! $dev || ! $dev->isDev() || ! $dev->is_active) {
            $request->session()->forget(['dev_original_id', 'dev_session_id']);

            return redirect()->route('dashboard');
        }

        $this->closeCurrentDevSession();

        Auth::login($dev);
        $request->session()->regenerate();
        $request->session()->forget(['dev_original_id', 'dev_session_id']);

        return redirect()->route('dashboard')->with('success', 'Kembali ke akun Developer.');
    }

    public function clearCache(ActivityLogger $logger)
    {
        Artisan::call('optimize:clear');
        $logger->log('dev.cache_cleared', 'Developer membersihkan cache aplikasi', null, null, [], [], [], 'warning');

        return back()->with('success', 'Cache, config, view, dan route berhasil dibersihkan.');
    }

    public function reconcile(ActivityLogger $logger)
    {
        $exitCode = Artisan::call('star:reconcile');
        $output = trim(Artisan::output());
        $logger->log('dev.reconcile_ran', 'Developer menjalankan reconciliation', null, null, ['output' => $output], [], [], 'info');

        return back()->with('success', 'Reconciliation selesai (exit '.$exitCode.'). '.$output);
    }

    public function reseed(Request $request)
    {
        abort_unless(config('app.dev_reset'), 404);

        $this->validateConfirmation($request, 'RESEED');
        $this->writeResetLog('demo');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Artisan::call('dev:reset', ['--demo' => true]);

        return redirect()->route('login', ['reset' => 'reseeded']);
    }

    public function resetEmpty(Request $request)
    {
        abort_unless(config('app.dev_reset'), 404);

        $this->validateConfirmation($request, 'DELETE ALL');
        $this->writeResetLog('empty');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Artisan::call('dev:reset', ['--empty' => true]);

        return redirect()->route('login', ['reset' => 'emptied']);
    }

    public function forceTaskStatus(Request $request, Task $task, ProgressService $progress, ActivityLogger $logger)
    {
        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,blocked,review,done,cancelled'],
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        DB::transaction(function () use ($task, $data, $progress, $logger) {
            $fresh = Task::whereKey($task->id)->lockForUpdate()->firstOrFail();
            $old = $fresh->status;

            $fresh->status = $data['status'];
            $fresh->completed_at = $data['status'] === 'done' ? now() : null;
            $fresh->lock_version = (int) $fresh->lock_version + 1;
            $fresh->save();

            $progress->recalcTask($fresh);

            $logger->log(
                'dev.task_status_forced',
                'Developer memaksa status task '.$fresh->name.' dari '.$old.' ke '.$data['status'],
                $fresh->event,
                $fresh,
                ['note' => $data['note'], 'forced' => true],
                ['status' => $old],
                ['status' => $data['status']],
                'warning'
            );
        });

        return back()->with('success', 'Status task dipaksa menjadi '.$data['status'].'.');
    }

    public function unpublishReport(ReportVersion $report, ActivityLogger $logger)
    {
        if ($report->status !== 'published') {
            return back()->withErrors(['report' => 'Laporan belum berstatus published.']);
        }

        $report->update(['status' => 'draft', 'published_at' => null]);
        $logger->log('dev.report_unpublished', 'Developer membuka publikasi laporan v'.$report->version, $report->event, $report, [], ['status' => 'published'], ['status' => 'draft'], 'warning');

        return back()->with('success', 'Laporan dikembalikan ke draft.');
    }

    public function deleteRecord(Request $request, string $type, int $id, ActivityLogger $logger)
    {
        $models = [
            'event' => Event::class,
            'task' => Task::class,
            'expense' => Expense::class,
            'invoice' => ClientInvoice::class,
            'report' => ReportVersion::class,
            'media' => MediaItem::class,
            'dealer' => Dealer::class,
            'user' => User::class,
        ];

        abort_unless(isset($models[$type]), 404);
        $request->validate(['confirmation' => ['required', 'in:DELETE']]);

        /** @var \Illuminate\Database\Eloquent\Model|null $model */
        $model = $models[$type]::find($id);

        if (! $model) {
            return back()->withErrors(['delete' => 'Data tidak ditemukan.']);
        }

        if ($model instanceof User) {
            if ((int) $model->id === (int) auth()->id()) {
                return back()->withErrors(['delete' => 'Tidak bisa menghapus akun sendiri.']);
            }

            if ($model->isDev() && User::where('role', 'dev')->count() <= 1) {
                return back()->withErrors(['delete' => 'Tidak bisa menghapus Developer terakhir.']);
            }
        }

        $label = class_basename($model).' #'.$model->getKey();
        $event = $model instanceof Event ? $model : ($model->event ?? null);

        try {
            $logger->log('dev.record_deleted', 'Developer menghapus '.$label, $event, null, ['type' => $type, 'id' => $id], [], [], 'danger');
            $model->delete();
        } catch (QueryException $exception) {
            return back()->withErrors(['delete' => 'Gagal menghapus: data masih direferensikan modul lain.']);
        }

        return back()->with('success', $label.' dihapus.');
    }

    private function validateConfirmation(Request $request, string $expected): void
    {
        $value = (string) $request->input('confirmation');

        if ($value !== $expected) {
            throw ValidationException::withMessages([
                'confirmation' => 'Ketik "'.$expected.'" untuk melanjutkan.',
            ]);
        }
    }

    private function closeCurrentDevSession(): void
    {
        $sessionId = session('dev_session_id');

        if ($sessionId) {
            DevSession::whereKey($sessionId)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
    }

    private function writeResetLog(string $mode): void
    {
        File::append(
            storage_path('logs/dev-reset.log'),
            '['.now()->toDateTimeString().'] dev='.(auth()->user()?->email ?? 'unknown').' mode='.$mode.PHP_EOL
        );
    }
}
