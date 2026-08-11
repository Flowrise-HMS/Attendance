<?php

namespace Modules\Attendance\Filament\Resources\DailyAttendanceResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Staff\Models\Staff;

class DailyAttendanceTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::actions())
            ->defaultSort('work_date', 'desc');
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('work_date')
                ->date()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('staff.full_name')
                ->label('Staff')
                ->searchable(),
            TextColumn::make('status')
                ->badge()
                ->color(fn (AttendanceStatus $state): string => $state->getColor()),
            TextColumn::make('first_in_at')
                ->time('H:i')
                ->placeholder('—'),
            TextColumn::make('last_out_at')
                ->time('H:i')
                ->placeholder('—'),
            TextColumn::make('worked_minutes')
                ->formatStateUsing(fn (int $state): string => sprintf('%dh %02dm', intdiv($state, 60), $state % 60))
                ->label('Worked'),
            TextColumn::make('late_minutes')
                ->label('Late')
                ->suffix('m')
                ->toggleable(),
            TextColumn::make('overtime_minutes')
                ->label('OT')
                ->suffix('m')
                ->toggleable(),
            IconColumn::make('is_manual_override')
                ->boolean()
                ->label('Override')
                ->trueIcon('heroicon-m-hand-raised')
                ->falseIcon('heroicon-o-hand-raised'),
        ];
    }

    public static function filters(): array
    {
        return [
            Filter::make('work_date')
                ->label('Work date range')
                ->schema([
                    DatePicker::make('work_from'),
                    DatePicker::make('work_until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['work_from'], fn (Builder $q, $date) => $q->whereDate('work_date', '>=', $date))
                        ->when($data['work_until'], fn (Builder $q, $date) => $q->whereDate('work_date', '<=', $date));
                }),
            SelectFilter::make('status')
                ->options(AttendanceStatus::class),
            SelectFilter::make('staff_id')
                ->label('Staff')
                ->options(fn (): array => Staff::query()->get()->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->display_name])->all())
                ->searchable(),
        ];
    }

    public static function actions(): array
    {
        return [
            ActionGroup::make([
                Action::make('overrideStatus')
                    ->label('Override status')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->visible(fn () => Auth::user()?->can('override_daily_attendance_status'))
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(AttendanceStatus::class)
                            ->required(),
                        TextInput::make('notes')
                            ->label('Notes')
                            ->maxLength(500),
                    ])
                    ->action(function (DailyAttendance $record, array $data): void {
                        $record->update([
                            'status' => $data['status'],
                            'is_manual_override' => true,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        Notification::make()
                            ->title('Status overridden')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ]),
        ];
    }
}
