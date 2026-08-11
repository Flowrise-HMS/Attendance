<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_attendance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('work_date');
            $table->foreignUuid('shift_id')->nullable()->constrained('attendance_shifts')->nullOnDelete();
            $table->string('shift_name')->nullable();
            $table->time('expected_start')->nullable();
            $table->time('expected_end')->nullable();
            $table->dateTime('first_in_at')->nullable();
            $table->dateTime('last_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->string('status')->default('absent');
            $table->boolean('is_manual_override')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'work_date']);
            $table->index(['branch_id', 'work_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_attendance');
    }
};
