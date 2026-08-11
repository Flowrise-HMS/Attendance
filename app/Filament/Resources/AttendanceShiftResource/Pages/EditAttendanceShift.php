<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource;

class EditAttendanceShift extends EditRecord
{
    protected static string $resource = AttendanceShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
