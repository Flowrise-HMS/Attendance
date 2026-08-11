<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Attendance\Database\Factories\AttendanceRecordFactory;
use Modules\Attendance\Enums\PunchSource;
use Modules\Attendance\Enums\PunchType;
use Modules\Attendance\Enums\VerifyType;
use Modules\Core\Models\BaseModel;
use Modules\Staff\Models\Staff;

class AttendanceRecord extends BaseModel
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'branch_id',
        'machine_id',
        'staff_id',
        'source',
        'badge_number',
        'punched_at',
        'punch_type',
        'verify_type',
        'status_code',
        'raw_line',
        'created_by',
        'dedup_hash',
    ];

    protected $casts = [
        'source' => PunchSource::class,
        'punched_at' => 'datetime',
        'punch_type' => PunchType::class,
        'verify_type' => VerifyType::class,
        'status_code' => 'integer',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(AttendanceMachine::class, 'machine_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function scopeUnmapped($query)
    {
        return $query->whereNull('staff_id');
    }

    public static function makeDedupHash(string $machineKey, string $badgeNumber, string $punchedAtApp): string
    {
        return hash('sha256', $machineKey.'|'.$badgeNumber.'|'.$punchedAtApp);
    }
}
