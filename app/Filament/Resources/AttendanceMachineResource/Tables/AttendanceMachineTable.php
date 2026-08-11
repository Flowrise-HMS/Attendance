<?php

namespace Modules\Attendance\Filament\Resources\AttendanceMachineResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Attendance\Classes\Services\AttendancePullService;
use Modules\Attendance\Jobs\PullAttendanceJob;
use Modules\Attendance\Models\AttendanceMachine;

class AttendanceMachineTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->recordActions(static::actions())
            ->defaultSort('name');
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('serial_number')
                ->searchable()
                ->copyable(),
            TextColumn::make('ip_address')
                ->label('IP Address'),
            TextColumn::make('status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'online' => 'success',
                    'offline' => 'danger',
                    default => 'gray',
                }),
            TextColumn::make('last_seen_at')
                ->label('Last seen')
                ->since()
                ->sortable()
                ->placeholder('Never'),
            IconColumn::make('push_enabled')
                ->boolean()
                ->label('PUSH'),
            IconColumn::make('pull_enabled')
                ->boolean()
                ->label('PULL'),
        ];
    }

    public static function actions(): array
    {
        return [
            ActionGroup::make([
                Action::make('syncNow')
                    ->label('Sync now')
                    ->icon('heroicon-m-arrow-path')
                    ->requiresConfirmation()
                    ->action(fn (AttendanceMachine $record) => PullAttendanceJob::dispatch($record->id))
                    ->successNotificationTitle('Sync started'),
                Action::make('testConnection')
                    ->label('Test connection')
                    ->icon('heroicon-m-wifi')
                    ->action(function (AttendanceMachine $record): void {
                        $ok = app(AttendancePullService::class)->testConnection($record);
                        Notification::make()
                            ->title($ok ? 'Connection successful' : 'Connection failed')
                            ->success($ok)
                            ->danger(! $ok)
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
