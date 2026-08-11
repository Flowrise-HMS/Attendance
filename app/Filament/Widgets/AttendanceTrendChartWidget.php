<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Widgets\LineChartWidget;
use Illuminate\Support\Collection;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\DailyAttendance;

class AttendanceTrendChartWidget extends LineChartWidget
{
    protected ?string $heading = 'Daily attendance trend (14 days)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $dates = $this->dateSeries();

        $rows = DailyAttendance::query()
            ->whereDate('work_date', '>=', today()->subDays(13))
            ->get()
            ->groupBy(fn (DailyAttendance $row) => $row->work_date->toDateString());

        $series = fn (AttendanceStatus $status): array => $dates
            ->map(fn (string $date) => $rows->get($date)?->where('status', $status)->count() ?? 0)
            ->all();

        return [
            'datasets' => [
                ['label' => 'Present', 'data' => $series(AttendanceStatus::Present), 'borderColor' => '#22c55e'],
                ['label' => 'Late', 'data' => $series(AttendanceStatus::Late), 'borderColor' => '#f59e0b'],
                ['label' => 'Absent', 'data' => $series(AttendanceStatus::Absent), 'borderColor' => '#ef4444'],
            ],
            'labels' => $dates->all(),
        ];
    }

    protected function dateSeries(): Collection
    {
        return collect(range(13, 0))->map(fn (int $days) => today()->subDays($days)->toDateString());
    }
}
