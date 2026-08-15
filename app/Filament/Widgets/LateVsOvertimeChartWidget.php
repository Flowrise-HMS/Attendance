<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Widgets\BarChartWidget;
use Modules\Attendance\Models\DailyAttendance;

class LateVsOvertimeChartWidget extends BarChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Late vs overtime (14 days)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $dates = collect(range(13, 0))->map(fn (int $days) => today()->subDays($days)->toDateString());

        $rows = DailyAttendance::query()
            ->whereDate('work_date', '>=', today()->subDays(13))
            ->get()
            ->groupBy(fn (DailyAttendance $row) => $row->work_date->toDateString());

        $sum = fn (string $column): array => $dates
            ->map(fn (string $date) => $rows->get($date)?->sum($column) ?? 0)
            ->all();

        return [
            'datasets' => [
                ['label' => 'Late (min)', 'data' => $sum('late_minutes'), 'backgroundColor' => '#f59e0b'],
                ['label' => 'Overtime (min)', 'data' => $sum('overtime_minutes'), 'backgroundColor' => '#3b82f6'],
            ],
            'labels' => $dates->all(),
        ];
    }
}
