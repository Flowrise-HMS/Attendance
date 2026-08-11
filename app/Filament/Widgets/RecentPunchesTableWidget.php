<?php

namespace Modules\Attendance\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Modules\Attendance\Models\AttendanceRecord;

class RecentPunchesTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 5;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttendanceRecord::query()
                    ->with(['staff', 'machine'])
                    ->orderByDesc('punched_at')
                    ->limit(20)
            )
            ->columns([
                TextColumn::make('punched_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('staff.full_name')
                    ->label('Staff')
                    ->placeholder('Unmapped'),
                TextColumn::make('badge_number')
                    ->label('Badge'),
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->placeholder('—'),
                TextColumn::make('punch_type')
                    ->badge(),
                TextColumn::make('source')
                    ->badge(),
            ])
            ->poll('10s')
            ->paginated(false);
    }
}
