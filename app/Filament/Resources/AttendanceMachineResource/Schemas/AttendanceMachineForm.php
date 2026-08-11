<?php

namespace Modules\Attendance\Filament\Resources\AttendanceMachineResource\Schemas;

use DateTimeZone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceMachineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Device')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('Main Gate Terminal'),
                    TextInput::make('serial_number')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('e.g. 1800123456789'),
                    TextInput::make('ip_address')
                        ->label('IP Address')
                        ->required()
                        ->maxLength(45)
                        ->placeholder('192.168.1.100'),
                    TextInput::make('port')
                        ->numeric()
                        ->required()
                        ->default(4370)
                        ->minValue(1)
                        ->maxValue(65535),
                    TextInput::make('comm_key')
                        ->numeric()
                        ->default(0)
                        ->helperText('Device communication key (0 if not configured).'),
                    TextInput::make('model')
                        ->maxLength(100)
                        ->placeholder('e.g. UFace 800'),
                    Select::make('timezone')
                        ->options(fn (): array => array_combine(DateTimeZone::listIdentifiers(), DateTimeZone::listIdentifiers()))
                        ->default(config('app.timezone'))
                        ->searchable()
                        ->required(),
                    Toggle::make('push_enabled')
                        ->label('Accept device push (PUSH)')
                        ->default(true),
                    Toggle::make('pull_enabled')
                        ->label('Poll device (PULL)'),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
        ]);
    }
}
