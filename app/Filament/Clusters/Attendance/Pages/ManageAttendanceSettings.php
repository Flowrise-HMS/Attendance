<?php

namespace Modules\Attendance\Filament\Clusters\Attendance\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Attendance\Filament\Clusters\Attendance\AttendanceCluster;
use Modules\Attendance\Settings\AttendanceSettings;
use Modules\Core\Enums\NavigationGroup;

class ManageAttendanceSettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $cluster = AttendanceCluster::class;

    protected static string $settings = AttendanceSettings::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::SETTINGS;

    protected static ?string $navigationLabel = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Defaults')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_late_grace_minutes')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('default_overtime_after_minutes')
                            ->numeric()
                            ->minValue(0),
                        TimePicker::make('default_start_time')
                            ->seconds(false)
                            ->formatStateUsing(fn (?string $state): ?string => $state !== null ? substr($state, 0, 5) : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? $state.':00' : null)
                            ->placeholder('08:00'),
                        TimePicker::make('default_end_time')
                            ->seconds(false)
                            ->formatStateUsing(fn (?string $state): ?string => $state !== null ? substr($state, 0, 5) : null)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state !== null ? $state.':00' : null)
                            ->placeholder('17:00'),
                        CheckboxList::make('weekend_days')
                            ->options(['saturday' => 'Saturday', 'sunday' => 'Sunday', 'friday' => 'Friday', 'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday'])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Devices')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pull_interval_minutes')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('min'),
                        Toggle::make('push_enabled'),
                    ]),
            ]);
    }
}
