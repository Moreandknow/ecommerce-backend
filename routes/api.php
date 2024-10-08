<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\ForgotPasswordController;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [AuthenticationController::class, 'register']);
Route::post('/resend-otp', [AuthenticationController::class, 'resendOtp']);
Route::post('/check-otp-register', [AuthenticationController::class, 'verifyOtp']);
Route::post('/verify-register', [AuthenticationController::class, 'verifyRegister']);

Route::prefix('forgot-password')->group(function(){
    Route::post('/request', [ForgotPasswordController::class, 'request']);
    Route::post('/resend-otp', [ForgotPasswordController::class, 'resendOtp']);
    Route::post('/check-otp', [ForgotPasswordController::class, 'verifyOtp']);
    Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
});

Route::post('/login', [AuthenticationController::class, 'login']);

Route::middleware('auth:sanctum')->group(function(){

    Route::get('profile', [ProfileController::class, 'getProfile']);
    Route::patch('profile', [ProfileController::class, 'updateProfile']);

});