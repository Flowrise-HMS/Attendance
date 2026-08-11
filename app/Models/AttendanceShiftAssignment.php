<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Attendance\Database\Factories\AttendanceShiftAssignmentFactory;
use Modules\Staff\Models\Staff;

class AttendanceShiftAssignment extends Model
{
    /** @use HasFactory<AttendanceShiftAssignmentFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'staff_id',
        'shift_id',
        'effective_from',
        'effective_to',
        'note',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(AttendanceShift::class);
    }

    public function overlaps(): bool
    {
        return self::query()
            ->where('staff_id', $this->staff_id)
            ->whereKeyNot($this->exists ? $this->getKey() : null)
            ->where('effective_from', '<=', $this->effective_to ?? $this->effective_from->copy()->addYears(100))
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $this->effective_from);
            })
            ->exists();
    }
}
