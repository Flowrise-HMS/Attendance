<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Attendance\Models\AttendanceShiftAssignment;

class AttendanceShiftAssignmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny AttendanceShiftAssignment');
    }

    public function view(AuthUser $authUser, AttendanceShiftAssignment $assignment): bool
    {
        return $authUser->can('View AttendanceShiftAssignment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create AttendanceShiftAssignment');
    }

    public function update(AuthUser $authUser, AttendanceShiftAssignment $assignment): bool
    {
        return $authUser->can('Update AttendanceShiftAssignment');
    }

    public function delete(AuthUser $authUser, AttendanceShiftAssignment $assignment): bool
    {
        return $authUser->can('Delete AttendanceShiftAssignment');
    }

    public function restore(AuthUser $authUser, AttendanceShiftAssignment $assignment): bool
    {
        return $authUser->can('Restore AttendanceShiftAssignment');
    }

    public function forceDelete(AuthUser $authUser, AttendanceShiftAssignment $assignment): bool
    {
        return $authUser->can('ForceDelete AttendanceShiftAssignment');
    }
}
