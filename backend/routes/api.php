<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\SelectionStepController;
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

    // Selection Steps (選考ステップ管理)
    Route::post('job-applications/{jobApplicationId}/selection-steps', [SelectionStepController::class, 'store']);
    Route::put('job-applications/{jobApplicationId}/selection-steps/{stepId}', [SelectionStepController::class, 'update']);
    Route::delete('job-applications/{jobApplicationId}/selection-steps/{stepId}', [SelectionStepController::class, 'destroy']);
});
