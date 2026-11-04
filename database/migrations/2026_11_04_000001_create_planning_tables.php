<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->date('date');
            $table->string('start_time', 8)->nullable();
            $table->string('end_time', 8)->nullable();
            $table->string('activity');
            $table->text('description')->nullable();
            $table->string('pic')->nullable();
            $table->string('location')->nullable();
            $table->string('category')->default('activity');
            $table->unsignedInteger('target_participants')->default(0);
            $table->unsignedInteger('actual_participants')->default(0);
            $table->string('status')->default('planned');
            $table->string('actual_start', 8)->nullable();
            $table->string('actual_end', 8)->nullable();
            $table->integer('variance_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('planning_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('aspect');
            $table->text('description');
            $table->string('target')->nullable();
            $table->string('pic')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planning_items');
        Schema::dropIfExists('schedule_items');
    }
};
