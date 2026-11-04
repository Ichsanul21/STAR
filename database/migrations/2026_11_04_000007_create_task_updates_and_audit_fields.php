<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('revision_of_id')->nullable()->after('task_group_id')->constrained('tasks')->nullOnDelete();
            $table->boolean('is_revision')->default(false)->after('revision_of_id');
            $table->text('revision_reason')->nullable()->after('is_revision');
        });

        Schema::create('task_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignId('event_phase_id')->nullable()->constrained('event_phases')->nullOnDelete();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('task_checklist_id')->nullable()->constrained('task_checklists')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('status');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note');
            $table->string('photo_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->json('old_values')->nullable()->after('description');
            $table->json('new_values')->nullable()->after('old_values');
            $table->string('ip_address', 45)->nullable()->after('meta');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('device_type', 40)->nullable()->after('user_agent');
            $table->string('browser', 80)->nullable()->after('device_type');
            $table->string('platform', 80)->nullable()->after('browser');
            $table->string('route', 255)->nullable()->after('platform');
            $table->string('http_method', 10)->nullable()->after('route');
            $table->unsignedInteger('status_code')->nullable()->after('http_method');
            $table->unsignedInteger('duration_ms')->nullable()->after('status_code');
            $table->string('request_id', 64)->nullable()->after('duration_ms');
            $table->string('session_id')->nullable()->after('request_id');
            $table->string('severity', 20)->default('info')->after('session_id');
            $table->boolean('is_system')->default(false)->after('severity');
            $table->index(['created_at', 'action']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at', 'action']);
            $table->dropColumn([
                'old_values', 'new_values', 'ip_address', 'user_agent', 'device_type',
                'browser', 'platform', 'route', 'http_method', 'status_code', 'duration_ms',
                'request_id', 'session_id', 'severity', 'is_system',
            ]);
        });

        Schema::dropIfExists('task_updates');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revision_of_id');
            $table->dropColumn(['is_revision', 'revision_reason']);
        });
    }
};
