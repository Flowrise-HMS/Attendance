<?php

declare(strict_types=1);

namespace Modules\Attendance\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Context;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Staff\Models\Staff;

class AttendanceRecordPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny AttendanceRecord') || $this->ownStaffId($authUser) !== null;
    }

    public function view(AuthUser $authUser, AttendanceRecord $record): bool
    {
        if ($authUser->can('View AttendanceRecord')) {
            return $this->inCurrentBranch($authUser, $record->branch_id);
        }

        return $this->ownStaffId($authUser) !== null && $record->staff_id === $this->ownStaffId($authUser);
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create AttendanceRecord');
    }

    public function update(AuthUser $authUser, AttendanceRecord $record): bool
    {
        return $authUser->can('Update AttendanceRecord') && $this->inCurrentBranch($authUser, $record->branch_id);
    }

    public function delete(AuthUser $authUser, AttendanceRecord $record): bool
    {
        return $authUser->can('Delete AttendanceRecord') && $this->inCurrentBranch($authUser, $record->branch_id);
    }

    public function import(AuthUser $authUser): bool
    {
        return $authUser->can('import_attendance_records');
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
