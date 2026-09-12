<?php

use App\Http\Controllers\Api\KioskController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\ReferralController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('kiosk')->group(function () {
        Route::get('/departments', [KioskController::class, 'departments']);
        Route::post('/queue-acquisitions', [KioskController::class, 'acquire']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('reception')->group(function () {
            Route::post(
                '/queue-acquisitions/{queueAcquisition}/register',
                [ReceptionController::class, 'registerQueueAcquisition']
            );
        });

        Route::prefix('queue')->group(function () {
            Route::get('/stations', [QueueController::class, 'stations']);
            Route::post('/stations/{station}/call-next', [QueueController::class, 'callNext']);
            Route::post('/tickets/{queueTicket}/start', [QueueController::class, 'start']);
            Route::post('/tickets/{queueTicket}/complete', [QueueController::class, 'complete']);
            Route::post('/tickets/{queueTicket}/hold', [QueueController::class, 'hold']);
            Route::post('/tickets/{queueTicket}/resume', [QueueController::class, 'resume']);
            Route::post('/tickets/{queueTicket}/skip', [QueueController::class, 'skip']);
            Route::post('/tickets/{queueTicket}/no-show', [QueueController::class, 'noShow']);
            Route::post('/tickets/{queueTicket}/cancel', [QueueController::class, 'cancel']);
            Route::post('/tickets/{queueTicket}/transfer', [QueueController::class, 'transfer']);
        });

        Route::prefix('referrals')->group(function () {
            Route::post('/visits/{visit}', [ReferralController::class, 'store']);
            Route::get('/{referral}', [ReferralController::class, 'show']);
        });

        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
