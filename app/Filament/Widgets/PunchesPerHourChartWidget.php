<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Widgets\BarChartWidget;
use Modules\Attendance\Models\AttendanceRecord;

class PunchesPerHourChartWidget extends BarChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Punches per hour (today)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $counts = AttendanceRecord::query()
            ->whereDate('punched_at', today())
            ->get()
            ->groupBy(fn ($record) => (int) $record->punched_at->format('H'))
            ->map->count();

        $hours = range(0, 23);

        return [
            'datasets' => [[
                'label' => 'Punches',
                'data' => array_map(fn ($h) => $counts->get($h, 0), $hours),
            ]],
            'labels' => array_map(fn ($h) => str_pad((string) $h, 2, '0', STR_PAD_LEFT), $hours),
        ];
    }
}
