<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Context;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Staff\Models\Staff;

class DailyAttendancePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny DailyAttendance') || $this->ownStaffId($authUser) !== null;
    }

    public function view(AuthUser $authUser, DailyAttendance $attendance): bool
    {
        if ($authUser->can('View DailyAttendance')) {
            return $this->inCurrentBranch($authUser, $attendance->branch_id);
        }

        return $this->ownStaffId($authUser) !== null && $attendance->staff_id === $this->ownStaffId($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create DailyAttendance');
    }

    public function update(AuthUser $authUser, DailyAttendance $attendance): bool
    {
        return $authUser->can('Update DailyAttendance') && $this->inCurrentBranch($authUser, $attendance->branch_id);
    }

    public function delete(AuthUser $authUser, DailyAttendance $attendance): bool
    {
        return $authUser->can('Delete DailyAttendance') && $this->inCurrentBranch($authUser, $attendance->branch_id);
    }

    public function override(AuthUser $authUser): bool
    {
        return $authUser->can('override_daily_attendance_status');
    }

    public function export(AuthUser $authUser): bool
    {
        return $authUser->can('export_daily_attendance');
    }

    protected function inCurrentBranch(AuthUser $authUser, ?string $branchId): bool
    {
        if (! $branchId) {
            return true;
        }

        $current = Context::get('current_branch_id') ?? $authUser->branch_id;

        return $branchId === $current;
    }

    protected function ownStaffId(AuthUser $authUser): ?string
    {
        return Staff::query()->where('user_id', $authUser->getAuthIdentifier())->value('id');
    }
}
