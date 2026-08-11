<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_shift_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignUuid('shift_id')->constrained('attendance_shifts')->cascadeOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['staff_id', 'effective_from', 'effective_to'], 'asn_staff_dates_index');
            $table->index(['shift_id'], 'asn_shift_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_shift_assignments');
    }
};
