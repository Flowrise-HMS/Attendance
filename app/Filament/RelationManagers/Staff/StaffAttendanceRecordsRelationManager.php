<?php

namespace Modules\Attendance\Filament\RelationManagers\Staff;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Tables\AttendanceRecordTable;

class StaffAttendanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceRecords';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return AttendanceRecordTable::configure($table);
    }
}
