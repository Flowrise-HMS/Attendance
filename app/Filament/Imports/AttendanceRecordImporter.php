<?php

namespace Modules\Attendance\Filament\Imports;

use Carbon\Carbon;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Number;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Staff\Models\Staff;

class AttendanceRecordImporter extends Importer
{
    protected static ?string $model = AttendanceRecord::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('badge_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('punched_at')
                ->requiredMapping()
                ->fillRecordUsing(fn () => null)
                ->rules(['required', 'date']),
            ImportColumn::make('verify_type')
                ->fillRecordUsing(fn () => null)
                ->rules(['nullable', 'max:50']),
            ImportColumn::make('status_code')
                ->fillRecordUsing(fn () => null)
                ->rules(['nullable', 'max:20']),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Select::make('machine_id')
                ->label('Device')
                ->placeholder('Unassigned (no device)')
                ->options(fn (): array => AttendanceMachine::query()->pluck('name', 'id')->all())
                ->searchable()
                ->helperText('Re-importing the same pen-drive file must use the SAME device as the original import.'),
        ];
    }

    public function resolveRecord(): ?AttendanceRecord
    {
        $machine = $this->options['machine_id'] ?? null
            ? AttendanceMachine::query()->find($this->options['machine_id'])
            : null;

        $currentBranch = Context::get('current_branch_id') ?? auth()->user()?->branch_id;

        if ($machine && $machine->branch_id !== $currentBranch) {
            throw new RowImportFailedException('The selected device does not belong to the current branch.');
        }

        $badge = trim((string) ($this->data['badge_number'] ?? ''));
        $statusCode = $this->data['status_code'] ?? null;
        $statusCodeInt = is_numeric($statusCode) ? (int) $statusCode : null;
        $punchType = PunchType::fromStatus($statusCodeInt ?? -1);
        $verifyCode = $this->data['verify_type'] ?? null;
        $verifyType = is_numeric($verifyCode) ? VerifyType::fromCode((int) $verifyCode) : ($verifyCode !== null && $verifyCode !== '' ? VerifyType::Other : null);

        $punchedAt = $this->parsePunchedAt($this->data['punched_at'], $machine);

        // Punch type is derived from Status in the mapped CSV; keep it consistent for storage.
        $this->data['punched_at'] = $punchedAt->format('Y-m-d H:i:s');

        $machineKey = $machine?->id ?? 'unassigned';
        $hash = AttendanceRecord::makeDedupHash($machineKey, $badge, $punchedAt->format('Y-m-d H:i:s'));

        if (AttendanceRecord::query()->where('dedup_hash', $hash)->exists()) {
            return null; // skip silently — original record untouched
        }

        $staff = Staff::query()->where('zk_user_id', $badge)->orWhere('staff_number', $badge)->first();

        $record = new AttendanceRecord;
        $record->forceFill([
            'branch_id' => $currentBranch,
            'machine_id' => $machine?->id,
            'staff_id' => $staff?->id,
            'badge_number' => $badge,
            'punched_at' => $punchedAt,
            'source' => PunchSource::Import,
            'punch_type' => $punchType,
            'verify_type' => $verifyType,
            'status_code' => $statusCodeInt,
            'created_by' => auth()->id(),
            'dedup_hash' => $hash,
        ]);

        return $record;
    }

    protected function parsePunchedAt(mixed $value, ?AttendanceMachine $machine): Carbon
    {
        $timezone = $machine?->timezone ?: config('app.timezone');
        $carbon = Carbon::parse($value, $timezone);

        if ($machine) {
            $carbon = $carbon->setTimezone(config('app.timezone'));
        }

        return $carbon;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your attendance import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
