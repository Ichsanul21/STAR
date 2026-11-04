<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['dev_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_sessions');
    }
};
