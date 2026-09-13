<?php

use App\Http\Controllers\Api\KioskController;
use App\Http\Controllers\Api\OnlineController;
use App\Http\Controllers\Api\PatientAuthController;
use App\Http\Controllers\Api\PatientVisitController;
use App\Http\Controllers\Api\PublicQueueDisplayController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ReceptionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\VisitPriorityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('kiosk')->middleware('throttle:60,1')->group(function () {
        Route::get('/departments', [KioskController::class, 'departments']);
        Route::post('/queue-acquisitions', [KioskController::class, 'acquire']);
    });

    Route::prefix('public')->middleware('throttle:120,1')->group(function () {
        Route::get('/queues/{station}', [PublicQueueDisplayController::class, 'show']);
    });

    Route::prefix('patient')->group(function () {
        Route::post('/register', [PatientAuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('/login', [PatientAuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::prefix('patient')->group(function () {
            Route::get('/me', [PatientAuthController::class, 'me']);
            Route::get('/visits', [PatientVisitController::class, 'index']);
            Route::get('/visits/{visit}', [PatientVisitController::class, 'show']);
            Route::get('/queue', [PatientVisitController::class, 'queue']);
            Route::post('/visits/{visit}/check-in', [PatientVisitController::class, 'checkIn']);
        });

        Route::prefix('online')->group(function () {
            Route::post('/visits', [OnlineController::class, 'store']);
        });

        Route::prefix('reception')->group(function () {
            Route::post('/visits', [ReceptionController::class, 'store']);
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

        Route::prefix('priority')->group(function () {
            Route::patch('/visits/{visit}', [VisitPriorityController::class, 'update']);
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
