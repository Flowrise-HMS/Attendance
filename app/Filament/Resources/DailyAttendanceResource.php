<?php

namespace Modules\Attendance\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource\Pages\ListDailyAttendances;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource\Tables\DailyAttendanceTable;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Core\Enums\NavigationGroup;
use Modules\Staff\Models\Staff;

class DailyAttendanceResource extends Resource
{
    protected static ?string $model = DailyAttendance::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::ADMINISTRATION;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(
            ! auth()->user()?->can('ViewAny DailyAttendance'),
            fn (Builder $query) => $query->where('staff_id', Staff::query()->where('user_id', auth()->id())->value('id'))
        );
    }

    public static function table(Table $table): Table
    {
        return DailyAttendanceTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDailyAttendances::route('/'),
        ];
    }
}
