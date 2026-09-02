<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Attendance\Filament\Resources\DailyAttendanceResource\Pages\ListDailyAttendances;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Staff\Models\Staff;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DailyAttendanceExportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Staff', 'Attendance']);

        Storage::fake('local');
    }

    /**
     * Regression: this action used to have no exporter attached, so clicking
     * Export crashed instead of producing a file.
     */
    public function test_permission_holder_can_export_daily_attendance(): void
    {
        Permission::findOrCreate('export_daily_attendance', 'web');
        Permission::findOrCreate('ViewAny DailyAttendance', 'web');
        $user = User::factory()->create()->givePermissionTo('export_daily_attendance', 'ViewAny DailyAttendance');

        $branch = BranchFactory::new()->create();
        $staff = Staff::factory()->create(['branch_id' => $branch->id]);
        DailyAttendance::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'work_date' => now()->toDateString(),
            'status' => 'present',
        ]);

        Livewire::actingAs($user)
            ->test(ListDailyAttendances::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::query()->latest('id')->first();

        $this->assertNotNull($export);
        $this->assertNotNull($export->completed_at);
        $this->assertSame(1, $export->successful_rows);
    }

    public function test_export_action_is_hidden_without_the_permission(): void
    {
        Permission::findOrCreate('ViewAny DailyAttendance', 'web');

        Livewire::actingAs(User::factory()->create()->givePermissionTo('ViewAny DailyAttendance'))
            ->test(ListDailyAttendances::class)
            ->assertActionHidden('export');
    }
}
