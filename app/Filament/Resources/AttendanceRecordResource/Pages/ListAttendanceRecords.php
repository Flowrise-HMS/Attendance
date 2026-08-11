<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRecordResource\Pages;

use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Filament\Imports\AttendanceRecordImporter;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource;

class ListAttendanceRecords extends ListRecords
{
    protected static string $resource = AttendanceRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(AttendanceRecordImporter::class)
                ->visible(fn () => Auth::user()?->can('import_attendance_records')),
        ];
    }
}
