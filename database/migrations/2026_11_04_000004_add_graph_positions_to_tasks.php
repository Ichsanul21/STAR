<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->decimal('graph_x', 10, 2)->nullable()->after('sort_order');
            $table->decimal('graph_y', 10, 2)->nullable()->after('graph_x');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['graph_x', 'graph_y']);
        });
    }
};
