<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conflict_reports', function (Blueprint $table) {
            $table->json('base_snapshot')->nullable()->after('actual_version');
            $table->json('merge_result')->nullable()->after('snapshot');
            $table->timestamp('merged_at')->nullable()->after('resolved_at');
            $table->foreignId('merged_by')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conflict_reports', function (Blueprint $table) {
            $table->dropForeign(['merged_by']);
            $table->dropColumn(['base_snapshot', 'merge_result', 'merged_at', 'merged_by']);
        });
    }
};
