<?php

declare(strict_types=1);

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\Filament\Clusters\Attendance\Pages\ManageAttendanceSettings;
use Modules\Attendance\Filament\Pages\AttendanceDashboard;
use Modules\Attendance\Models\AttendanceRecord;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Staff', 'Attendance']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('gates attendance import with the custom config permission', function (): void {
    Permission::findOrCreate('import_attendance_records', 'web');

    $user = User::factory()->create()->givePermissionTo('import_attendance_records');
    $this->actingAs($user);

    expect(Auth::user()?->can('import_attendance_records'))->toBeTrue()
        ->and($user->can('import', AttendanceRecord::class))->toBeTrue();
});

it('gates attendance pages with Shield page permissions', function (string $page): void {
    Filament::setCurrentPanel(Filament::getDefaultPanel());

    $permission = array_key_first(
        FilamentShield::getPages()[$page]['permissions'] ?? []
    );

    expect($permission)->toBeString();

    Permission::findOrCreate($permission, 'web');

    $this->actingAs(User::factory()->create()->givePermissionTo($permission));

    expect($page::canAccess())->toBeTrue();
})->with([
    'dashboard' => [AttendanceDashboard::class],
    'settings' => [ManageAttendanceSettings::class],
]);

it('denies attendance custom permissions and pages without grants', function (): void {
    Filament::setCurrentPanel(Filament::getDefaultPanel());

    $this->actingAs(User::factory()->create());

    expect(Auth::user()?->can('import_attendance_records'))->toBeFalse()
        ->and(AttendanceDashboard::canAccess())->toBeFalse()
        ->and(ManageAttendanceSettings::canAccess())->toBeFalse();
});
