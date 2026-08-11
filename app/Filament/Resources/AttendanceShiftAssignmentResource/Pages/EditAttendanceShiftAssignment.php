<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource;

class EditAttendanceShiftAssignment extends EditRecord
{
    protected static string $resource = AttendanceShiftAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
