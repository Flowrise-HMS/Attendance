<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource;

class ListAttendanceShifts extends ListRecords
{
    protected static string $resource = AttendanceShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
