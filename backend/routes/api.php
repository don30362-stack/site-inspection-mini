<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports', [ReportController::class, 'index']);
Route::post('/reports', [ReportController::class, 'store']);
Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
