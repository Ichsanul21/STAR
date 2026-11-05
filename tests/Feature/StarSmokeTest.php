<?php

namespace Tests\Feature;

use App\Models\BudgetItem;
use App\Models\ClientInvoice;
use App\Models\Event;
use App\Models\Expense;
use App\Models\ReportVersion;
use App\Models\SalesRecord;
use App\Models\Task;
use App\Models\User;
use App\Models\AppSetting;
use App\Services\FinanceService;
use App\Services\NumberingService;
use App\Services\ProgressService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StarSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    public function test_internal_user_can_view_dashboard_and_event(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/events')->assertOk();
        $this->actingAs($user)->get('/events/'.$event->id)->assertOk()->assertSee($event->name);
    }

    public function test_dealer_only_sees_own_portal(): void
    {
        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();

        $this->actingAs($dealer)->get('/portal')->assertOk();
        $this->actingAs($dealer)->get('/events')->assertOk();
    }

    public function test_progress_rolls_up_from_checklist_to_event(): void
    {
        $event = Event::firstOrFail();
        $task = Task::where('event_id', $event->id)->firstOrFail();
        $progress = app(ProgressService::class);

        $task->checklists()->delete();
        $task->update(['status' => 'in_progress']);
        $task->checklists()->create(['label' => 'Langkah 1', 'sort_order' => 0]);
        $task->checklists()->create(['label' => 'Langkah 2', 'sort_order' => 1]);
        $task->checklists()->first()->update(['is_done' => true]);

        $progress->recalcTask($task);
        $task->refresh();

        $this->assertSame(50.0, (float) $task->progress);
        $this->assertGreaterThanOrEqual(0, (float) $task->group->fresh()->progress);
        $this->assertGreaterThanOrEqual(0, (float) $task->group->phase->fresh()->progress);
    }

    public function test_expense_approval_updates_budget_actual(): void
    {
        $event = Event::firstOrFail();
        $item = BudgetItem::whereHas('version', fn ($q) => $q->where('event_id', $event->id))->firstOrFail();

        $expense = Expense::create([
            'event_id' => $event->id,
            'budget_item_id' => $item->id,
            'expense_date' => now()->toDateString(),
            'category' => $item->category,
            'description' => 'Test expense',
            'qty' => 1,
            'unit_price' => 1000000,
            'amount' => 1000000,
            'payment_method' => 'transfer',
            'status' => 'approved',
        ]);

        app(FinanceService::class)->recalcExpense($expense);

        $this->assertGreaterThan(0, (float) $item->fresh()->actual_amount);
    }

    public function test_xlsx_rab_import_creates_budget_items(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Kategori', 'Uraian', 'Volume', 'Satuan', 'Harga Satuan'],
            ['Produksi', 'Sewa panggung', 1, 'set', 12000000],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'rab_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        $upload = new \Illuminate\Http\UploadedFile($path, 'rab.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($user)
            ->post('/events/'.$event->id.'/budget/import', ['file' => $upload])
            ->assertRedirect();

        $this->assertDatabaseHas('budget_items', [
            'description' => 'Sewa panggung',
            'planned_amount' => 12000000,
        ]);

        @unlink($path);
    }

    public function test_xlsx_rundown_and_planning_imports(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $rundown = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $rundown->getActiveSheet()->fromArray([
            ['Tanggal', 'Mulai', 'Selesai', 'Aktivitas', 'PIC', 'Lokasi', 'Status'],
            ['2026-11-08', '09:00', '10:00', 'Opening ceremony', 'Budi', 'Main stage', 'planned'],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'rundown_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($rundown))->save($path);

        $this->actingAs($user)->post('/events/'.$event->id.'/import/rundown', [
            'file' => new \Illuminate\Http\UploadedFile($path, 'rundown.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect();

        $this->assertDatabaseHas('schedule_items', ['activity' => 'Opening ceremony']);
        @unlink($path);

        $planning = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $planning->getActiveSheet()->fromArray([
            ['Aspek', 'Deskripsi', 'Target', 'PIC', 'Status'],
            ['Tujuan Event', 'Meningkatkan lead', '200 lead', 'Budi', 'open'],
        ], null, 'A1');
        $path2 = tempnam(sys_get_temp_dir(), 'planning_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($planning))->save($path2);

        $this->actingAs($user)->post('/events/'.$event->id.'/import/planning', [
            'file' => new \Illuminate\Http\UploadedFile($path2, 'planning.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect();

        $this->assertDatabaseHas('planning_items', ['aspect' => 'Tujuan Event']);
        @unlink($path2);
    }

    public function test_xlsx_preview_and_mapping_commit(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $book->getActiveSheet()->fromArray([
            ['Kategori', 'Uraian', 'Volume', 'Satuan', 'Harga Satuan'],
            ['Produksi', 'Sewa panggung', 1, 'set', 12000000],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'map_').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);

        $this->actingAs($user)->post('/events/'.$event->id.'/imports/preview', [
            'type' => 'rab',
            'file' => new \Illuminate\Http\UploadedFile($path, 'rab.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ])->assertRedirect();

        $batch = \App\Models\ImportBatch::firstOrFail();
        $this->actingAs($user)->get('/imports/'.$batch->id.'/map')->assertOk();

        $this->actingAs($user)->post('/imports/'.$batch->id.'/commit', [
            'mapping' => [
                'category' => 'A',
                'description' => 'B',
                'qty' => 'C',
                'unit' => 'D',
                'unit_price' => 'E',
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('budget_items', ['description' => 'Sewa panggung']);
        @unlink($path);
    }

    public function test_dependency_graph_calculates_critical_path(): void
    {
        $event = Event::firstOrFail();
        $tasks = Task::where('event_id', $event->id)->take(2)->get();

        $tasks[0]->update(['start_at' => now(), 'due_at' => now()->addDays(2)]);
        $tasks[1]->update(['start_at' => now()->addDays(2), 'due_at' => now()->addDays(4)]);
        \App\Models\TaskDependency::where('task_id', $tasks[1]->id)->delete();
        \App\Models\TaskDependency::create([
            'task_id' => $tasks[1]->id,
            'depends_on_task_id' => $tasks[0]->id,
            'type' => 'finish_to_start',
        ]);

        $graph = app(\App\Services\DependencyGraphService::class)->build($event->fresh());

        $this->assertNotEmpty($graph['edges']);
        $this->assertNotEmpty($graph['critical']);
    }

    public function test_report_narrative_carries_to_next_version(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($user)->post('/events/'.$event->id.'/reports')->assertRedirect();
        $draft = $event->reports()->firstOrFail();

        $this->actingAs($user)->post('/reports/'.$draft->id.'/narrative', [
            'summary' => 'Narasi uji.',
            'evaluation' => 'Evaluasi uji.',
            'recommendations' => 'Rekomendasi uji.',
            'conclusion' => 'Kesimpulan uji.',
        ])->assertRedirect();

        $this->actingAs($user)->post('/events/'.$event->id.'/reports')->assertRedirect();

        $this->assertSame('Narasi uji.', $event->reports()->firstOrFail()->narrative['summary']);
    }

    public function test_event_roles_are_enforced(): void
    {
        $event = Event::firstOrFail();
        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();
        $field = User::where('email', 'field@star.test')->firstOrFail();
        $pm = User::where('email', 'pm@star.test')->firstOrFail();

        $this->actingAs($dealer)->get('/events/'.$event->id)->assertOk();
        $this->actingAs($dealer)->patch('/events/'.$event->id, ['name' => 'Hack'])->assertForbidden();

        $this->actingAs($field)->get('/events/'.$event->id)->assertOk();
        $this->actingAs($field)->patch('/events/'.$event->id, ['name' => 'Hack'])->assertForbidden();

        $this->actingAs($pm)->get('/events/'.$event->id)->assertOk();
    }

    public function test_field_can_advance_task_with_note_and_history(): void
    {
        $field = User::where('email', 'field@star.test')->firstOrFail();
        $task = Task::where('status', 'todo')->firstOrFail();

        $this->actingAs($field)->post('/tasks/'.$task->id.'/advance', [
            'to_status' => 'in_progress',
            'note' => 'Pekerjaan dimulai oleh field crew.',
            'lock_version' => $task->lock_version,
        ])->assertRedirect();

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('task_updates', [
            'task_id' => $task->id,
            'type' => 'status',
            'from_status' => 'todo',
            'to_status' => 'in_progress',
        ]);
    }

    public function test_admin_cannot_override_task_status_and_backward_is_rejected(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $field = User::where('email', 'field@star.test')->firstOrFail();
        $task = Task::where('status', 'todo')->firstOrFail();

        $this->actingAs($admin)->post('/tasks/'.$task->id.'/advance', [
            'to_status' => 'in_progress',
            'note' => 'Admin tidak boleh override.',
            'lock_version' => $task->lock_version,
        ])->assertSessionHasErrors('task');

        $this->actingAs($field)->post('/tasks/'.$task->id.'/advance', [
            'to_status' => 'in_progress',
            'note' => 'Field memulai task.',
            'lock_version' => $task->lock_version,
        ])->assertRedirect();

        $this->actingAs($field)->post('/tasks/'.$task->id.'/advance', [
            'to_status' => 'todo',
            'note' => 'Coba mundur.',
            'lock_version' => $task->fresh()->lock_version,
        ])->assertSessionHasErrors('to_status');
    }

    public function test_checklist_completion_records_note_and_cannot_be_reopened(): void
    {
        $field = User::where('email', 'field@star.test')->firstOrFail();
        $checklist = \App\Models\TaskChecklist::where('is_done', false)->firstOrFail();

        $this->actingAs($field)->post('/checklists/'.$checklist->id.'/complete', [
            'note' => 'Checklist selesai dengan bukti.',
            'lock_version' => $checklist->lock_version,
        ])->assertRedirect();

        $this->assertTrue($checklist->fresh()->is_done);
        $this->assertDatabaseHas('task_updates', [
            'task_checklist_id' => $checklist->id,
            'type' => 'checklist',
        ]);

        $this->actingAs($field)->post('/checklists/'.$checklist->id.'/complete', [
            'note' => 'Coba selesaikan lagi.',
        ])->assertSessionHasErrors('checklist');
    }

    public function test_reviewer_can_create_linked_revision_task(): void
    {
        $reviewer = User::where('email', 'reviewer@star.test')->firstOrFail();
        $task = Task::firstOrFail();

        $this->actingAs($reviewer)->post('/tasks/'.$task->id.'/revisions', [
            'note' => 'Dokumentasi kurang lengkap, buat revisi.',
        ])->assertRedirect();

        $revision = Task::where('revision_of_id', $task->id)->firstOrFail();
        $this->assertTrue($revision->is_revision);
        $this->assertSame($task->task_group_id, $revision->task_group_id);
    }

    public function test_activity_log_visibility_and_request_details(): void
    {
        config(['audit.enabled' => true]);

        $super = User::where('email', 'admin@star.test')->firstOrFail();
        $eo = User::where('email', 'eo@star.test')->firstOrFail();
        $pm = User::where('email', 'pm@star.test')->firstOrFail();

        \App\Models\ActivityLog::create([
            'user_id' => $super->id,
            'action' => 'test.audit',
            'description' => 'Audit test',
            'ip_address' => '10.10.10.10',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'platform' => 'Windows',
        ]);

        $this->actingAs($super)->get('/activity-logs')->assertOk()->assertSee('IP / Device');
        $this->actingAs($eo)->get('/activity-logs')->assertOk()->assertDontSee('IP / Device');
        $this->actingAs($pm)->get('/activity-logs')->assertForbidden();
    }

    public function test_graph_positions_are_persisted(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();
        $task = Task::where('event_id', $event->id)->firstOrFail();

        $this->actingAs($user)->post('/events/'.$event->id.'/graph/positions', [
            'positions' => [
                $task->id => ['x' => 123.5, 'y' => 456.5],
            ],
        ])->assertOk();

        $this->assertEquals(123.5, (float) $task->fresh()->graph_x);
        $this->assertEquals(456.5, (float) $task->fresh()->graph_y);
    }

    public function test_report_section_order_can_be_saved(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($user)->post('/events/'.$event->id.'/reports')->assertRedirect();
        $report = $event->reports()->firstOrFail();

        $sections = ['signoff', 'summary', 'cover', 'result', 'tasks', 'payments', 'expenses', 'documentation', 'budget', 'progress', 'evaluation'];

        $this->actingAs($user)->post('/reports/'.$report->id.'/sections', [
            'sections' => $sections,
        ])->assertRedirect();

        $this->assertSame($sections, $report->fresh()->section_order);
    }

    public function test_demo_login_buttons_authenticate_selected_account(): void
    {
        config(['app.demo_login' => true]);

        $this->post('/demo-login/admin')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs(User::where('email', 'admin@star.test')->firstOrFail());

        auth()->logout();

        $this->post('/demo-login/dealer-bdg')->assertRedirect('/portal');
        $this->assertAuthenticatedAs(User::where('email', 'dealer.bdg@star.test')->firstOrFail());
    }

    public function test_global_pages_render_for_internal_user(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();

        foreach (['/my-tasks', '/budget', '/expenses', '/payments', '/documents', '/reports', '/master-data', '/settings'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
    }

    public function test_wysiwyg_narrative_is_sanitized(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($user)->post('/events/'.$event->id.'/reports')->assertRedirect();
        $report = $event->reports()->firstOrFail();

        $this->actingAs($user)->post('/reports/'.$report->id.'/narrative', [
            'summary' => '<p><strong>Bold</strong><script>alert(1)</script></p>',
            'evaluation' => '<h2>Evaluasi</h2><p>Aman</p>',
        ])->assertRedirect();

        $summary = $report->fresh()->narrative['summary'];

        $this->assertStringContainsString('<strong>Bold</strong>', $summary);
        $this->assertStringNotContainsString('<script', $summary);
    }

    public function test_stale_lock_version_is_rejected(): void
    {
        $field = User::where('email', 'field@star.test')->firstOrFail();
        $task = Task::where('status', 'todo')->firstOrFail();

        $this->actingAs($field)->post('/tasks/'.$task->id.'/advance', [
            'to_status' => 'in_progress',
            'note' => 'Update dengan versi lama.',
            'lock_version' => 0,
        ])->assertSessionHasErrors('conflict');
    }

    public function test_report_generation_creates_pdf_and_xlsx(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($user)
            ->post('/events/'.$event->id.'/reports')
            ->assertRedirect();

        $report = $event->reports()->firstOrFail();

        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->xlsx_path);
        Storage::disk('local')->assertExists($report->pdf_path);
        Storage::disk('local')->assertExists($report->xlsx_path);
    }

    public function test_client_module_and_global_search_render(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();
        $dealer = \App\Models\Dealer::firstOrFail();

        $this->actingAs($user)->get('/clients')->assertOk()->assertSee($dealer->name);
        $this->actingAs($user)->get('/clients/'.$dealer->id)->assertOk()->assertSee($dealer->name);
        $this->actingAs($user)->get('/search?q='.urlencode($dealer->name))->assertOk()->assertSee($dealer->name);

        $dealerUser = User::where('email', 'dealer@star.test')->firstOrFail();
        $this->actingAs($dealerUser)->get('/clients')->assertForbidden();
    }

    public function test_global_export_streams_csv(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();

        foreach (['budget', 'expenses', 'payments', 'reports', 'clients'] as $type) {
            $response = $this->actingAs($user)->get('/exports/'.$type);
            $response->assertOk();
            $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        }
    }

    public function test_bulk_expense_approval_updates_status(): void
    {
        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $expenses = Expense::where('status', 'submitted')->take(2)->get();

        $this->actingAs($finance)->post('/expenses/bulk', [
            'action' => 'approve',
            'expense_ids' => $expenses->pluck('id')->all(),
        ])->assertRedirect();

        foreach ($expenses as $expense) {
            $this->assertSame('approved', $expense->fresh()->status);
        }
    }

    public function test_gantt_reschedule_updates_task_dates(): void
    {
        $admin = User::where('email', 'pm@star.test')->firstOrFail();
        $task = Task::whereNotNull('due_at')->firstOrFail();
        $event = $task->event;

        $this->actingAs($admin)->patchJson('/tasks/'.$task->id.'/schedule', [
            'start_at' => '2026-05-01',
            'due_at' => '2026-05-05',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('2026-05-01', $task->start_at->toDateString());
        $this->assertSame('2026-05-05', $task->due_at->toDateString());

        $this->actingAs($admin)->patchJson('/tasks/'.$task->id.'/schedule', [
            'start_at' => '2026-05-10',
            'due_at' => '2026-05-01',
        ])->assertStatus(422);

        $this->actingAs($admin)->get('/events/'.$event->id.'/tasks?zoom=day')->assertOk();
    }

    public function test_dashboard_renders_financial_chart_with_toggles(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Perbandingan keuangan')
            ->assertSee('data-finance-toggle="revenue"', false)
            ->assertSee('id="financeChart"', false)
            ->assertSee('data-financial', false)
            ->assertSee('Piutang');
    }

    public function test_timeline_uses_click_modal_and_has_scurve_view(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($admin)->get('/events/'.$event->id.'/tasks?view=timeline')
            ->assertOk()
            ->assertSee('ganttTaskModal', false)
            ->assertSee('data-gantt-task', false)
            ->assertDontSee('data-draggable', false);

        $this->actingAs($admin)->get('/events/'.$event->id.'/tasks?view=scurve')
            ->assertOk()
            ->assertSee('S-Curve Progress')
            ->assertSee('data-scurve', false)
            ->assertSee('Rencana hari ini');
    }

    public function test_task_edit_updates_schedule_and_rejects_inverted_dates(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $task = Task::whereNotNull('due_at')->firstOrFail();

        $this->actingAs($admin)->patch('/tasks/'.$task->id, [
            'name' => 'Agenda diperbarui',
            'description' => 'Catatan agenda',
            'priority' => 'high',
            'assigned_to' => null,
            'start_at' => '2026-06-01T08:00',
            'due_at' => '2026-06-03T17:00',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('Agenda diperbarui', $task->name);
        $this->assertSame('2026-06-01 08:00', $task->start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-06-03 17:00', $task->due_at->format('Y-m-d H:i'));

        $this->actingAs($admin)->patch('/tasks/'.$task->id, [
            'name' => $task->name,
            'priority' => $task->priority,
            'start_at' => '2026-06-05T08:00',
            'due_at' => '2026-06-01T17:00',
        ])->assertSessionHasErrors('due_at');
    }

    public function test_numbering_service_generates_sequential_numbers(): void
    {
        $service = app(NumberingService::class);

        $first = $service->next('event');
        $second = $service->next('event');

        $this->assertMatchesRegularExpression('/^EVT-\d{6}-0001$/', $first);
        $this->assertMatchesRegularExpression('/^EVT-\d{6}-0002$/', $second);
        $this->assertMatchesRegularExpression('/^EVT-\d{6}-0001$/', $service->preview('event'));
    }

    public function test_event_creation_uses_auto_numbering(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $dealer = \App\Models\Dealer::firstOrFail();

        $this->actingAs($admin)->post('/events', [
            'dealer_id' => $dealer->id,
            'name' => 'Event Numbering Test',
            'type' => 'activation',
            'location' => 'Jakarta',
            'city' => 'Jakarta',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(11)->toDateString(),
            'capture_mode' => 'upload_or_camera',
            'contract_value' => 1000000,
        ])->assertRedirect();

        $event = Event::where('name', 'Event Numbering Test')->firstOrFail();

        $this->assertMatchesRegularExpression('/^EVT-\d{6}-\d{4}$/', $event->code);
    }

    public function test_invoice_number_is_generated_when_blank(): void
    {
        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($finance)->post('/invoices', [
            'event_id' => $event->id,
            'invoice_number' => '',
            'term' => 'Termin Otomatis',
            'amount' => 1000,
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $invoice = ClientInvoice::where('event_id', $event->id)->where('term', 'Termin Otomatis')->firstOrFail();

        $this->assertMatchesRegularExpression('/^INV-'.preg_quote($event->code, '/').'-02$/', $invoice->invoice_number);
    }

    public function test_report_number_is_generated(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $report = app(ReportService::class)->generate($event, $admin);

        $this->assertNotNull($report->report_number);
        $this->assertMatchesRegularExpression('/^RPT-'.preg_quote($event->code, '/').'-01$/', $report->report_number);
    }

    public function test_spk_number_is_generated_when_blank(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($admin)->post('/events/'.$event->id.'/sales', [
            'model' => 'Honda Test',
            'unit_price' => 1000000,
            'spk_number' => '',
            'status' => 'spk',
        ])->assertRedirect();

        $sale = SalesRecord::where('event_id', $event->id)->where('model', 'Honda Test')->firstOrFail();

        $this->assertMatchesRegularExpression('/^SPK-\d{6}-0001$/', $sale->spk_number);
    }

    public function test_numbering_settings_can_be_updated_by_eo_admin_only(): void
    {
        $eo = User::where('email', 'eo@star.test')->firstOrFail();
        $finance = User::where('email', 'finance@star.test')->firstOrFail();

        $formats = [
            'event_format' => 'EVT-{YY}{MM}-{seq:3}',
            'invoice_format' => 'INV-{event}-{seq:3}',
            'spk_format' => 'SPK-{YY}{MM}-{seq:3}',
            'report_format' => 'RPT-{event}-{seq:3}',
        ];

        $this->actingAs($eo)->patch('/settings/numbering', $formats)->assertRedirect();
        $this->assertSame('EVT-{YY}{MM}-{seq:3}', AppSetting::get('numbering.event'));

        $this->actingAs($finance)->patch('/settings/numbering', $formats)->assertForbidden();
    }

    public function test_super_admin_can_create_dealer_and_user(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($admin)->post('/master-data/dealers', [
            'code' => 'TEST-01',
            'name' => 'Dealer Test',
            'city' => 'Jakarta',
        ])->assertRedirect();

        $this->assertDatabaseHas('dealers', ['code' => 'TEST-01']);

        $this->actingAs($admin)->post('/master-data/users', [
            'name' => 'User Test',
            'email' => 'user.test@star.test',
            'password' => 'password123',
            'role' => 'finance',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'user.test@star.test']);

        $eo = User::where('email', 'eo@star.test')->firstOrFail();
        $this->actingAs($eo)->post('/master-data/dealers', [
            'code' => 'TEST-02',
            'name' => 'Dealer EO',
        ])->assertForbidden();
    }

    public function test_version_conflict_is_recorded_with_diff(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $task = Task::firstOrFail();
        $task->forceFill(['lock_version' => 3])->save();

        $this->actingAs($admin)->patch('/tasks/'.$task->id, [
            'name' => 'Nama Baru',
            'priority' => 'high',
            'lock_version' => 1,
        ])->assertSessionHasErrors('conflict');

        $conflict = \App\Models\ConflictReport::latest('id')->firstOrFail();

        $this->assertSame('Task', $conflict->model_type);
        $this->assertSame($task->id, $conflict->model_id);
        $this->assertSame(1, $conflict->expected_version);
        $this->assertSame(3, $conflict->actual_version);
        $this->assertSame('Nama Baru', $conflict->attempted['name']);

        $this->actingAs($admin)->get('/conflicts')->assertOk()->assertSee('Task');
        $this->actingAs($admin)->get('/conflicts/'.$conflict->id)->assertOk()->assertSee('Merge Data');

        $this->actingAs($admin)->post('/conflicts/'.$conflict->id.'/resolve')->assertRedirect();
        $this->assertNotNull($conflict->fresh()->resolved_at);

        $field = User::where('email', 'field@star.test')->firstOrFail();
        $this->actingAs($field)->get('/conflicts/'.$conflict->id)->assertForbidden();

        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();
        $this->actingAs($dealer)->get('/conflicts')->assertForbidden();
    }

    public function test_json_version_conflict_returns_409(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $expense = Expense::whereIn('status', ['submitted', 'draft', 'rejected'])->firstOrFail();
        $expense->forceFill(['lock_version' => 5])->save();

        $this->actingAs($admin)->patchJson('/expenses/'.$expense->id, [
            'expense_date' => '2026-11-04',
            'category' => 'Produksi',
            'description' => 'Uji konflik JSON',
            'qty' => 1,
            'unit_price' => 1000,
            'payment_method' => 'transfer',
            'lock_version' => 1,
        ])->assertStatus(409)->assertJsonPath('conflict', true);

        $this->assertSame(5, $expense->fresh()->lock_version);
        $this->assertNotNull(\App\Models\ConflictReport::latest('id')->first());
    }

    public function test_conflict_current_endpoint_returns_server_version(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $task = Task::firstOrFail();

        $this->actingAs($admin)
            ->getJson('/conflicts/current?type=task&id='.$task->id)
            ->assertOk()
            ->assertJsonPath('lock_version', (int) $task->lock_version)
            ->assertJsonStructure(['attributes' => ['name', 'status']]);

        $this->actingAs($admin)->getJson('/conflicts/current?type=unknown&id=1')->assertStatus(422);

        $reviewer = User::where('email', 'reviewer@star.test')->firstOrFail();
        $expense = Expense::firstOrFail();
        $this->actingAs($reviewer)->getJson('/conflicts/current?type=expense&id='.$expense->id)->assertForbidden();
    }

    public function test_master_data_can_update_and_toggle_dealer_and_user(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $dealer = \App\Models\Dealer::whereDoesntHave('events', fn ($q) => $q->where('status', 'active'))->firstOrFail();

        $this->actingAs($admin)->patch('/master-data/dealers/'.$dealer->id, [
            'code' => $dealer->code,
            'name' => 'Nama Dealer Baru',
            'city' => 'Surabaya',
        ])->assertRedirect();

        $this->assertSame('Nama Dealer Baru', $dealer->fresh()->name);

        $this->actingAs($admin)->post('/master-data/dealers/'.$dealer->id.'/toggle')->assertRedirect();
        $this->assertFalse($dealer->fresh()->is_active);

        $this->actingAs($admin)->post('/master-data/dealers/'.$dealer->id.'/toggle')->assertRedirect();
        $this->assertTrue($dealer->fresh()->is_active);

        $activeDealer = \App\Models\Dealer::whereHas('events', fn ($q) => $q->where('status', 'active'))->firstOrFail();
        $this->actingAs($admin)->post('/master-data/dealers/'.$activeDealer->id.'/toggle')->assertSessionHasErrors('dealer');
        $this->assertTrue($activeDealer->fresh()->is_active);

        $reviewer = User::where('email', 'reviewer@star.test')->firstOrFail();

        $this->actingAs($admin)->patch('/master-data/users/'.$reviewer->id, [
            'name' => 'Reviewer Baru',
            'email' => 'reviewer.baru@star.test',
            'role' => 'reviewer',
        ])->assertRedirect();

        $this->assertSame('Reviewer Baru', $reviewer->fresh()->name);

        $this->actingAs($admin)->post('/master-data/users/'.$reviewer->id.'/toggle')->assertRedirect();
        $this->assertFalse($reviewer->fresh()->is_active);

        $this->actingAs($admin)->post('/master-data/users/'.$admin->id.'/toggle')->assertSessionHasErrors('user');
    }

    public function test_notification_preferences_are_respected(): void
    {
        $user = User::where('email', 'reviewer@star.test')->firstOrFail();
        $event = Event::firstOrFail();
        $service = app(\App\Services\NotificationService::class);

        $user->update(['notification_preferences' => ['notify_tasks' => false]]);
        $service->send($user->fresh(), 'Uji Preferensi', 'Body', $event, 'task');

        $this->assertDatabaseMissing('app_notifications', ['user_id' => $user->id, 'title' => 'Uji Preferensi']);

        $user->update(['notification_preferences' => ['notify_tasks' => true]]);
        $service->send($user->fresh(), 'Uji Preferensi', 'Body', $event, 'task');

        $this->assertDatabaseHas('app_notifications', ['user_id' => $user->id, 'title' => 'Uji Preferensi']);
    }

    public function test_settings_saves_notification_and_view_preferences(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($admin)->patch('/settings', [
            'name' => $admin->name,
            'prefs' => ['notify_tasks' => '1'],
        ])->assertRedirect();

        $admin->refresh();
        $this->assertTrue($admin->notification_preferences['notify_tasks']);
        $this->assertFalse($admin->notification_preferences['notify_reports']);

        $response = $this->actingAs($admin)->post('/preferences/event-view', ['view' => 'list', 'status' => 'active']);
        $response->assertRedirect(route('events.index', ['status' => 'active', 'view' => 'list']));

        $this->assertSame('list', $admin->fresh()->default_event_view);
        $this->actingAs($admin)->get('/events')->assertOk()->assertSee('lg:grid-cols-12');
    }

    public function test_offline_queue_page_renders(): void
    {
        $user = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($user)->get('/offline')->assertOk()->assertSee('Offline Queue');
        $this->actingAs($user)->get('/conflicts')->assertOk();
    }

    public function test_lookup_endpoints_respect_access(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($admin)->getJson('/lookup/events?q=Honda')->assertOk()->assertJsonStructure(['items' => [['value', 'text']]]);
        $this->actingAs($admin)->getJson('/lookup/cities')->assertOk();
        $this->actingAs($admin)->getJson('/lookup/locations')->assertOk();
        $this->actingAs($admin)->getJson('/lookup/models')->assertOk()->assertJsonFragment(['text' => 'Honda BeAT']);
        $this->actingAs($admin)->getJson('/events/'.$event->id.'/budget-items')->assertOk()->assertJsonStructure(['items']);
        $this->actingAs($admin)->getJson('/events/'.$event->id.'/finance-context')->assertOk()->assertJsonStructure(['contract', 'invoiced', 'remaining', 'suggested_number']);

        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();
        $this->actingAs($dealer)->getJson('/lookup/events')->assertForbidden();
    }

    public function test_global_expense_can_be_created_for_allowed_event(): void
    {
        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $event = Event::firstOrFail();
        $item = \App\Models\BudgetItem::whereHas('version', fn ($q) => $q->where('event_id', $event->id))->firstOrFail();

        $this->actingAs($finance)->post('/expenses', [
            'event_id' => $event->id,
            'expense_date' => now()->toDateString(),
            'category' => 'produksi',
            'description' => 'Expense global uji',
            'qty' => 1,
            'unit_price' => 250000,
            'budget_item_id' => $item->id,
            'payment_method' => 'transfer',
        ])->assertRedirect();

        $this->assertDatabaseHas('expenses', ['event_id' => $event->id, 'description' => 'Expense global uji', 'status' => 'submitted']);

        $otherEvent = Event::where('id', '!=', $event->id)->firstOrFail();
        $otherItem = \App\Models\BudgetItem::whereHas('version', fn ($q) => $q->where('event_id', $otherEvent->id))->firstOrFail();

        $this->actingAs($finance)->post('/expenses', [
            'event_id' => $event->id,
            'expense_date' => now()->toDateString(),
            'category' => 'produksi',
            'description' => 'Budget item beda event',
            'qty' => 1,
            'unit_price' => 100000,
            'budget_item_id' => $otherItem->id,
            'payment_method' => 'transfer',
        ])->assertSessionHasErrors('budget_item_id');

        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();
        $this->actingAs($dealer)->post('/expenses', ['event_id' => $event->id])->assertForbidden();
    }

    public function test_global_invoice_and_payment_can_be_created(): void
    {
        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $event = \App\Models\Event::whereHas('invoices')->firstOrFail();

        $this->actingAs($finance)->post('/invoices', [
            'event_id' => $event->id,
            'invoice_number' => 'INV-GLOBAL-TEST-01',
            'term' => 'termin_2',
            'amount' => 1000000,
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertRedirect();

        $invoice = \App\Models\ClientInvoice::where('invoice_number', 'INV-GLOBAL-TEST-01')->firstOrFail();

        $this->actingAs($finance)->post('/payments', [
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'method' => 'transfer',
        ])->assertRedirect();

        $this->assertDatabaseHas('client_payments', ['client_invoice_id' => $invoice->id, 'amount' => 500000]);
        $this->assertSame('partial', $invoice->fresh()->derivedStatus());
    }

    public function test_three_way_merge_applies_non_conflicting_changes(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $expense = Expense::whereIn('status', ['submitted', 'draft', 'rejected'])->firstOrFail();
        $lock = (int) $expense->lock_version;
        $base = $this->expenseBaseSnapshot($expense);

        $expense->forceFill(['vendor' => 'Vendor Server', 'lock_version' => $lock + 1])->save();

        $this->actingAs($admin)->patch('/expenses/'.$expense->id, [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'description' => 'Deskripsi dari user',
            'qty' => $expense->qty,
            'unit_price' => $expense->unit_price,
            'vendor' => $base['vendor'],
            'payment_method' => $expense->payment_method,
            'budget_item_id' => $expense->budget_item_id,
            'lock_version' => $lock,
            'base_snapshot' => json_encode($base),
        ])->assertSessionHasErrors('conflict');

        $conflict = \App\Models\ConflictReport::latest('id')->firstOrFail();
        $this->assertNotEmpty($conflict->base_snapshot);

        $plan = app(\App\Services\ConflictMergeService::class)->plan($conflict);
        $this->assertSame('user', collect($plan)->firstWhere('field', 'description')['resolution']);
        $this->assertSame('server', collect($plan)->firstWhere('field', 'vendor')['resolution']);

        $this->actingAs($admin)->post('/conflicts/'.$conflict->id.'/merge', [])->assertRedirect();

        $expense->refresh();
        $this->assertSame('Deskripsi dari user', $expense->description);
        $this->assertSame('Vendor Server', $expense->vendor);
        $this->assertSame($lock + 2, (int) $expense->lock_version);
        $this->assertNotNull($conflict->fresh()->merged_at);
    }

    public function test_three_way_merge_requires_choice_for_conflicting_field(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $expense = Expense::whereIn('status', ['submitted', 'draft', 'rejected'])->firstOrFail();

        $expense->forceFill(['description' => 'Base desc', 'lock_version' => 10])->save();
        $base = $this->expenseBaseSnapshot($expense);

        $expense->forceFill(['description' => 'Server desc', 'lock_version' => 11])->save();

        $this->actingAs($admin)->patch('/expenses/'.$expense->id, [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'description' => 'User desc',
            'qty' => $expense->qty,
            'unit_price' => $expense->unit_price,
            'vendor' => $expense->vendor,
            'payment_method' => $expense->payment_method,
            'budget_item_id' => $expense->budget_item_id,
            'lock_version' => 10,
            'base_snapshot' => json_encode($base),
        ])->assertSessionHasErrors('conflict');

        $conflict = \App\Models\ConflictReport::latest('id')->firstOrFail();
        $plan = app(\App\Services\ConflictMergeService::class)->plan($conflict);
        $this->assertTrue(collect($plan)->firstWhere('field', 'description')['disputed']);

        $this->actingAs($admin)->post('/conflicts/'.$conflict->id.'/merge', [])->assertSessionHasErrors('decisions');
        $this->assertSame('Server desc', $expense->fresh()->description);

        $this->actingAs($admin)->post('/conflicts/'.$conflict->id.'/merge', [
            'decisions' => ['description' => 'user'],
        ])->assertRedirect();

        $this->assertSame('User desc', $expense->fresh()->description);
    }

    public function test_vehicle_models_can_be_managed_by_eo_admin(): void
    {
        $eo = User::where('email', 'eo@star.test')->firstOrFail();

        $this->actingAs($eo)->post('/master-data/lookups', [
            'group' => 'vehicle_models',
            'label' => 'Honda EM1 e',
        ])->assertRedirect();

        $this->assertDatabaseHas('lookups', ['group' => 'vehicle_models', 'value' => 'honda_em1_e']);

        $lookup = \App\Models\Lookup::where('value', 'honda_em1_e')->firstOrFail();
        $this->actingAs($eo)->post('/master-data/lookups/'.$lookup->id.'/toggle')->assertRedirect();
        $this->assertFalse($lookup->fresh()->is_active);
        $this->actingAs($eo)->getJson('/lookup/models?q=EM1')->assertOk()->assertJsonMissing(['text' => 'Honda EM1 e']);

        $dealer = User::where('email', 'dealer@star.test')->firstOrFail();
        $this->actingAs($dealer)->post('/master-data/lookups', [
            'group' => 'vehicle_models',
            'label' => 'Tidak boleh',
        ])->assertForbidden();
    }

    public function test_documents_page_exposes_preview_metadata(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($admin)->get('/documents')
            ->assertOk()
            ->assertSee('data-preview-meta', false);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Performa event')
            ->assertSee('data-dashboard-chart', false)
            ->assertSee('notificationPopover', false)
            ->assertSee('data-notif-trigger', false)
            ->assertSee('toggleSidebar', false);
    }

    public function test_expense_notifications_are_categorized(): void
    {
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $event = Event::firstOrFail();
        $item = \App\Models\BudgetItem::whereHas('version', fn ($q) => $q->where('event_id', $event->id))->firstOrFail();

        $this->actingAs($admin)->post('/expenses', [
            'event_id' => $event->id,
            'expense_date' => now()->toDateString(),
            'category' => 'produksi',
            'description' => 'Notifikasi expense uji',
            'qty' => 1,
            'unit_price' => 50000,
            'budget_item_id' => $item->id,
            'payment_method' => 'transfer',
        ])->assertRedirect();

        $expense = \App\Models\Expense::where('description', 'Notifikasi expense uji')->firstOrFail();

        $this->actingAs($finance)->get('/notifications?type=finance')
            ->assertOk()
            ->assertSee('Expense menunggu approval')
            ->assertSee('Notifikasi expense uji')
            ->assertSee('sidebar-notif-row is-active', false);

        $this->actingAs($finance)->get('/notifications?type=task')
            ->assertOk()
            ->assertSee('Perlu tindakan', false);

        $this->actingAs($finance)->get('/dashboard')
            ->assertOk()
            ->assertSee('Keuangan')
            ->assertSee('sidebar-notif', false);

        $this->actingAs($finance)->post('/expenses/'.$expense->id.'/approve', [
            'lock_version' => $expense->lock_version,
        ])->assertRedirect();

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $admin->id,
            'type' => 'expense',
            'title' => 'Expense disetujui',
        ]);

        $this->actingAs($finance)->get('/notifications?type=finance')
            ->assertOk()
            ->assertDontSee('Notifikasi expense uji');
    }

    public function test_dev_role_can_access_everything_and_dev_tools(): void
    {
        config(['app.dev_tools' => true, 'app.dev_impersonation' => true, 'app.dev_reset' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $event = Event::firstOrFail();

        $this->actingAs($dev)->get('/dev')->assertOk()->assertSee('Dev Tools')->assertSee('Impersonation');
        $this->actingAs($dev)->get('/events')->assertOk()->assertSee($event->name);
        $this->actingAs($dev)->get('/expenses')->assertOk();
        $this->actingAs($dev)->get('/activity-logs')->assertOk();

        config(['app.dev_tools' => false]);
        $this->actingAs($dev)->get('/dev')->assertRedirect(route('login'));
        $this->post('/demo-login/dev')->assertNotFound();
    }

    public function test_dev_can_impersonate_and_return(): void
    {
        config(['app.dev_tools' => true, 'app.dev_impersonation' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $finance = User::where('email', 'finance@star.test')->firstOrFail();

        $this->actingAs($dev)->post('/dev/impersonate/'.$finance->id)->assertRedirect();
        $this->assertAuthenticatedAs($finance->fresh());

        $session = \App\Models\DevSession::latest('id')->firstOrFail();
        $this->assertSame($dev->id, $session->dev_id);
        $this->assertSame($finance->id, $session->target_id);
        $this->assertNull($session->ended_at);

        $this->get('/dashboard')->assertOk()->assertSee('Mode simulasi')->assertSee('Kembali ke Developer');

        $this->post('/dev/stop-impersonation')->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($dev->fresh());
        $this->assertNotNull($session->fresh()->ended_at);
    }

    public function test_non_dev_cannot_impersonate(): void
    {
        config(['app.dev_tools' => true, 'app.dev_impersonation' => true]);

        $finance = User::where('email', 'finance@star.test')->firstOrFail();
        $target = User::where('email', 'pm@star.test')->firstOrFail();

        $this->actingAs($finance)->post('/dev/impersonate/'.$target->id)->assertForbidden();
    }

    public function test_dev_activity_hidden_from_non_dev_audit(): void
    {
        config(['app.dev_tools' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $admin = User::where('email', 'admin@star.test')->firstOrFail();
        $task = Task::firstOrFail();

        $this->actingAs($dev)->post('/dev/tasks/'.$task->id.'/force-status', [
            'status' => 'todo',
            'note' => 'Force untuk pengujian dev',
        ])->assertRedirect();

        $this->assertSame('todo', $task->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'dev.task_status_forced',
            'user_id' => $dev->id,
        ]);

        $this->actingAs($admin)->get('/activity-logs')
            ->assertOk()
            ->assertDontSee('dev.task_status_forced');

        $this->actingAs($dev)->get('/activity-logs')
            ->assertOk()
            ->assertSee('dev.task_status_forced');
    }

    public function test_dev_can_force_task_status_backward(): void
    {
        config(['app.dev_tools' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $task = Task::firstOrFail();
        $task->forceFill(['status' => 'in_progress'])->save();

        $this->actingAs($dev)->post('/dev/tasks/'.$task->id.'/force-status', [
            'status' => 'todo',
            'note' => 'Kembalikan ke todo untuk testing',
        ])->assertRedirect();

        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_only_dev_can_assign_dev_role(): void
    {
        config(['app.dev_tools' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $admin = User::where('email', 'admin@star.test')->firstOrFail();

        $this->actingAs($admin)->post('/master-data/users', [
            'name' => 'Dev Baru',
            'email' => 'dev.baru@star.test',
            'password' => 'password123',
            'role' => 'dev',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'dev.baru@star.test']);

        $this->actingAs($dev)->post('/master-data/users', [
            'name' => 'Dev Baru',
            'email' => 'dev.baru@star.test',
            'password' => 'password123',
            'role' => 'dev',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'dev.baru@star.test', 'role' => 'dev']);
    }

    public function test_dev_reset_requires_confirmation(): void
    {
        config(['app.dev_tools' => true, 'app.dev_reset' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $eventCount = Event::count();

        $this->actingAs($dev)->post('/dev/reset/demo', ['confirmation' => 'SALAH'])
            ->assertSessionHasErrors('confirmation');

        $this->assertSame($eventCount, Event::count());
    }

    public function test_dev_can_edit_final_rab(): void
    {
        config(['app.dev_tools' => true]);

        $dev = User::where('email', 'dev@star.test')->firstOrFail();
        $final = \App\Models\BudgetVersion::where('stage', 'final')->firstOrFail();
        $before = $final->items()->count();

        $this->actingAs($dev)->post('/budget/'.$final->id.'/items', [
            'category' => 'produksi',
            'description' => 'Item tambahan dev',
            'qty' => 1,
            'unit' => 'unit',
            'unit_price' => 10000,
        ])->assertRedirect();

        $this->assertSame($before + 1, $final->items()->count());
    }

    private function expenseBaseSnapshot(Expense $expense): array
    {
        return [
            'expense_date' => $expense->expense_date?->toDateString(),
            'category' => $expense->category,
            'description' => $expense->description,
            'qty' => (float) $expense->qty,
            'unit_price' => (float) $expense->unit_price,
            'vendor' => $expense->vendor,
            'payment_method' => $expense->payment_method,
            'paid_by' => $expense->paid_by,
            'notes' => $expense->notes,
            'budget_item_id' => $expense->budget_item_id,
        ];
    }
}
