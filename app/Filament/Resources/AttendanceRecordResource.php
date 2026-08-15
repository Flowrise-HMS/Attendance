<?php

namespace Modules\Attendance\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Pages\EditAttendanceRecord;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Pages\ListAttendanceRecords;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Schemas\AttendanceRecordForm;
use Modules\Attendance\Filament\Resources\AttendanceRecordResource\Tables\AttendanceRecordTable;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Core\Enums\NavigationGroup;
use Modules\Staff\Models\Staff;

class AttendanceRecordResource extends Resource
{
    protected static ?string $model = AttendanceRecord::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(
            ! auth()->user()?->can('ViewAny AttendanceRecord'),
            fn (Builder $query) => $query->where('staff_id', Staff::query()->where('user_id', auth()->id())->value('id'))
        );
    }

    public static function form(Schema $schema): Schema
    {
        return AttendanceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendanceRecordTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceRecords::route('/'),
            'edit' => EditAttendanceRecord::route('/{record}/edit'),
        ];
    }
}
