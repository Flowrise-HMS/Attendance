<?php

namespace Modules\Attendance\Filament\RelationManagers\Staff;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource\Tables\DailyAttendanceTable;

class StaffDailyAttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'dailyAttendance';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return DailyAttendanceTable::configure($table);
    }
}
