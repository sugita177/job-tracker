<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\JobApplicationController;
use App\Http\Controllers\Api\SelectionStepController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- 認証エンドポイント (Public) ---
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    // 認証情報・ログアウト
    Route::prefix('auth')->group(function () {
        Route::get('user', [AuthController::class, 'user']);
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // 企業管理
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
