<?php

use App\Http\Controllers\MpesaTransactionController;
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
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InventoryController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// MPesa callback — must be public (Safaricom posts here)
Route::post('/mpesa/callback', [MpesaTransactionController::class, 'callback']);

/*
|--------------------------------------------------------------------------
| PUBLIC BROWSING
|--------------------------------------------------------------------------
*/

Route::prefix('categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::get('/{category}/services', [CategoryController::class, 'services']);
    Route::get('/{category}/products', [CategoryController::class, 'products']);
});

Route::prefix('search')->group(function () {
    Route::get('/services', [SearchController::class, 'services']);
    Route::get('/products', [SearchController::class, 'products']);
    Route::get('/businesses', [SearchController::class, 'businesses']);
});

Route::prefix('business')->group(function () {
    Route::get('/{businessProfile}', [BusinessProfileController::class, 'showPublic']);
    Route::get('/{businessProfile}/services', [ServicesController::class, 'public']);
    Route::get('/{businessProfile}/products', [ProductController::class, 'public']);
    Route::get('/{businessProfile}/reviews', [ReviewController::class, 'index']);
});

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |-------------- AUTH & USER ------------------------------------------------
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
    |-------------- SUBSCRIPTIONS (no subscription gate — needed to pay) -------
    */
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/pay', [SubscriptionController::class, 'pay']);
        Route::get('/history', [SubscriptionController::class, 'history']);
        Route::post('/query', [SubscriptionController::class, 'query']);
    });

    /*
    |-------------- CHAT -------------------------------------------------------
    | Both customers and providers use these routes.
    */
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::delete('/{conversation}', [ChatController::class, 'close']);
        Route::get('/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/{conversation}/typing', [ChatController::class, 'typing']);         // typing indicator
    });
    Route::put('/messages/{message}/read', [ChatController::class, 'markRead']);

    /*
    |-------------- BUSINESS PROFILE ------------------------------------------
    */
    Route::prefix('business')->group(function () {

        Route::get('/profile', [BusinessProfileController::class, 'show']);
        Route::post('/profile', [BusinessProfileController::class, 'store']);
        Route::put('/profile', [BusinessProfileController::class, 'update']);
        Route::delete('/profile', [BusinessProfileController::class, 'destroy']);
        Route::get('/statistics', [BusinessProfileController::class, 'statistics']);

        Route::prefix('locations')->group(function () {
            Route::get('/', [BusinessLocationController::class, 'index']);
            Route::post('/', [BusinessLocationController::class, 'store']);
            Route::get('/{businessLocation}', [BusinessLocationController::class, 'show']);
            Route::put('/{businessLocation}', [BusinessLocationController::class, 'update']);
            Route::delete('/{businessLocation}', [BusinessLocationController::class, 'destroy']);
        });

        // --- Subscription required below ---
        Route::middleware('subscription')->group(function () {

            Route::prefix('services')->group(function () {
                Route::get('/', [ServicesController::class, 'index']);
                Route::post('/', [ServicesController::class, 'store']);
                Route::get('/{service}', [ServicesController::class, 'show']);
                Route::put('/{service}', [ServicesController::class, 'update']);
                Route::delete('/{service}', [ServicesController::class, 'destroy']);
                Route::post('/{service}/images', [ServicesController::class, 'uploadImages']);
                Route::delete('/{service}/images/{imageIndex}', [ServicesController::class, 'deleteImage']);
            });

            Route::prefix('products')->group(function () {
                Route::get('/', [ProductController::class, 'index']);
                Route::post('/', [ProductController::class, 'store']);
                Route::get('/{product}', [ProductController::class, 'show']);
                Route::put('/{product}', [ProductController::class, 'update']);
                Route::delete('/{product}', [ProductController::class, 'destroy']);
                Route::post('/{product}/images', [ProductController::class, 'uploadImages']);
                Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
            });

            Route::prefix('inventory')->group(function () {
                Route::get('/', [InventoryController::class, 'index']);
                Route::get('/low-stock', [InventoryController::class, 'lowStock']);
                Route::get('/movements', [InventoryController::class, 'movements']);
                Route::post('/movement', [InventoryController::class, 'recordMovement']);
                Route::post('/restock', [InventoryController::class, 'restock']);
                Route::get('/{product}', [InventoryController::class, 'show']);
                Route::put('/{product}', [InventoryController::class, 'update']);
            });

            Route::get('/appointments', [AppointmentController::class, 'businessAppointments']);
            Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
            Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);

            Route::get('/orders', [OrderController::class, 'businessOrders']);
            Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        });
    });

    /*
    |-------------- CUSTOMER — APPOINTMENTS -----------------------------------
    */
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
    });

    /*
    |-------------- CUSTOMER — ORDERS ----------------------------------------
    */
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
    });

    /*
    |-------------- REVIEWS --------------------------------------------------
    */
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });
});