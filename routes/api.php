<?php

use App\Http\Controllers\Api\KioskController;
use App\Http\Controllers\Api\ReceptionController;
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

        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });
});
