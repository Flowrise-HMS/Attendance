<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('machine_id')->nullable()->constrained('attendance_machines')->nullOnDelete();
            $table->foreignUuid('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->string('source');
            $table->string('badge_number');
            $table->dateTime('punched_at');
            $table->string('punch_type')->default('unspecified');
            $table->string('verify_type')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('raw_line')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->char('dedup_hash', 64)->unique();
            $table->timestamps();

            $table->index(['branch_id']);
            $table->index(['staff_id', 'punched_at']);
            $table->index(['machine_id', 'punched_at']);
            $table->index(['punched_at']);
            $table->index(['source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
