<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::prefix('auth')->group(function () {
    Route::post('login', 'App\Http\Controllers\Auth\AuthController@login');
    Route::post('register', 'App\Http\Controllers\Auth\AuthController@register');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
    });
});
