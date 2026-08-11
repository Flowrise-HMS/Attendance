<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceShiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Shift')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Morning'),
                    TimePicker::make('start_time')
                        ->label('Start time')
                        ->seconds(false)
                        ->required(),
                    TimePicker::make('end_time')
                        ->label('End time')
                        ->seconds(false)
                        ->required()
                        ->helperText('An end time earlier than the start time marks an overnight shift.'),
                    TextInput::make('late_grace_minutes')
                        ->numeric()
                        ->default(15)
                        ->minValue(0)
                        ->suffix('min'),
                    TextInput::make('overtime_after_minutes')
                        ->numeric()
                        ->default(60)
                        ->minValue(0)
                        ->suffix('min'),
                    TextInput::make('break_minutes')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('min')
                        ->helperText('Deducted from worked time when the schedule is 6 hours or longer.'),
                    TextInput::make('color')
                        ->maxLength(20)
                        ->placeholder('e.g. #3b82f6'),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
        ]);
    }
}
