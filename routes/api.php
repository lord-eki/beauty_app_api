<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\BusinessProfileController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MpesaTransactionController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceAvailabilityController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\ServiceStaffController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/verify-phone', [AuthController::class, 'verifyPhone'])->middleware('throttle:6,1');
        Route::post('/verify-phone/resend' , [AuthController::class, 'resendOtp'])->middleware('throttle:3,1');
    });
});

// MPesa callback
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
    Route::get('/{businessProfile}/promotions/active', [PromotionController::class, 'activeForBusiness']);

    // Public: staff list and availability (customers need these to book)
    Route::get('/{businessProfile}/staff', [ServiceStaffController::class, 'publicList']);
    Route::get('/{businessProfile}/availability', [ServiceAvailabilityController::class, 'publicSlots']);
});

// Public: nearest locations search
Route::get('/locations/nearby', [BusinessLocationController::class, 'nearby']);

/*
|--------------------------------------------------------------------------
| PROTECTED ROUTES (SANCTUM)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('user')->group(function () {
        Route::get('/profile', [UserController::class, 'show']);
        Route::put('/profile', [UserController::class, 'update']);
        Route::post('/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('/avatar', [UserController::class, 'deleteAvatar']);
    });

    /*
    |-------------- SUBSCRIPTIONS ----------------------------------------------
    */
    Route::prefix('subscription')->group(function () {
        Route::get('/status', [SubscriptionController::class, 'status']);
        Route::post('/pay', [SubscriptionController::class, 'pay']);
        Route::get('/history', [SubscriptionController::class, 'history']);
        Route::post('/query', [SubscriptionController::class, 'query']);
    });

    /*
    |-------------- PROMOTIONS (customer-facing) -------------------------------
    */
    Route::prefix('promotions')->group(function () {
        Route::get('/validate', [PromotionController::class, 'validate']);
        Route::post('/redeem', [PromotionController::class, 'redeem']);
    });

    /*
    |-------------- REVIEWS (customer) ----------------------------------------
    */
    Route::prefix('reviews')->group(function () {
        Route::post('/', [ReviewController::class, 'store']);
        Route::put('/{review}', [ReviewController::class, 'update']);
        Route::delete('/{review}', [ReviewController::class, 'destroy']);
    });

    /*
    |-------------- CHAT -------------------------------------------------------
    */
    Route::prefix('conversations')->group(function () {
        Route::get('/', [ChatController::class, 'index']);
        Route::post('/', [ChatController::class, 'store']);
        Route::delete('/{conversation}', [ChatController::class, 'close']);
        Route::get('/{conversation}/messages', [ChatController::class, 'messages']);
        Route::post('/{conversation}/messages', [ChatController::class, 'sendMessage']);
        Route::post('/{conversation}/typing', [ChatController::class, 'typing']);
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

        // Locations — no subscription gate (needed at account setup)
        Route::prefix('locations')->group(function () {
            Route::get('/', [BusinessLocationController::class, 'index']);
            Route::post('/', [BusinessLocationController::class, 'store']);
            Route::get('/{businessLocation}', [BusinessLocationController::class, 'show']);
            Route::put('/{businessLocation}', [BusinessLocationController::class, 'update']);
            Route::delete('/{businessLocation}', [BusinessLocationController::class, 'destroy']);
            Route::middleware('subscription')->group(function () {
                Route::get('/{businessLocation}/inventory', [BusinessLocationController::class, 'inventory']);
                Route::get('/{businessLocation}/appointments', [BusinessLocationController::class, 'appointments']);
                Route::get('/{businessLocation}/staff', [BusinessLocationController::class, 'staff']);
            });
        });

        // --- Subscription required below ---
        Route::middleware('subscription')->group(function () {

            /* -- Services -- */
            Route::prefix('services')->group(function () {
                Route::get('/', [ServicesController::class, 'index']);
                Route::post('/', [ServicesController::class, 'store']);
                Route::get('/{service}', [ServicesController::class, 'show']);
                Route::put('/{service}', [ServicesController::class, 'update']);
                Route::delete('/{service}', [ServicesController::class, 'destroy']);
                Route::post('/{service}/images', [ServicesController::class, 'uploadImages']);
                Route::delete('/{service}/images/{imageIndex}', [ServicesController::class, 'deleteImage']);
            });

            /* -- Products -- */
            Route::prefix('products')->group(function () {
                Route::get('/', [ProductController::class, 'index']);
                Route::post('/', [ProductController::class, 'store']);
                Route::get('/{product}', [ProductController::class, 'show']);
                Route::put('/{product}', [ProductController::class, 'update']);
                Route::delete('/{product}', [ProductController::class, 'destroy']);
                Route::post('/{product}/images', [ProductController::class, 'uploadImages']);
                Route::delete('/{product}/images/{imageIndex}', [ProductController::class, 'deleteImage']);
            });

            /* -- Inventory -- */
            Route::prefix('inventory')->group(function () {
                Route::get('/', [InventoryController::class, 'index']);
                Route::get('/low-stock', [InventoryController::class, 'lowStock']);
                Route::get('/movements', [InventoryController::class, 'movements']);
                Route::post('/movement', [InventoryController::class, 'recordMovement']);
                Route::post('/restock', [InventoryController::class, 'restock']);
                Route::get('/{product}', [InventoryController::class, 'show']);
                Route::put('/{product}', [InventoryController::class, 'update']);
            });

            /* -- Staff -- */
            Route::prefix('staff')->group(function () {
                Route::get('/', [ServiceStaffController::class, 'index']);
                Route::post('/', [ServiceStaffController::class, 'store']);
                Route::get('/{staff}', [ServiceStaffController::class, 'show']);
                Route::put('/{staff}', [ServiceStaffController::class, 'update']);
                Route::delete('/{staff}', [ServiceStaffController::class, 'destroy']);
            });

            /* -- Schedule / Availability -- */
            Route::prefix('schedule')->group(function () {
                Route::get('/', [ServiceAvailabilityController::class, 'index']);
                Route::post('/', [ServiceAvailabilityController::class, 'store']);
                Route::put('/{serviceAvailability}', [ServiceAvailabilityController::class, 'update']);
                Route::delete('/{serviceAvailability}', [ServiceAvailabilityController::class, 'destroy']);
            });

            /* -- Promotions -- */
            Route::prefix('promotions')->group(function () {
                Route::get('/', [PromotionController::class, 'index']);
                Route::post('/', [PromotionController::class, 'store']);
                Route::get('/{promotion}', [PromotionController::class, 'show']);
                Route::put('/{promotion}', [PromotionController::class, 'update']);
                Route::delete('/{promotion}', [PromotionController::class, 'destroy']);
            });

            /* -- Appointments (business view) -- */
            Route::get('/appointments', [AppointmentController::class, 'businessAppointments']);
            Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
            Route::post('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);

            /* -- Orders (business view) -- */
            Route::get('/orders', [OrderController::class, 'businessOrders']);
            Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        });
    });

    /*
    |-------------- CUSTOMER — APPOINTMENTS ------------------------------------
    */
    Route::prefix('appointments')->group(function () {
        Route::get('/', [AppointmentController::class, 'index']);
        Route::post('/', [AppointmentController::class, 'store']);
        Route::get('/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
    });

    /*
    |-------------- CUSTOMER — ORDERS ------------------------------------------
    */
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::post('/{order}/cancel', [OrderController::class, 'cancel']);
    });
});