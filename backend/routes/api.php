<?php

use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports', [ReportController::class, 'index']);
Route::post('/reports', [ReportController::class, 'store'])->middleware('throttle:report-create');
Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->whereNumber('report')->middleware('throttle:report-delete');
Route::get('/reports/{report}', [ReportController::class, 'show'])->whereNumber('report');
