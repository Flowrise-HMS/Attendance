<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftResource\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceShiftTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->recordActions(static::actions())
            ->defaultSort('start_time');
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('start_time')
                ->time('H:i')
                ->sortable(),
            TextColumn::make('end_time')
                ->time('H:i')
                ->sortable(),
            TextColumn::make('is_overnight')
                ->label('Type')
                ->badge()
                ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                ->getStateUsing(fn ($record) => $record->isOvernight() ? 'Overnight' : 'Day'),
            TextColumn::make('break_minutes')
                ->suffix(' min')
                ->toggleable(),
            IconColumn::make('is_active')
                ->boolean(),
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
