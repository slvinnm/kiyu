<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::redirect('/docs', '/docs/index.html');
Route::redirect('/api-docs', '/docs/index.html');
