<?php

namespace Modules\Attendance\Database\Seeders;

use Illuminate\Database\Seeder;

class AttendanceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AttendanceCustomPermissionSeeder::class);
    }
}
