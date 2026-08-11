<?php

namespace Modules\Attendance\Filament\Resources\DailyAttendanceResource\Pages;

use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource;

class ListDailyAttendances extends ListRecords
{
    protected static string $resource = DailyAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->visible(fn () => Auth::user()?->can('export_daily_attendance')),
        ];
    }
}
