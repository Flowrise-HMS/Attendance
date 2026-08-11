<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\DailyAttendanceFactory;
use Modules\Attendance\Enums\AttendanceStatus;
use Modules\Core\Models\BaseModel;
use Modules\Staff\Models\Staff;

class DailyAttendance extends BaseModel
{
    /** @use HasFactory<DailyAttendanceFactory> */
    use HasFactory, HasUuids;

    protected $table = 'daily_attendance';

    protected $fillable = [
        'branch_id',
        'staff_id',
        'work_date',
        'shift_id',
        'shift_name',
        'expected_start',
        'expected_end',
        'first_in_at',
        'last_out_at',
        'worked_minutes',
        'late_minutes',
        'overtime_minutes',
        'status',
        'is_manual_override',
        'notes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'expected_start' => 'datetime:H:i:s',
        'expected_end' => 'datetime:H:i:s',
        'first_in_at' => 'datetime',
        'last_out_at' => 'datetime',
        'worked_minutes' => 'integer',
        'late_minutes' => 'integer',
        'overtime_minutes' => 'integer',
        'status' => AttendanceStatus::class,
        'is_manual_override' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class, 'shift_id');
    }
}
