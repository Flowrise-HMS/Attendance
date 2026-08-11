<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Attendance\Database\Factories\AttendanceShiftFactory;
use Modules\Core\Models\BaseModel;

class AttendanceShift extends BaseModel
{
    /** @use HasFactory<AttendanceShiftFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'start_time',
        'end_time',
        'late_grace_minutes',
        'overtime_after_minutes',
        'break_minutes',
        'color',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'late_grace_minutes' => 'integer',
        'overtime_after_minutes' => 'integer',
        'break_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected array $logAttributes = [
        'name',
        'start_time',
        'end_time',
        'late_grace_minutes',
        'overtime_after_minutes',
        'break_minutes',
        'color',
        'is_active',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(AttendanceShiftAssignment::class, 'shift_id');
    }

    public function isOvernight(): bool
    {
        return $this->end_time->lt($this->start_time);
    }
}
