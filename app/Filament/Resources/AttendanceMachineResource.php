<?php

namespace Modules\Attendance\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\CreateAttendanceMachine;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\EditAttendanceMachine;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Pages\ListAttendanceMachines;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Schemas\AttendanceMachineForm;
use Modules\Attendance\Filament\Resources\AttendanceMachineResource\Tables\AttendanceMachineTable;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Core\Enums\NavigationGroup;

class AttendanceMachineResource extends Resource
{
    protected static ?string $model = AttendanceMachine::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return AttendanceMachineForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceMachineTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceMachines::route('/'),
            'create' => CreateAttendanceMachine::route('/create'),
            'edit' => EditAttendanceMachine::route('/{record}/edit'),
        ];
    }
}
