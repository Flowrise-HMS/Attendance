<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRecordResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Staff\Models\Staff;

class AttendanceRecordTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(static::columns())
            ->filters(static::filters())
            ->recordActions(static::actions())
            ->defaultSort('punched_at', 'desc');
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('punched_at')
                ->dateTime()
                ->sortable()
                ->weight('bold'),
            TextColumn::make('staff.full_name')
                ->label('Staff')
                ->placeholder('Unmapped'),
            TextColumn::make('badge_number')
                ->label('Badge')
                ->searchable(),
            TextColumn::make('machine.name')
                ->label('Machine')
                ->placeholder('—'),
            TextColumn::make('punch_type')
                ->label('Type')
                ->badge()
                ->color(fn ($state) => $state->getColor()),
            TextColumn::make('source')
                ->badge(),
            TextColumn::make('verify_type')
                ->badge()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status_code')
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function filters(): array
    {
        return [
            Filter::make('punched_at')
                ->label('Punch date range')
                ->schema([
                    DatePicker::make('punched_from'),
                    DatePicker::make('punched_until'),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['punched_from'], fn (Builder $q, $date) => $q->whereDate('punched_at', '>=', $date))
                        ->when($data['punched_until'], fn (Builder $q, $date) => $q->whereDate('punched_at', '<=', $date));
                }),
            SelectFilter::make('machine_id')
                ->label('Machine')
                ->relationship('machine', 'name')
                ->preload(),
            SelectFilter::make('staff_id')
                ->label('Staff')
                ->options(fn (): array => Staff::query()->get()->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->display_name])->all())
                ->searchable(),
            SelectFilter::make('source')
                ->options(PunchSource::class),
            SelectFilter::make('punch_type')
                ->options(PunchType::class),
            Filter::make('unmapped')
                ->label('Unmapped only')
                ->query(fn (Builder $query) => $query->whereNull('staff_id')),
        ];
    }

    public static function actions(): array
    {
        return [
            ActionGroup::make([
                EditAction::make(),
                Action::make('linkStaff')
                    ->label('Link staff')
                    ->icon('heroicon-m-user-plus')
                    ->color('success')
                    ->visible(fn (AttendanceRecord $record) => $record->staff_id === null)
                    ->schema([
                        Select::make('staff_id')
                            ->label('Staff')
                            ->options(fn (): array => Staff::query()->get()->mapWithKeys(fn (Staff $staff) => [$staff->id => $staff->display_name])->all())
                            ->searchable()
                            ->required(),
                        Toggle::make('set_zk_user_id')
                            ->label('Also write this badge as the staff device badge (zk_user_id)')
                            ->default(false),
                    ])
                    ->action(function (AttendanceRecord $record, array $data): void {
                        $updated = AttendanceRecord::query()
                            ->whereNull('staff_id')
                            ->where('badge_number', $record->badge_number)
                            ->where('branch_id', $record->branch_id)
                            ->update(['staff_id' => $data['staff_id']]);

                        if (! empty($data['set_zk_user_id'])) {
                            Staff::query()->whereKey($data['staff_id'])->update(['zk_user_id' => $record->badge_number]);
                        }

                        Notification::make()
                            ->title("Linked {$updated} record(s)")
                            ->success()
                            ->send();
                    }),
                DeleteAction::make(),
            ]),
        ];
    }
}
