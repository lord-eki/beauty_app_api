<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

// Auth (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

/*
|--------------------------------------------------------------------------
| PUBLIC BROWSING
|--------------------------------------------------------------------------
*/

// Categories
Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::get('/{category}/services', [CategoryController::class, 'services']);
    Route::get('/{category}/products', [CategoryController::class, 'products']);
});

// Search
Route::prefix('search')->group(function () {
    Route::get('/services', [SearchController::class, 'services']);
    Route::get('/products', [SearchController::class, 'products']);
    Route::get('/businesses', [SearchController::class, 'businesses']);
});


/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |---------------- AUTHENTICATED USER ----------------|
    */

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


    /*
    |---------------- BUSINESS PROFILE  ----------------|
    */

    Route::prefix('business')->group(function () {

        // profile management
        Route::get('/profile', [BusinessProfileController::class, 'show']);
        Route::post('/profile', [BusinessProfileController::class, 'store']);
        Route::put('/profile', [BusinessProfileController::class, 'update']);
        Route::delete('/profile', [BusinessProfileController::class, 'destroy']);
        Route::get('/statistics', [BusinessProfileController::class, 'statistics']);

        // locations
        Route::prefix('locations')->group(function () {
            Route::get('/', [BusinessLocationController::class, 'index']);
            Route::post('/', [BusinessLocationController::class, 'store']);
            Route::get('/{businessLocation}', [BusinessLocationController::class, 'show']);
            Route::put('/{businessLocation}', [BusinessLocationController::class, 'update']);
            Route::delete('/{businessLocation}', [BusinessLocationController::class, 'destroy']);
        });

        // services
        Route::prefix('services')->group(function () {
            Route::get('/', [ServicesController::class, 'index']);
            Route::post('/', [ServicesController::class, 'store']);
            Route::get('/{service}', [ServicesController::class, 'show']);
            Route::put('/{service}', [ServicesController::class, 'update']);
            Route::delete('/{service}', [ServicesController::class, 'destroy']);
            Route::post('/{service}/images', [ServicesController::class, 'uploadImages']);
            Route::delete('/{service}/images/{imageIndex}', [ServicesController::class, 'deleteImage']);
        });

        // products
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
            Route::post('/{product}/images', [ProductController::class, 'uploadImages']);
            Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
        });

        // provider appointment management
        Route::get('/appointments', [AppointmentController::class, 'businessAppointments']);
        Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
        Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);

        // provider order management
        Route::get('/orders', [OrderController::class, 'businessOrders']);
        Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
    });

    /*
    |---------------- CUSTOMER APPOINTMENTS ----------------|
    */

    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
    });

    /*
    |---------------- ORDERS (CUSTOMER) ----------------|
    */

    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
    });

    /*
    |---------------- REVIEWS ----------------|
    */

    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });

});




Route::prefix('business')->group(function () {
    Route::get('/{businessProfile}', [BusinessProfileController::class, 'showPublic']);
    Route::get('/{businessProfile}/services', [ServicesController::class, 'public']);
    Route::get('/{businessProfile}/products', [ProductController::class, 'public']);
    Route::get('/{businessProfile}/reviews', [ReviewController::class, 'index']);
});