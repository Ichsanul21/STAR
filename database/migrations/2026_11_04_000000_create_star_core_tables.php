<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('pic_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('dealer_id')->nullable()->after('id')->constrained('dealers')->nullOnDelete();
            $table->string('role')->default('field_crew')->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('dealer_id')->constrained('dealers')->restrictOnDelete();
            $table->string('name');
            $table->string('type')->default('activation');
            $table->string('location')->nullable();
            $table->string('city')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('status')->default('active');
            $table->string('capture_mode')->default('upload_or_camera');
            $table->unsignedInteger('target_visitors')->default(0);
            $table->unsignedInteger('target_leads')->default(0);
            $table->unsignedInteger('target_units')->default(0);
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('event_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('weight', 8, 2)->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status')->default('todo');
            $table->decimal('progress', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('event_divisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('field_crew');
            $table->foreignId('division_id')->nullable()->constrained('event_divisions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'role', 'division_id']);
        });

        Schema::create('task_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->constrained('event_phases')->cascadeOnDelete();
            $table->foreignId('event_division_id')->nullable()->constrained('event_divisions')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('status')->default('todo');
            $table->decimal('progress', 5, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('task_group_id')->constrained('task_groups')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 8, 2)->default(1);
            $table->string('status')->default('todo');
            $table->decimal('progress', 5, 2)->default(0);
            $table->string('priority')->default('medium');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('task_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type')->default('finish_to_start');
            $table->integer('lag_minutes')->default(0);
            $table->timestamps();
            $table->unique(['task_id', 'depends_on_task_id']);
        });

        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('source')->default('timer');
            $table->text('note')->nullable();
            $table->boolean('is_billable')->default(false);
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('client_uuid')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('stage')->default('planning');
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->decimal('total_planned', 18, 2)->default(0);
            $table->decimal('total_actual', 18, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'stage', 'version']);
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_version_id')->constrained('budget_versions')->cascadeOnDelete();
            $table->string('stable_key', 36);
            $table->string('category');
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->string('unit')->default('unit');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('planned_amount', 18, 2)->default(0);
            $table->decimal('actual_amount', 18, 2)->default(0);
            $table->foreignId('task_group_id')->nullable()->constrained('task_groups')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['budget_version_id', 'stable_key']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('budget_item_id')->nullable()->constrained('budget_items')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->date('expense_date');
            $table->string('category');
            $table->string('description');
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('vendor')->nullable();
            $table->string('payment_method')->default('transfer');
            $table->string('paid_by')->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('status')->default('submitted');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('client_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('term')->default('Termin 1');
            $table->string('description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamps();
        });

        Schema::create('client_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_invoice_id')->constrained('client_invoices')->cascadeOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->dateTime('paid_at');
            $table->string('method')->default('transfer');
            $table->string('proof_path')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->nullable()->constrained('event_phases')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('path');
            $table->string('watermarked_path')->nullable();
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('caption')->nullable();
            $table->dateTime('captured_at');
            $table->decimal('gps_lat', 10, 7);
            $table->decimal('gps_lng', 10, 7);
            $table->decimal('gps_accuracy', 8, 2)->nullable();
            $table->string('address')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('visitor_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('source')->default('walk_in');
            $table->string('interest_model')->nullable();
            $table->string('budget_range')->nullable();
            $table->string('payment_method')->nullable();
            $table->boolean('test_drive')->default(false);
            $table->string('lead_status')->default('new');
            $table->string('sales_pic')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('consent')->default(false);
            $table->string('client_uuid')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('model');
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->string('spk_number')->nullable();
            $table->string('status')->default('spk');
            $table->date('sold_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('event_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('leads')->default(0);
            $table->unsignedInteger('test_drives')->default(0);
            $table->unsignedInteger('spk')->default(0);
            $table->unsignedInteger('units_sold')->default(0);
            $table->decimal('revenue', 18, 2)->default(0);
            $table->unsignedInteger('handover')->default(0);
            $table->decimal('cost_per_lead', 18, 2)->default(0);
            $table->decimal('roi', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('report_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('status')->default('draft');
            $table->string('pdf_path')->nullable();
            $table->string('xlsx_path')->nullable();
            $table->json('snapshot')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'version']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->nullable()->constrained('event_phases')->nullOnDelete();
            $table->string('category')->default('other');
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('path');
            $table->string('checksum', 64)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('type')->default('info');
            $table->dateTime('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('app_notifications');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('report_versions');
        Schema::dropIfExists('event_results');
        Schema::dropIfExists('sales_records');
        Schema::dropIfExists('visitor_entries');
        Schema::dropIfExists('media');
        Schema::dropIfExists('client_payments');
        Schema::dropIfExists('client_invoices');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('time_entries');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_checklists');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_groups');
        Schema::dropIfExists('event_members');
        Schema::dropIfExists('event_divisions');
        Schema::dropIfExists('event_phases');
        Schema::dropIfExists('events');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dealer_id');
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
        Schema::dropIfExists('dealers');
    }
};
