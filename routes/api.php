<?php

use App\Http\Controllers\Api\KioskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('kiosk')->group(function () {
        Route::get('/departments', [KioskController::class, 'departments']);
        Route::post('/queue-acquisitions', [KioskController::class, 'acquire']);
    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->middleware('auth:sanctum');
});
