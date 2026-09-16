<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::inertia('/kiosk', 'kiosk/index')->name('kiosk');
Route::inertia('/login', 'auth/login')->name('login');
Route::inertia('/signup', 'auth/signup')->name('signup');

Route::prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::inertia('/', 'admin/dashboard')
            ->name('dashboard');

        Route::inertia('/queue', 'admin/queue/index')
            ->name('queue.index');

        Route::inertia('/queue/stations', 'admin/queue/stations')
            ->name('queue.stations');

        Route::inertia('/patients', 'admin/patients/index')
            ->name('patients.index');

        Route::inertia('/patients/{patient}', 'admin/patients/show')
            ->name('patients.show');

        Route::inertia('/visits', 'admin/visits/index')
            ->name('visits.index');

        Route::inertia('/visits/{visit}', 'admin/visits/show')
            ->name('visits.show');

        Route::inertia('/visits/{visit}', 'admin/visits/show')
            ->name('visits.show');

        Route::inertia('/referrals', 'admin/referrals/index')
            ->name('referrals.index');

        Route::inertia('/referrals/{referral}', 'admin/referrals/show')
            ->name('referrals.show');
    });

Route::prefix('app')
    ->name('app.')
    ->group(function () {
        Route::inertia('/', 'app/index')
            ->name('dashboard');
    });
