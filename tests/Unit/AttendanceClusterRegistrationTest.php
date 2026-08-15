<?php

use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Clusters\Attendance\Pages\ManageAttendanceSettings;
use Modules\Attendance\Filament\Pages\AttendanceDashboard;
use Modules\Attendance\Filament\Widgets\AttendanceStatsWidget;
use Modules\Attendance\Filament\Widgets\AttendanceTrendChartWidget;
use Modules\Attendance\Filament\Widgets\LateVsOvertimeChartWidget;
use Modules\Attendance\Filament\Widgets\PunchesPerHourChartWidget;
use Modules\Attendance\Filament\Widgets\RecentPunchesTableWidget;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource;
use Tests\TestCase;

uses(TestCase::class);

it('registers all resources under the attendance cluster', function (): void {
    expect(AttendanceMachineResource::getCluster())->toBe(AttendanceCluster::class);
    expect(AttendanceShiftResource::getCluster())->toBe(AttendanceCluster::class);
    expect(AttendanceShiftAssignmentResource::getCluster())->toBe(AttendanceCluster::class);
    expect(AttendanceRecordResource::getCluster())->toBe(AttendanceCluster::class);
    expect(DailyAttendanceResource::getCluster())->toBe(AttendanceCluster::class);
});

it('registers the dashboard and settings pages under the attendance cluster', function (): void {
    expect(AttendanceDashboard::getCluster())->toBe(AttendanceCluster::class);
    expect(ManageAttendanceSettings::getCluster())->toBe(AttendanceCluster::class);
});

it('keeps attendance dashboard widgets off panel autodiscovery', function (string $widget): void {
    expect($widget::isDiscovered())->toBeFalse();
})->with([
    AttendanceStatsWidget::class,
    PunchesPerHourChartWidget::class,
    AttendanceTrendChartWidget::class,
    LateVsOvertimeChartWidget::class,
    RecentPunchesTableWidget::class,
]);
