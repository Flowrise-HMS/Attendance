<?php

namespace Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Schemas;

use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;

class AttendanceShiftAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Assignment')
                ->columns(2)
                ->schema([
                    Select::make('staff_id')
                        ->label('Staff')
                        ->relationship(name: 'staff', modifyQueryUsing: fn (Builder $query) => $query->orderBy('first_name')->orderBy('last_name'))
                        ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->full_name)
                        ->searchable()
                        ->preload()
                        ->required(),
                    Select::make('shift_id')
                        ->label('Shift')
                        ->options(fn (Get $get): array => AttendanceShift::query()
                            ->where('branch_id', Context::get('current_branch_id') ?? Auth::user()?->branch_id)
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Only shifts in the current branch are available.'),
                    DatePicker::make('effective_from')
                        ->label('Effective from')
                        ->required()
                        ->rules(static::overlapRules()),
                    DatePicker::make('effective_to')
                        ->label('Effective to')
                        ->rules(static::overlapRules())
                        ->helperText('Leave empty for an open-ended assignment.'),
                    TextInput::make('note')
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    protected static function overlapRules(): array
    {
        return [
            function (Get $get, Field $component, string $attribute, mixed $value, Closure $fail): void {
                $staffId = $get('staff_id');
                if (! $staffId) {
                    return;
                }

                $from = $get('effective_from');
                $to = $get('effective_to');
                if (! $from) {
                    return;
                }

                $recordId = $component->getRecord()?->id ?? '';

                $exists = AttendanceShiftAssignment::query()
                    ->where('staff_id', $staffId)
                    ->where('id', '!=', $recordId)
                    ->where('effective_from', '<=', $to ?? $from)
                    ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
                    ->exists();

                if ($exists) {
                    $fail('This staff member already has an assignment overlapping these dates.');
                }
            },
        ];
    }
}
