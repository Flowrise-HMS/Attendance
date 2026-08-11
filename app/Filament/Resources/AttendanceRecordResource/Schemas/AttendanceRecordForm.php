<?php

namespace Modules\Attendance\Filament\Resources\AttendanceRecordResource\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;

class AttendanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Punch')
                ->columns(2)
                ->schema([
                    DateTimePicker::make('punched_at')
                        ->label('Punched at')
                        ->required(),
                    TextInput::make('badge_number')
                        ->required()
                        ->maxLength(255),
                    Select::make('machine_id')
                        ->label('Machine')
                        ->relationship('machine', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Select::make('staff_id')
                        ->label('Staff')
                        ->relationship('staff', 'full_name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Select::make('punch_type')
                        ->label('Punch type')
                        ->options(PunchType::class)
                        ->required(),
                    Select::make('verify_type')
                        ->label('Verify type')
                        ->options(VerifyType::class)
                        ->nullable(),
                    TextInput::make('status_code')
                        ->numeric()
                        ->nullable(),
                    Select::make('source')
                        ->options(PunchSource::class)
                        ->required(),
                ]),
        ]);
    }
}
