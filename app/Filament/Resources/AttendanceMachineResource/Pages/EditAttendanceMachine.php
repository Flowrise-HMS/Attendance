<?php

namespace Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource;

class EditAttendanceMachine extends EditRecord
{
    protected static string $resource = AttendanceMachineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
