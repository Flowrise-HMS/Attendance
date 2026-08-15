<?php

namespace Modules\Attendance\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\CreateAttendanceShiftAssignment;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\EditAttendanceShiftAssignment;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\ListAttendanceShiftAssignments;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Schemas\AttendanceShiftAssignmentForm;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Tables\AttendanceShiftAssignmentTable;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Core\Enums\NavigationGroup;

class AttendanceShiftAssignmentResource extends Resource
{
    protected static ?string $model = AttendanceShiftAssignment::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return AttendanceShiftAssignmentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceShiftAssignmentTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceShiftAssignments::route('/'),
            'create' => CreateAttendanceShiftAssignment::route('/create'),
            'edit' => EditAttendanceShiftAssignment::route('/{record}/edit'),
        ];
    }
}
