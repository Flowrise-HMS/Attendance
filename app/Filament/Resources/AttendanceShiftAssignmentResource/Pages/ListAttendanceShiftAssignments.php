<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource;

class ListAttendanceShiftAssignments extends ListRecords
{
    protected static string $resource = AttendanceShiftAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
