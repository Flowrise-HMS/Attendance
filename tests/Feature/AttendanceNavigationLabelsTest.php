<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource;
use Tests\TestCase;

class AttendanceNavigationLabelsTest extends TestCase
{
    public function test_resources_use_short_navigation_labels(): void
    {
        $this->assertSame('Shifts', AttendanceShiftResource::getNavigationLabel());
        $this->assertSame('Shift Assignments', AttendanceShiftAssignmentResource::getNavigationLabel());
        $this->assertSame('Daily Attendance', DailyAttendanceResource::getNavigationLabel());
    }
}
