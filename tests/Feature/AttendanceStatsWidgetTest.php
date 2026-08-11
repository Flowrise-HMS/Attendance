<?php

use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Filament\Widgets\AttendanceStatsWidget;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);

    $this->branch = Branch::factory()->create();
    $this->staff = Staff::factory()->create(['branch_id' => $this->branch->id]);
});

it('renders stats for today without errors', function (): void {
    DailyAttendance::factory()->create([
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'work_date' => today(),
        'status' => AttendanceStatus::Present,
    ]);

    Livewire::test(AttendanceStatsWidget::class)->assertOk();
});
