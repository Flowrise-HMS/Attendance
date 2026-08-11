<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\DailyAttendance;

class AttendanceStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $counts = DailyAttendance::query()
            ->whereDate('work_date', today())
            ->get()
            ->groupBy('status')
            ->map->count();

        $onShift = AttendanceRecord::query()
            ->whereDate('punched_at', today())
            ->whereHas('staff')
            ->get()
            ->groupBy('staff_id')
            ->filter(fn ($group) => $group->whereIn('punch_type', [PunchType::In, PunchType::Unspecified])->isNotEmpty()
                && $group->whereIn('punch_type', [PunchType::Out, PunchType::Unspecified])->isEmpty())
            ->count();

        return [
            Stat::make('Present today', $counts->get(AttendanceStatus::Present->value, 0))
                ->description('Completed in/out pair today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Late today', $counts->get(AttendanceStatus::Late->value, 0))
                ->description('Arrived after grace period')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Absent today', $counts->get(AttendanceStatus::Absent->value, 0))
                ->description('No punches recorded')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
            Stat::make('On shift', $onShift)
                ->description('Checked in, not yet out')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color('info'),
        ];
    }
}
