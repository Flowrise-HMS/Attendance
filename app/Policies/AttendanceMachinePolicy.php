<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Attendance\Models\AttendanceMachine;

class AttendanceMachinePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny AttendanceMachine');
    }

    public function view(AuthUser $authUser, AttendanceMachine $machine): bool
    {
        return $authUser->can('View AttendanceMachine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create AttendanceMachine');
    }

    public function update(AuthUser $authUser, AttendanceMachine $machine): bool
    {
        return $authUser->can('Update AttendanceMachine');
    }

    public function delete(AuthUser $authUser, AttendanceMachine $machine): bool
    {
        return $authUser->can('Delete AttendanceMachine');
    }

    public function restore(AuthUser $authUser, AttendanceMachine $machine): bool
    {
        return $authUser->can('Restore AttendanceMachine');
    }

    public function forceDelete(AuthUser $authUser, AttendanceMachine $machine): bool
    {
        return $authUser->can('ForceDelete AttendanceMachine');
    }
}
