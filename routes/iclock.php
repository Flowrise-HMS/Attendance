<?php

use Illuminate\Support\Facades\Route;
use Modules\Attendance\Http\Controllers\IclockController;

Route::get('cdata', [IclockController::class, 'cdata']);
Route::post('cdata', [IclockController::class, 'cdata']);
Route::get('getrequest', [IclockController::class, 'getrequest']);
