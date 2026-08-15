<?php

namespace Modules\Attendance\Filament\Clusters\Attendance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class AttendanceCluster extends Cluster
{
    protected static ?string $slug = 'attendance-cluster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;
}
