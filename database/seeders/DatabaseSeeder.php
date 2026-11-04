<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\BudgetItem;
use App\Models\BudgetVersion;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Dealer;
use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventResult;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\MediaItem;
use App\Models\PlanningItem;
use App\Models\ReportVersion;
use App\Models\SalesRecord;
use App\Models\ScheduleItem;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TaskUpdate;
use App\Models\User;
use App\Models\VisitorEntry;
use App\Services\EventSetupService;
use App\Services\FinanceService;
use App\Services\NotificationService;
use App\Services\ProgressService;
use App\Services\ReportService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        config(['audit.enabled' => false]);

        $this->seedLookups();

        $jakarta = Dealer::create(['code' => 'HND-JKT', 'name' => 'Honda Dealer Jakarta', 'city' => 'Jakarta', 'address' => 'Jl. Sudirman No. 1', 'phone' => '021-555-0100', 'pic_name' => 'Rina Dealer']);
        $bandung = Dealer::create(['code' => 'HND-BDG', 'name' => 'Honda Dealer Bandung', 'city' => 'Bandung', 'address' => 'Jl. Asia Afrika No. 8', 'phone' => '022-555-0200', 'pic_name' => 'Dedi Dealer']);
        $surabaya = Dealer::create(['code' => 'HND-SBY', 'name' => 'Honda Dealer Surabaya', 'city' => 'Surabaya', 'address' => 'Jl. Basuki Rahmat No. 12', 'phone' => '031-555-0300', 'pic_name' => 'Sinta Dealer']);

        $accounts = [
            ['name' => 'Developer', 'email' => 'dev@star.test', 'role' => 'dev'],
            ['name' => 'Ikhsan Admin', 'email' => 'admin@star.test', 'role' => 'super_admin'],
            ['name' => 'EO Admin', 'email' => 'eo@star.test', 'role' => 'eo_admin'],
            ['name' => 'Finance Officer', 'email' => 'finance@star.test', 'role' => 'finance'],
            ['name' => 'PM Event', 'email' => 'pm@star.test', 'role' => 'pm_event'],
            ['name' => 'Field Crew', 'email' => 'field@star.test', 'role' => 'field_crew'],
            ['name' => 'Reviewer Event', 'email' => 'reviewer@star.test', 'role' => 'reviewer'],
            ['name' => 'Dealer Admin Jakarta', 'email' => 'dealer@star.test', 'role' => 'dealer_admin', 'dealer_id' => $jakarta->id],
            ['name' => 'Dealer Viewer Jakarta', 'email' => 'dealer.viewer@star.test', 'role' => 'dealer_viewer', 'dealer_id' => $jakarta->id],
            ['name' => 'PIC Divisi', 'email' => 'pic@star.test', 'role' => 'division_pic'],
            ['name' => 'Dealer Admin Bandung', 'email' => 'dealer.bdg@star.test', 'role' => 'dealer_admin', 'dealer_id' => $bandung->id],
            ['name' => 'Dealer Admin Surabaya', 'email' => 'dealer.sby@star.test', 'role' => 'dealer_admin', 'dealer_id' => $surabaya->id],
        ];

        foreach ($accounts as $account) {
            User::create($account + ['password' => Hash::make('password'), 'is_active' => true]);
        }

        $admin = User::where('email', 'admin@star.test')->first();
        $eo = User::where('email', 'eo@star.test')->first();
        $finance = User::where('email', 'finance@star.test')->first();
        $pm = User::where('email', 'pm@star.test')->first();
        $field = User::where('email', 'field@star.test')->first();
        $reviewer = User::where('email', 'reviewer@star.test')->first();
        $dealerUsers = [
            'jkt' => User::where('email', 'dealer@star.test')->first(),
            'bdg' => User::where('email', 'dealer.bdg@star.test')->first(),
            'sby' => User::where('email', 'dealer.sby@star.test')->first(),
        ];

        $events = [
            [
                'key' => 'jkt', 'dealer' => $jakarta, 'dealer_user' => $dealerUsers['jkt'],
                'code' => 'EVT-'.now()->format('Ym').'-JKT', 'name' => 'Honda Dealer Activation Weekend',
                'type' => 'activation', 'location' => 'Main Atrium Mall Kelapa Gading', 'city' => 'Jakarta',
                'start' => now()->addDays(3), 'end' => now()->addDays(4), 'status' => 'active', 'state' => 'upcoming',
                'capture' => 'camera_only', 'targets' => [500, 120, 25],
                'description' => 'Aktivasi dealer Honda dengan test drive, booth display, dan program penjualan.',
            ],
            [
                'key' => 'bdg', 'dealer' => $bandung, 'dealer_user' => $dealerUsers['bdg'],
                'code' => 'EVT-'.now()->format('Ym').'-BDG', 'name' => 'Honda Test Drive Roadshow Bandung',
                'type' => 'test_drive', 'location' => 'Summarecon Mall Bandung', 'city' => 'Bandung',
                'start' => now()->subDays(12), 'end' => now()->subDays(11), 'status' => 'closed', 'state' => 'closed',
                'capture' => 'upload_or_camera', 'targets' => [350, 90, 18],
                'description' => 'Roadshow test drive dengan konvoi unit dan booth konsultasi dealer.',
            ],
            [
                'key' => 'sby', 'dealer' => $surabaya, 'dealer_user' => $dealerUsers['sby'],
                'code' => 'EVT-'.now()->format('Ym').'-SBY', 'name' => 'Honda Dealer Gathering Surabaya',
                'type' => 'gathering', 'location' => 'Grand City Convex', 'city' => 'Surabaya',
                'start' => now()->addDays(14), 'end' => now()->addDays(14), 'status' => 'active', 'state' => 'upcoming',
                'capture' => 'camera_only', 'targets' => [250, 60, 12],
                'description' => 'Gathering pelanggan dan dealer dengan product knowledge, test drive, dan entertainment.',
            ],
        ];

        $setup = app(EventSetupService::class);
        $progress = app(ProgressService::class);
        $financeService = app(FinanceService::class);
        $notifier = app(NotificationService::class);

        foreach ($events as $index => $config) {
            $event = Event::create([
                'code' => $config['code'],
                'dealer_id' => $config['dealer']->id,
                'name' => $config['name'],
                'type' => $config['type'],
                'location' => $config['location'],
                'city' => $config['city'],
                'start_date' => $config['start'],
                'end_date' => $config['end'],
                'status' => $config['status'],
                'capture_mode' => $config['capture'],
                'target_visitors' => $config['targets'][0],
                'target_leads' => $config['targets'][1],
                'target_units' => $config['targets'][2],
                'description' => $config['description'],
                'created_by' => $index === 0 ? $admin->id : $eo->id,
            ]);

            $setup->bootstrap($event);

            $this->seedTeam($event, $pm, $finance, $field, $reviewer, $config['dealer_user']);
            $this->seedTasks($event, $progress, $pm, $finance, $field, $reviewer, $config['state']);
            $this->seedPlanning($event, $index);
            $final = $this->seedBudget($event, $index, $financeService);
            $event->update(['contract_value' => round((float) $final->total_planned * 1.2, 2)]);
            $this->seedExpenses($event, $final, $index, $finance, $admin, $financeService);
            $this->seedPayments($event, $final, $index, $financeService);
            $this->seedVisitors($event, $index, $field);
            $this->seedSales($event, $index);

            if (! app()->runningUnitTests()) {
                $this->seedMedia($event, $index, $field);
                $this->seedTaskUpdates($event, $index, $pm, $field);
                $this->seedReport($event, $index, $admin, $config['dealer_user'], $notifier);
            }

            ActivityLog::create([
                'user_id' => $index === 0 ? $admin->id : $eo->id,
                'event_id' => $event->id,
                'action' => 'event.created',
                'description' => 'Event '.$event->name.' dibuat untuk '.$event->dealer->name.'.',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36',
                'device_type' => 'desktop',
                'browser' => 'Chrome',
                'platform' => 'Windows',
                'route' => 'events.store',
                'http_method' => 'POST',
                'status_code' => 302,
                'severity' => 'info',
            ]);
        }

        config(['audit.enabled' => true]);
    }

    private function seedLookups(): void
    {
        $group = 'vehicle_models';

        foreach (array_keys(config('star_options.vehicle_models', [])) as $index => $value) {
            Lookup::updateOrCreate(
                ['group' => $group, 'value' => $value],
                ['label' => config("star_options.vehicle_models.{$value}"), 'sort_order' => $index, 'is_active' => true]
            );
        }
    }

    private function seedTeam(Event $event, User $pm, User $finance, User $field, User $reviewer, User $dealerUser): void
    {
        $divisions = $event->divisions;
        $pic = User::where('email', 'pic@star.test')->first();

        EventMember::create(['event_id' => $event->id, 'user_id' => $pm->id, 'role' => 'pm_event']);
        EventMember::create(['event_id' => $event->id, 'user_id' => $finance->id, 'role' => 'division_pic', 'division_id' => $divisions->firstWhere('code', 'finance')?->id]);
        EventMember::create(['event_id' => $event->id, 'user_id' => $field->id, 'role' => 'field_crew', 'division_id' => $divisions->firstWhere('code', 'documentation')?->id]);
        EventMember::create(['event_id' => $event->id, 'user_id' => $reviewer->id, 'role' => 'reviewer']);
        EventMember::create(['event_id' => $event->id, 'user_id' => $dealerUser->id, 'role' => 'viewer']);

        if ($pic) {
            EventMember::firstOrCreate(
                ['event_id' => $event->id, 'user_id' => $pic->id, 'role' => 'division_pic', 'division_id' => $divisions->firstWhere('code', 'production')?->id],
            );
        }
    }

    private function seedTasks(Event $event, ProgressService $progress, User $pm, User $finance, User $field, User $reviewer, string $state): void
    {
        $assignees = [$pm, $field, $reviewer, $finance];
        $tasks = $event->tasks()->with('group.phase')->orderBy('id')->get();

        foreach ($tasks as $index => $task) {
            $task->assigned_to = $assignees[$index % count($assignees)]->id;

            if ($state === 'closed') {
                $task->status = 'done';
                $task->completed_at = now()->subDays(2);
            } else {
                $phaseCode = $task->group?->phase?->code;
                $task->status = match (true) {
                    $phaseCode === 'prep' && $index < 8 => 'done',
                    $phaseCode === 'prep' => 'in_progress',
                    $phaseCode === 'event' && $index % 2 === 0 => 'in_progress',
                    default => 'todo',
                };
            }

            $task->save();

            if ($index < 8) {
                foreach ([['Persiapan dokumen', $state === 'closed'], ['Konfirmasi PIC', $state !== 'upcoming' || $index < 4]] as $itemIndex => [$label, $done]) {
                    $task->checklists()->create(['label' => $label, 'is_done' => $done, 'sort_order' => $itemIndex]);
                }
            }

            $progress->recalcTask($task);
        }

        foreach ($event->taskGroups as $group) {
            $groupTasks = $group->tasks()->orderBy('sort_order')->get();
            if ($groupTasks->count() >= 2) {
                TaskDependency::firstOrCreate([
                    'task_id' => $groupTasks[1]->id,
                    'depends_on_task_id' => $groupTasks[0]->id,
                ], ['type' => 'finish_to_start']);
                $progress->recalcTask($groupTasks[1]);
            }
        }
    }

    private function seedPlanning(Event $event, int $index): void
    {
        $plans = [
            [['Tujuan Event', 'Meningkatkan lead dan penjualan dealer', 'Target tercapai', 'PM Event', 'open'], ['Target Audiens', 'Keluarga muda dan profesional muda', '500 visitor', 'Marketing', 'open'], ['KPI', 'Lead, test drive, SPK, unit terjual', 'Sesuai target', 'PM Event', 'open']],
            [['Tujuan Event', 'Membangun pipeline test drive Bandung', '90 lead', 'PM Event', 'done'], ['Target Audiens', 'Pemilik mobil keluarga', '350 visitor', 'Marketing', 'done'], ['Evaluasi', 'Review hasil roadshow', 'Laporan final', 'Reviewer', 'done']],
            [['Tujuan Event', 'Meningkatkan engagement pelanggan Surabaya', '60 lead', 'PM Event', 'open'], ['Target Audiens', 'Pelanggan existing dan komunitas', '250 visitor', 'Marketing', 'open']],
        ];

        foreach ($plans[$index] as $order => [$aspect, $description, $target, $pic, $status]) {
            PlanningItem::create([
                'event_id' => $event->id,
                'aspect' => $aspect,
                'description' => $description,
                'target' => $target,
                'pic' => $pic,
                'deadline' => $event->start_date?->copy()->subDays(2),
                'status' => $status,
                'sort_order' => $order,
            ]);
        }

        $rundowns = [
            [['09:00', '10:00', 'Opening ceremony', 'PM Event', 'Main stage', 'opening'], ['10:00', '12:00', 'Test drive & booth', 'Field Crew', 'Area parkir', 'test_drive'], ['13:00', '15:00', 'Sales consultation', 'Sales', 'Booth dealer', 'activation'], ['15:00', '16:00', 'Closing & doorprize', 'PM Event', 'Main stage', 'closing']],
            [['08:00', '09:00', 'Briefing & konvoi', 'PM Event', 'Start point', 'opening'], ['09:00', '12:00', 'Test drive route', 'Field Crew', 'Route Bandung', 'test_drive'], ['12:00', '14:00', 'Booth consultation', 'Sales', 'Mall atrium', 'activation'], ['14:00', '15:00', 'Closing', 'PM Event', 'Mall atrium', 'closing']],
            [['10:00', '11:00', 'Opening & product knowledge', 'PM Event', 'Grand City', 'opening'], ['11:00', '13:00', 'Test drive & games', 'Field Crew', 'Area parkir', 'test_drive'], ['13:00', '15:00', 'Gathering & entertainment', 'PM Event', 'Main hall', 'entertainment']],
        ];

        foreach ($rundowns[$index] as $order => [$start, $end, $activity, $pic, $location, $category]) {
            ScheduleItem::create([
                'event_id' => $event->id,
                'date' => $event->start_date,
                'start_time' => $start,
                'end_time' => $end,
                'activity' => $activity,
                'pic' => $pic,
                'location' => $location,
                'category' => $category,
                'target_participants' => 100,
                'actual_participants' => $event->status === 'closed' ? 120 : 0,
                'status' => $event->status === 'closed' ? 'done' : 'planned',
                'sort_order' => $order,
            ]);
        }
    }

    private function seedBudget(Event $event, int $index, FinanceService $finance): BudgetVersion
    {
        $groups = $event->taskGroups->keyBy('name');
        $items = [
            ['Produksi', 'Sewa booth & backdrop', 1, 'set', 25000000 + ($index * 2000000), 'Produksi & Logistik'],
            ['Produksi', 'Sound system & lighting', 1, 'set', 8500000, 'Produksi & Logistik'],
            ['Konsumsi', 'Catering crew & vendor', 40, 'pax', 75000, 'Tim & Briefing'],
            ['Promosi', 'Cetak materi promosi', 1, 'paket', 4500000, 'Tim & Briefing'],
            ['Operasional', 'Transport & logistik', 1, 'paket', 3000000, 'Perencanaan & Perizinan'],
            ['Fee', 'Fee talent & MC', 2, 'orang', 3500000, 'Eksekusi Event'],
        ];

        $planning = BudgetVersion::create([
            'event_id' => $event->id,
            'stage' => 'planning',
            'version' => 1,
            'status' => 'draft',
        ]);

        foreach ($items as $order => [$category, $description, $qty, $unit, $price, $groupName]) {
            BudgetItem::create([
                'budget_version_id' => $planning->id,
                'category' => $category,
                'description' => $description,
                'qty' => $qty,
                'unit' => $unit,
                'unit_price' => $price,
                'planned_amount' => $qty * $price,
                'task_group_id' => $groups[$groupName]?->id,
                'sort_order' => $order,
            ]);
        }

        $finance->recalcVersion($planning);
        $planning->update(['status' => 'approved', 'approved_by' => User::where('email', 'admin@star.test')->value('id'), 'approved_at' => now()]);

        $final = BudgetVersion::create([
            'event_id' => $event->id,
            'stage' => 'final',
            'version' => 1,
            'status' => 'approved',
            'approved_by' => User::where('email', 'admin@star.test')->value('id'),
            'approved_at' => now(),
        ]);

        foreach ($planning->items as $item) {
            $final->items()->create([
                'stable_key' => $item->stable_key,
                'category' => $item->category,
                'description' => $item->description,
                'qty' => $item->qty,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'planned_amount' => $item->planned_amount,
                'task_group_id' => $item->task_group_id,
                'sort_order' => $item->sort_order,
            ]);
        }

        $finance->recalcVersion($final);

        return $final;
    }

    private function seedExpenses(Event $event, BudgetVersion $final, int $index, User $finance, User $admin, FinanceService $financeService): void
    {
        $items = $final->items->keyBy('description');
        $tasks = $event->tasks()->get()->keyBy('name');
        $data = [
            ['Sewa booth & backdrop', 'Produksi', 'Pembayaran DP booth', 15000000, 'PT Booth Kreatif', 'approved', 'Produksi booth'],
            ['Sound system & lighting', 'Produksi', 'Sewa sound system', 8500000, 'PT Audio Jaya', 'approved', 'Operasional booth'],
            ['Catering crew & vendor', 'Konsumsi', 'Catering briefing crew', 1500000, 'Katering Sehat', $event->status === 'closed' ? 'approved' : 'submitted', 'Briefing tim'],
            ['Cetak materi promosi', 'Promosi', 'Cetak banner & flyer', 4500000, 'Percetakan Cepat', 'approved', 'Finalisasi materi promosi'],
        ];

        foreach ($data as $offset => [$description, $category, $expenseDescription, $amount, $vendor, $status, $taskName]) {
            $item = $items[$description] ?? $final->items->first();
            $expense = Expense::create([
                'event_id' => $event->id,
                'budget_item_id' => $item?->id,
                'task_id' => $tasks[$taskName]?->id,
                'expense_date' => $event->start_date?->copy()->subDays(3 - $offset) ?? now()->subDays(3 - $offset),
                'category' => $category,
                'description' => $expenseDescription,
                'qty' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'vendor' => $vendor,
                'payment_method' => 'transfer',
                'paid_by' => 'Finance Officer',
                'status' => $status,
                'approved_by' => in_array($status, ['approved', 'paid'], true) ? $admin->id : null,
                'approved_at' => in_array($status, ['approved', 'paid'], true) ? now()->subDays(2) : null,
                'created_by' => $finance->id,
            ]);

            $financeService->recalcExpense($expense);
        }
    }

    private function seedPayments(Event $event, BudgetVersion $final, int $index, FinanceService $finance): void
    {
        $invoice = ClientInvoice::create([
            'event_id' => $event->id,
            'invoice_number' => 'INV-'.$event->code.'-01',
            'term' => 'Termin 1',
            'description' => 'Pembayaran pelaksanaan event',
            'amount' => round((float) $final->total_planned * 0.7, 2),
            'due_date' => $event->start_date?->copy()->addDays(5),
            'status' => 'unpaid',
        ]);

        ClientPayment::create([
            'client_invoice_id' => $invoice->id,
            'amount' => $event->status === 'closed' ? (float) $invoice->amount : round((float) $invoice->amount * 0.5, 2),
            'paid_at' => now()->subDays(5),
            'method' => 'transfer',
            'verified_by' => User::where('email', 'finance@star.test')->value('id'),
            'verified_at' => now()->subDays(5),
            'status' => 'verified',
        ]);

        $finance->recalcInvoice($invoice);
    }

    private function seedVisitors(Event $event, int $index, User $field): void
    {
        $cities = $event->city === 'Jakarta' ? ['Jakarta', 'Bekasi', 'Depok'] : ($event->city === 'Bandung' ? ['Bandung', 'Cimahi', 'Sumedang'] : ['Surabaya', 'Sidoarjo', 'Gresik']);
        $names = [['Andi Wijaya', 'Honda Brio', 'hot'], ['Siti Rahma', 'Honda HR-V', 'warm'], ['Budi Santoso', 'Honda BR-V', 'follow_up'], ['Maya Putri', 'Honda City', 'new'], ['Rizky Pratama', 'Honda Accord', 'cold']];

        foreach ($names as $offset => [$name, $model, $leadStatus]) {
            VisitorEntry::create([
                'event_id' => $event->id,
                'name' => $name,
                'phone' => '08123456'.str_pad((string) ($index * 10 + $offset), 4, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                'city' => $cities[$offset % count($cities)],
                'province' => $event->city === 'Jakarta' ? 'DKI Jakarta' : ($event->city === 'Bandung' ? 'Jawa Barat' : 'Jawa Timur'),
                'source' => ['walk_in', 'social_media', 'invitation', 'referral', 'dealer_database'][$offset],
                'interest_model' => $model,
                'budget_range' => $offset % 2 === 0 ? '200-300jt' : '300-400jt',
                'payment_method' => $offset % 2 === 0 ? 'credit' : 'cash',
                'test_drive' => $offset < 3,
                'lead_status' => $leadStatus,
                'sales_pic' => 'Sales Dealer',
                'follow_up_date' => now()->addDays($offset + 1),
                'consent' => true,
                'created_by' => $field->id,
            ]);
        }

        $this->refreshResult($event);
    }

    private function seedSales(Event $event, int $index): void
    {
        SalesRecord::create([
            'event_id' => $event->id,
            'model' => 'Honda Brio',
            'unit_price' => 210000000,
            'spk_number' => 'SPK-'.$event->code.'-001',
            'status' => $event->status === 'closed' ? 'handover' : 'spk',
            'sold_at' => $event->start_date,
            'notes' => 'Data demo sales.',
        ]);

        if ($event->status === 'closed') {
            SalesRecord::create([
                'event_id' => $event->id,
                'model' => 'Honda HR-V',
                'unit_price' => 380000000,
                'spk_number' => 'SPK-'.$event->code.'-002',
                'status' => 'delivered',
                'sold_at' => $event->start_date,
            ]);
        }

        $this->refreshResult($event);
    }

    private function refreshResult(Event $event): void
    {
        $visitorCount = $event->visitors()->count();
        $leadCount = $event->visitors()->where('lead_status', '!=', 'lost')->count();
        $testDriveCount = $event->visitors()->where('test_drive', true)->count();
        $spk = $event->sales()->whereIn('status', ['spk', 'delivered', 'handover'])->count();
        $units = $event->sales()->whereIn('status', ['delivered', 'handover'])->count();
        $revenue = (float) $event->sales()->whereIn('status', ['delivered', 'handover'])->sum('unit_price');
        $cost = (float) $event->expenses()->whereIn('status', ['approved', 'paid', 'reimbursed'])->sum('amount');

        EventResult::updateOrCreate(['event_id' => $event->id], [
            'visitors' => $visitorCount,
            'unique_visitors' => $event->visitors()->distinct('phone')->count('phone'),
            'leads' => $leadCount,
            'test_drives' => $testDriveCount,
            'spk' => $spk,
            'units_sold' => $units,
            'revenue' => $revenue,
            'handover' => $event->sales()->where('status', 'handover')->count(),
            'cost_per_lead' => $leadCount > 0 ? round($cost / $leadCount, 2) : 0,
            'roi' => $cost > 0 ? round(($revenue - $cost) / $cost * 100, 2) : 0,
            'notes' => 'Data demo terintegrasi untuk '.$event->name.'.',
        ]);
    }

    private function seedMedia(Event $event, int $index, User $uploader): void
    {
        $captions = [
            'Persiapan - Setup booth',
            'Hari-H - Aktivasi pengunjung',
            'Hari-H - Test drive',
            'Pasca-Event - Dokumentasi',
        ];
        $collections = [[10, 37, 64], [204, 0, 0], [15, 59, 59], [30, 41, 59]];
        $phases = $event->phases;

        foreach ($captions as $offset => $caption) {
            $width = 1200;
            $height = 800;
            $image = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($image, ...$collections[($index + $offset) % count($collections)]);
            $white = imagecolorallocate($image, 255, 255, 255);
            imagefill($image, 0, 0, $bg);
            imagestring($image, 5, 40, 40, 'STAR - Supported by Alenkosa', $white);
            imagestring($image, 5, 40, 80, $event->name, $white);
            imagestring($image, 3, 40, 120, $caption, $white);
            imagestring($image, 2, 40, 150, $event->city.' - '.$event->start_date?->format('d/m/Y'), $white);

            $path = "events/{$event->id}/watermarked/demo-{$offset}.jpg";
            Storage::disk('local')->makeDirectory("events/{$event->id}/watermarked");
            imagejpeg($image, Storage::disk('local')->path($path), 85);
            imagedestroy($image);

            MediaItem::create([
                'event_id' => $event->id,
                'event_phase_id' => $phases[$offset % max(1, $phases->count())]?->id,
                'path' => $path,
                'watermarked_path' => $path,
                'original_name' => "demo-{$offset}.jpg",
                'mime' => 'image/jpeg',
                'size' => filesize(Storage::disk('local')->path($path)),
                'width' => $width,
                'height' => $height,
                'caption' => $caption,
                'captured_at' => $event->start_date?->copy()->addHours(8 + $offset) ?? now()->subDays(1),
                'gps_lat' => $event->city === 'Jakarta' ? -6.158 : ($event->city === 'Bandung' ? -6.917 : -7.257),
                'gps_lng' => $event->city === 'Jakarta' ? 106.905 : ($event->city === 'Bandung' ? 107.619 : 112.752),
                'gps_accuracy' => 12,
                'address' => $event->location.', '.$event->city,
                'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
                'status' => $event->status === 'closed' ? 'approved' : ($offset < 2 ? 'approved' : 'pending'),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    private function seedTaskUpdates(Event $event, int $index, User $pm, User $field): void
    {
        $media = $event->media()->first();

        foreach ($event->tasks()->take(6)->get() as $offset => $task) {
            TaskUpdate::create([
                'event_id' => $event->id,
                'event_phase_id' => $task->group?->event_phase_id,
                'task_id' => $task->id,
                'user_id' => $offset % 2 === 0 ? $field->id : $pm->id,
                'type' => 'status',
                'from_status' => 'todo',
                'to_status' => 'in_progress',
                'note' => 'Pekerjaan dimulai. Koordinasi dengan PIC dan vendor sudah dilakukan.',
                'photo_path' => $offset === 0 ? $media?->path : null,
            ]);

            if (! in_array($task->status, ['todo'], true)) {
                TaskUpdate::create([
                    'event_id' => $event->id,
                    'event_phase_id' => $task->group?->event_phase_id,
                    'task_id' => $task->id,
                    'user_id' => $offset % 2 === 0 ? $pm->id : $field->id,
                    'type' => 'status',
                    'from_status' => 'in_progress',
                    'to_status' => $task->status,
                    'note' => 'Update progres sesuai status task saat ini. Dokumentasi dan catatan sudah dilengkapi.',
                    'photo_path' => $offset === 1 ? $media?->path : null,
                ]);
            }
        }
    }

    private function seedReport(Event $event, int $index, User $admin, User $dealerUser, NotificationService $notifier): void
    {
        $report = app(ReportService::class)->generate($event, $admin, [
            'summary' => 'Event '.$event->name.' terlaksana sesuai rencana dengan fokus pada akuisisi lead dan pengalaman test drive.',
            'evaluation' => 'Dokumentasi dan koordinasi tim berjalan baik. Beberapa aktivitas perlu penyesuaian waktu pada event berikutnya.',
            'recommendations' => 'Perkuat follow-up lead H+1 dan pastikan kelengkapan dokumentasi sebelum closing event.',
            'conclusion' => 'Laporan ini menjadi baseline untuk evaluasi event dan submission ke dealer.',
        ]);

        if ($event->status === 'closed') {
            $report->update([
                'status' => 'published',
                'approved_by' => $admin->id,
                'published_at' => now()->subDays(3),
                'acknowledged_by' => $dealerUser->id,
                'acknowledged_at' => now()->subDays(2),
            ]);

            $notifier->send($dealerUser, 'Laporan event dipublikasikan', $event->name.' v'.$report->version, $event, 'report');
        } else {
            $notifier->send($dealerUser, 'Laporan draft tersedia', $event->name.' v'.$report->version, $event, 'report');
        }

        ActivityLog::create([
            'user_id' => $admin->id,
            'event_id' => $event->id,
            'action' => $event->status === 'closed' ? 'report.published' : 'report.generated',
            'description' => 'Laporan v'.$report->version.' untuk '.$event->name.'.',
        ]);
    }
}
