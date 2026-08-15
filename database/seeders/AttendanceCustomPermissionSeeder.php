<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AttendanceCustomPermissionSeeder extends Seeder
{
    /**
     * permission name => web-guard roles.
     *
     * Names MUST be the config KEYS (snake_case) — Shield names custom permissions from the
     * config key (verified transformCustomPermissions + case=snake), so the DB rows are
     * `import_attendance_records`, `override_daily_attendance_status`, etc., NOT the labels.
     */
    protected array $matrix = [
        'import_attendance_records' => ['super_admin', 'admin'],
        'override_daily_attendance_status' => ['super_admin', 'admin'],
        'export_daily_attendance' => ['super_admin', 'admin'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->matrix as $name => $roles) {
            $perm = Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );

            foreach ($roles as $roleName) {
                Role::query()
                    ->where(['name' => $roleName, 'guard_name' => 'web'])
                    ->first()
                    ?->givePermissionTo($perm);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
