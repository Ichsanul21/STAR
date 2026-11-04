<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['events', 'tasks', 'task_checklists', 'budget_versions', 'budget_items', 'expenses', 'report_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedInteger('lock_version')->default(1)->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (['events', 'tasks', 'task_checklists', 'budget_versions', 'budget_items', 'expenses', 'report_versions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('lock_version');
            });
        }
    }
};
