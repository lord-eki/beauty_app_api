<?php

use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServicesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\AuthController;


// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Public categories
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::get('/{category}/services', [CategoryController::class, 'services']);
    Route::get('/{category}/products', [CategoryController::class, 'products']);
});

// Public search
Route::prefix('search')->group(function () {
    Route::get('/services', [SearchController::class, 'services']);
    Route::get('/products', [SearchController::class, 'products']);
    Route::get('/businesses', [SearchController::class, 'businesses']);
});

// Public business profiles
Route::prefix('business')->group(function () {
    Route::get('/{businessProfile}', [BusinessProfileController::class, 'show']);
    Route::get('/{businessProfile}/services', [ServicesController::class, 'public']);
    Route::get('/{businessProfile}/products', [ProductController::class, 'public']);
    Route::get('/{businessProfile}/reviews', [ReviewController::class, 'index']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // User profile
    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
    });

    // Business Profile Management
    Route::prefix('business')->group(function () {
        Route::get('/profile', [BusinessProfileController::class, 'index']);
        Route::post('/profile', [BusinessProfileController::class, 'store']);
        Route::put('/profile', [BusinessProfileController::class, 'update']);
        Route::delete('/profile', [BusinessProfileController::class, 'destroy']);
        Route::get('/statistics', [BusinessProfileController::class, 'statistics']);
    });

    // Business Locations
    Route::prefix('business/locations')->group(function () {
        Route::get('/', [BusinessLocationController::class, 'index']);
        Route::post('/', [BusinessLocationController::class, 'store']);
        Route::get('/{businessLocation}', [BusinessLocationController::class, 'show']);
        Route::put('/{businessLocation}', [BusinessLocationController::class, 'update']);
        Route::delete('/{businessLocation}', [BusinessLocationController::class, 'destroy']);
    });

    // Services Management
    Route::prefix('business/services')->group(function () {
        Route::get('/', [ServicesController::class, 'index']);
        Route::post('/', [ServicesController::class, 'store']);
        Route::get('/{service}', [ServicesController::class, 'show']);
        Route::put('/{service}', [ServicesController::class, 'update']);
        Route::delete('/{service}', [ServicesController::class, 'destroy']);
        Route::post('/{service}/images', [ServicesController::class, 'uploadImages']);
        Route::delete('/{service}/images/{imageIndex}', [ServicesController::class, 'deleteImage']);
    });

    // Products Management
    Route::prefix('business/products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::post('/', [ProductController::class, 'store']);
        Route::get('/{product}', [ProductController::class, 'show']);
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::post('/{product}/images', [ProductController::class, 'uploadImages']);
        Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
    });

    // Reviews
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });
});

