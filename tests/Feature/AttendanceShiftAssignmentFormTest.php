<?php

namespace Modules\Attendance\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Attendance\Filament\Resources\AttendanceShiftAssignmentResource\Pages\CreateAttendanceShiftAssignment;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Core\Models\Branch;
use Modules\Staff\Models\Staff;
use Tests\TestCase;

class AttendanceShiftAssignmentFormTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Staff', 'Attendance']);

        Gate::before(fn (): bool => true);

        $user = User::factory()->create();
        $this->actingAs($user);

        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    public function test_create_page_loads_with_staff_options_using_full_name_accessor(): void
    {
        $branch = Branch::factory()->create();
        Staff::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
        ]);
        AttendanceShift::factory()->create(['branch_id' => $branch->id]);

        Livewire::test(CreateAttendanceShiftAssignment::class)
            ->assertOk()
            ->assertFormSet([
                'staff_id' => null,
            ]);
    }
}
