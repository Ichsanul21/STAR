<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_versions', function (Blueprint $table) {
            $table->string('report_number', 80)->nullable()->unique()->after('version');
        });
    }

    public function down(): void
    {
        Schema::table('report_versions', function (Blueprint $table) {
            $table->dropUnique(['report_number']);
            $table->dropColumn('report_number');
        });
    }
};
