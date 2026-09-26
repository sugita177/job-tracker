<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\JobApplicationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('companies', CompanyController::class);

    // Job Applications
    Route::apiResource('job-applications', JobApplicationController::class);
    Route::post('job-applications/{id}/advance-status', [JobApplicationController::class, 'advanceStatus']);
    Route::post('job-applications/{id}/correct-status', [JobApplicationController::class, 'correctStatus']);
});
