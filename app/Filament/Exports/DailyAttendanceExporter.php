<?php

namespace Modules\Attendance\Filament\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Modules\Attendance\Models\DailyAttendance;

class DailyAttendanceExporter extends Exporter
{
    protected static ?string $model = DailyAttendance::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('staff.staff_number'),
            ExportColumn::make('staff.first_name'),
            ExportColumn::make('staff.last_name'),
            ExportColumn::make('work_date'),
            ExportColumn::make('shift_name'),
            ExportColumn::make('expected_start'),
            ExportColumn::make('expected_end'),
            ExportColumn::make('first_in_at'),
            ExportColumn::make('last_out_at'),
            ExportColumn::make('worked_minutes'),
            ExportColumn::make('late_minutes'),
            ExportColumn::make('overtime_minutes'),
            ExportColumn::make('status'),
            ExportColumn::make('is_manual_override'),
            ExportColumn::make('notes'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your daily attendance export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
