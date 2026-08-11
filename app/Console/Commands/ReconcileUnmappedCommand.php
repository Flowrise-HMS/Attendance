<?php

namespace Modules\Attendance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Attendance\Models\AttendanceRecord;

class ReconcileUnmappedCommand extends Command
{
    protected $signature = 'attendance:reconcile-unmapped {--days=30 : Only consider punches within the last N days}';

    protected $description = 'Report attendance punches without a linked staff member';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $rows = AttendanceRecord::query()
            ->unmapped()
            ->where('punched_at', '>=', now()->subDays($days))
            ->selectRaw('badge_number, COUNT(*) as total, MIN(punched_at) as first_punch, MAX(punched_at) as last_punch')
            ->groupBy('badge_number')
            ->orderByDesc('total')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No unmapped punches found.');

            return self::SUCCESS;
        }

        $this->table(['Badge', 'Punches', 'First', 'Last'], $rows->map(fn ($row) => [
            $row->badge_number,
            $row->total,
            $row->first_punch,
            $row->last_punch,
        ])->all());

        return self::SUCCESS;
    }
}
