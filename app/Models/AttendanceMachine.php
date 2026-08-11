<?php

namespace Modules\Attendance\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Attendance\Database\Factories\AttendanceMachineFactory;
use Modules\Core\Models\BaseModel;

class AttendanceMachine extends BaseModel
{
    /** @use HasFactory<AttendanceMachineFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'serial_number',
        'ip_address',
        'port',
        'comm_key',
        'model',
        'timezone',
        'push_enabled',
        'pull_enabled',
        'is_active',
        'last_seen_at',
        'last_sync_at',
        'metadata',
    ];

    protected $casts = [
        'port' => 'integer',
        'comm_key' => 'integer',
        'push_enabled' => 'boolean',
        'pull_enabled' => 'boolean',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected array $logAttributes = [
        'name',
        'serial_number',
        'ip_address',
        'port',
        'comm_key',
        'model',
        'timezone',
        'push_enabled',
        'pull_enabled',
        'is_active',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'machine_id');
    }

    public function getStatusAttribute(): string
    {
        // Hardcoded 30-minute online window — deliberately avoids a DB round-trip in the
        // accessor (app(AttendanceSettings::class) throws outside a request/CLI boot with
        // no DB). 30 min is the max useful window given pull_interval_minutes (default 15).
        $window = now()->subMinutes(30);
        if ($this->last_sync_at && $this->last_sync_at->gt($window)) {
            return 'online';
        }
        if ($this->last_seen_at && $this->last_seen_at->gt($window)) {
            return 'online';
        }

        return $this->last_sync_at || $this->last_seen_at ? 'offline' : 'unknown';
    }
}
