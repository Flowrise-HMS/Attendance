<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Attendance\Models\AttendanceShift;

class AttendanceShiftPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny AttendanceShift');
    }

    public function view(AuthUser $authUser, AttendanceShift $shift): bool
    {
        return $authUser->can('View AttendanceShift');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create AttendanceShift');
    }

    public function update(AuthUser $authUser, AttendanceShift $shift): bool
    {
        return $authUser->can('Update AttendanceShift');
    }

    public function delete(AuthUser $authUser, AttendanceShift $shift): bool
    {
        return $authUser->can('Delete AttendanceShift');
    }

    public function restore(AuthUser $authUser, AttendanceShift $shift): bool
    {
        return $authUser->can('Restore AttendanceShift');
    }

    public function forceDelete(AuthUser $authUser, AttendanceShift $shift): bool
    {
        return $authUser->can('ForceDelete AttendanceShift');
    }
}
