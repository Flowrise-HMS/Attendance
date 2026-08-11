<?php

namespace Modules\Attendance\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Attendance';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapIclockRoutes();
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapIclockRoutes(): void
    {
        Route::middleware('throttle:iclock')->prefix('iclock')->group(module_path($this->name, '/routes/iclock.php'));
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/web.php'));
    }

    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }
}
