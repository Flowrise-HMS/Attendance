<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceShiftAssignmentTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->recordActions(static::actions())
            ->defaultSort('effective_from', 'desc');
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('staff.full_name')
                ->label('Staff')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('shift.name')
                ->label('Shift')
                ->searchable(),
            TextColumn::make('effective_from')
                ->date()
                ->sortable(),
            TextColumn::make('effective_to')
                ->date()
                ->sortable()
                ->placeholder('Current'),
            TextColumn::make('note')
                ->limit(40)
                ->toggleable(),
        ];
    }

    public static function actions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
