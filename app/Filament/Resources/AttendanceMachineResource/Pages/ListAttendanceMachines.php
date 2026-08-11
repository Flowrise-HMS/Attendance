<?php

namespace Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource;

class ListAttendanceMachines extends ListRecords
{
    protected static string $resource = AttendanceMachineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
