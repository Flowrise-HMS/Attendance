<?php

namespace Modules\Attendance\Filament\Clusters\Attendance;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Enums\SidebarGroup;

class AttendanceCluster extends Cluster
{
    protected static ?string $slug = 'attendance-cluster';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::Operations;

    protected static ?int $navigationSort = 30;
}
