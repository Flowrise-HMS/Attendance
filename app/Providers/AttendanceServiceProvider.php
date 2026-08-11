<?php

namespace Modules\Attendance\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Attendance\Classes\Services\AttendanceDailyService;
use Modules\Attendance\Classes\Services\AttendanceIngestionService;
use Modules\Attendance\Classes\Services\AttendancePullService;
use Modules\Attendance\Console\Commands\ComputeDailyAttendanceCommand;
use Modules\Attendance\Console\Commands\PullAttendanceCommand;
use Modules\Attendance\Console\Commands\ReconcileUnmappedCommand;
use Modules\Attendance\Models\AttendanceMachine;
use Modules\Attendance\Models\AttendanceRecord;
use Modules\Attendance\Models\AttendanceShift;
use Modules\Attendance\Models\AttendanceShiftAssignment;
use Modules\Attendance\Models\DailyAttendance;
use Modules\Attendance\Policies\AttendanceMachinePolicy;
use Modules\Attendance\Policies\AttendanceRecordPolicy;
use Modules\Attendance\Policies\AttendanceShiftAssignmentPolicy;
use Modules\Attendance\Policies\AttendanceShiftPolicy;
use Modules\Attendance\Policies\DailyAttendancePolicy;
use Modules\Attendance\Settings\AttendanceSettings;
use Nwidart\Modules\Support\ModuleServiceProvider;

class AttendanceServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Attendance';

    protected string $nameLower = 'attendance';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        // NOTE: parent::boot() already calls $this->registerCommands() — do NOT call it again here.
        $this->registerPolicies();
        $this->registerRateLimiter();
        $this->registerServices();
    }

    protected function registerCommands(): void
    {
        // Commands are created in later tasks; guard so artisan does not fatal while they
        // do not exist yet (Artisan::starting resolves every registered class on each run).
        $this->commands(array_filter([
            class_exists(PullAttendanceCommand::class) ? PullAttendanceCommand::class : null,
            class_exists(ComputeDailyAttendanceCommand::class) ? ComputeDailyAttendanceCommand::class : null,
            class_exists(ReconcileUnmappedCommand::class) ? ReconcileUnmappedCommand::class : null,
        ]));
    }

    protected function registerPolicies(): void
    {
        Gate::policy(AttendanceMachine::class, AttendanceMachinePolicy::class);
        Gate::policy(AttendanceShift::class, AttendanceShiftPolicy::class);
        Gate::policy(AttendanceShiftAssignment::class, AttendanceShiftAssignmentPolicy::class);
        Gate::policy(AttendanceRecord::class, AttendanceRecordPolicy::class);
        Gate::policy(DailyAttendance::class, DailyAttendancePolicy::class);
    }

    protected function registerRateLimiter(): void
    {
        // Key by client IP (plus serial when present) so one flooded device cannot
        // starve the other machines in the same branch.
        RateLimiter::for('iclock', function (Request $request) {
            $serial = (string) $request->query('SN', '');
            $key = $serial !== '' ? $serial : ($request->ip() ?? 'iclock');

            return Limit::perMinute(300)->by($key);
        });
    }

    protected function registerServices(): void
    {
        $this->app->singleton(AttendanceIngestionService::class);
        $this->app->singleton(AttendanceDailyService::class);
        $this->app->singleton(
            AttendancePullService::class,
            fn () => new AttendancePullService(app(AttendanceIngestionService::class))
        );
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('attendance:pull')
            ->everyFifteenMinutes()
            // v1: cadence fixed at 15 min; pull_interval_minutes only gates on/off
            // (spec §4.1 says "every pull_interval_minutes" — dynamic cadence deferred).
            ->when(function (): bool {
                try {
                    return app(AttendanceSettings::class)->pull_interval_minutes > 0;
                } catch (\Throwable) {
                    return false;
                }
            });

        $schedule->command('attendance:compute-daily')
            ->dailyAt('01:00');
    }
}
