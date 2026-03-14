<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Api\Auth\AuthController;

// Public Catalog routes
Route::get('/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/products/{product}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
Route::get('/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/categories/{category}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/farmer/signup', [AuthController::class, 'farmerSignup']);
    Route::post('/buyer/signup', [AuthController::class, 'buyerSignup']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes (require auth:sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Admin routes
    Route::prefix('admin')->group(function () {
        Route::get('/users', [\App\Http\Controllers\Api\Admin\UserController::class, 'index']);
        Route::patch('/users/{user}/status', [\App\Http\Controllers\Api\Admin\UserController::class, 'updateStatus']);

        // Product management
        Route::get('/products', [\App\Http\Controllers\Api\Admin\ProductController::class, 'index']);
        Route::get('/products/{product}', [\App\Http\Controllers\Api\Admin\ProductController::class, 'show']);
        Route::post('/products', [\App\Http\Controllers\Api\Admin\ProductController::class, 'store']);
        Route::match(['PUT', 'POST', 'PATCH'], '/products/{product}', [\App\Http\Controllers\Api\Admin\ProductController::class, 'update']);
        Route::delete('/products/{product}', [\App\Http\Controllers\Api\Admin\ProductController::class, 'destroy']);

        // Category management
        Route::get('/categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'index']);
        Route::get('/categories/{category}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'show']);
        Route::post('/categories', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'store']);
        Route::match(['PUT', 'POST', 'PATCH'], '/categories/{category}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'update']);
        Route::delete('/categories/{category}', [\App\Http\Controllers\Api\Admin\CategoryController::class, 'destroy']);

        // Order management (Admin)
        Route::get('/orders', [\App\Http\Controllers\Api\OrderController::class, 'index']);
        Route::post('/orders/{id}/assign-farmers', [\App\Http\Controllers\Api\OrderController::class, 'assignFarmers']);
    });

    // Buyer Order routes
    Route::post('/orders', [\App\Http\Controllers\Api\OrderController::class, 'store']);
    Route::get('/orders/buyer', [\App\Http\Controllers\Api\OrderController::class, 'buyerOrders']);
    Route::post('/orders/{id}/cancel', [\App\Http\Controllers\Api\OrderController::class, 'cancelOrder']);

    // Farmer Order routes
    Route::get('/orders/farmer', [\App\Http\Controllers\Api\OrderController::class, 'farmerOrders']);
    Route::post('/orders/{id}/respond', [\App\Http\Controllers\Api\OrderController::class, 'updateFarmerStatus']);
});
