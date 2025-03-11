<?php

use Google\Service\AppHub\Service;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/midtrans/callback', [\App\Http\Controllers\MidtransController::class, 'callback']);