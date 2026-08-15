<?php

namespace Modules\Attendance\Filament\Pages;

use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Widgets\AttendanceStatsWidget;
use Modules\Attendance\Filament\Widgets\AttendanceTrendChartWidget;
use Modules\Attendance\Filament\Widgets\LateVsOvertimeChartWidget;
use Modules\Attendance\Filament\Widgets\PunchesPerHourChartWidget;
use Modules\Attendance\Filament\Widgets\RecentPunchesTableWidget;
use Modules\Core\Enums\NavigationGroup;

class AttendanceDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Attendance Dashboard';

    protected static ?string $slug = 'attendance-dashboard';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_attendance_dashboard') ?? false;
    }

    public function getWidgets(): array
    {
        return [
            AttendanceStatsWidget::class,
            PunchesPerHourChartWidget::class,
            AttendanceTrendChartWidget::class,
            LateVsOvertimeChartWidget::class,
            RecentPunchesTableWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return 'Attendance Dashboard';
    }
}
