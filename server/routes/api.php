<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\DocumentController;
use App\Http\Controllers\Api\Admin\GroupController as AdminGroupController;
use App\Http\Controllers\Api\Provider\GroupController as ProviderGroupController;
use App\Http\Controllers\Api\RideRequestController;
use App\Http\Controllers\Api\Admin\RateCardController;
use App\Http\Controllers\Api\Admin\SosController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\VerificationReviewController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FareController;
use App\Http\Controllers\Api\TrustedContactController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\VerificationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/role/switch', [AuthController::class, 'switchRole']);

    Route::get('/verification', [VerificationController::class, 'show']);
    Route::post('/verification', [VerificationController::class, 'store']);

    Route::post('/vehicle', [VehicleController::class, 'store'])->middleware('role:provider');

    Route::get('/contacts', [TrustedContactController::class, 'index']);
    Route::post('/contacts', [TrustedContactController::class, 'store']);
    Route::delete('/contacts/{id}', [TrustedContactController::class, 'destroy']);

    Route::post('/fare/quote', [FareController::class, 'quote']);

    // Passenger: request, list, view and cancel rides
    Route::post('/rides/preview', [RideRequestController::class, 'preview']);
    Route::get('/rides', [RideRequestController::class, 'index']);
    Route::post('/rides', [RideRequestController::class, 'store']);
    Route::get('/rides/{id}', [RideRequestController::class, 'show']);
    Route::post('/rides/{id}/cancel', [RideRequestController::class, 'cancel']);

    // Provider: matched groups waiting for a driver
    Route::prefix('provider')->middleware('role:provider')->group(function () {
        Route::get('/groups/available', [ProviderGroupController::class, 'available']);
        Route::get('/groups/mine', [ProviderGroupController::class, 'mine']);
        Route::post('/groups/{id}/accept', [ProviderGroupController::class, 'accept']);
        Route::post('/groups/{id}/release', [ProviderGroupController::class, 'release']);
    });

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/stats', [DashboardController::class, 'stats']);

        Route::get('/verifications', [VerificationReviewController::class, 'pending']);
        Route::get('/verifications/pending', [VerificationReviewController::class, 'pending']);
        Route::get('/verifications/{verification}/document/{type}', [DocumentController::class, 'show']);
        Route::post('/verifications/{verification}/approve', [VerificationReviewController::class, 'approve']);
        Route::post('/verifications/{verification}/reject', [VerificationReviewController::class, 'reject']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users/{user}/suspend', [UserController::class, 'setSuspended']);

        Route::get('/sos', [SosController::class, 'index']);
        Route::patch('/sos/{sos}', [SosController::class, 'update']);

        Route::get('/groups', [AdminGroupController::class, 'index']);
        Route::post('/matching/run', [AdminGroupController::class, 'runMatching']);

        Route::get('/rate-cards', [RateCardController::class, 'index']);
        Route::put('/rate-cards/{rateCard}', [RateCardController::class, 'update']);
    });
});
