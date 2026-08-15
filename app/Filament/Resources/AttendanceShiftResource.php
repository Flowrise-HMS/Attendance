<?php

namespace Modules\Attendance\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\CreateAttendanceShift;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\EditAttendanceShift;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Pages\ListAttendanceShifts;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Schemas\AttendanceShiftForm;
use Modules\Attendance\Filament\Resources\AttendanceShiftResource\Tables\AttendanceShiftTable;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Core\Enums\NavigationGroup;

class AttendanceShiftResource extends Resource
{
    protected static ?string $model = AttendanceShift::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return AttendanceShiftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceShiftTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceShifts::route('/'),
            'create' => CreateAttendanceShift::route('/create'),
            'edit' => EditAttendanceShift::route('/{record}/edit'),
        ];
    }
}
