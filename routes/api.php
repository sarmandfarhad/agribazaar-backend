<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\FarmerProductController as AdminFarmerProductController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\BasketController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CronController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\Farmer\OrderController as FarmerOrderController;
use App\Http\Controllers\Api\Farmer\ProductController as FarmerProductController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WishlistController;
use Illuminate\Support\Facades\Route;

// ─── Public ────────────────────────────────────────────────────────

Route::get('/media/{path}', [MediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

Route::prefix('auth')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
    Route::post('/farmer/signup', [AuthController::class, 'farmerSignup']);
    Route::post('/buyer/signup', [AuthController::class, 'buyerSignup']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Vercel Cron, protected by CRON_SECRET
Route::get('/cron/confirm-orders', [CronController::class, 'confirmOrders']);

// ─── Any signed-in user ────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/delivery/settings', [DeliveryController::class, 'show']);

    // Buyer who placed it, an assigned farmer once confirmed, or an admin
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');

    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    // ─── Buyers ────────────────────────────────────────────────────

    Route::middleware('role:buyer')->group(function () {
        Route::get('/basket', [BasketController::class, 'index']);
        Route::post('/basket/items', [BasketController::class, 'store']);
        Route::patch('/basket/items/{id}', [BasketController::class, 'update'])->whereNumber('id');
        Route::delete('/basket/items/{id}', [BasketController::class, 'destroy'])->whereNumber('id');

        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/buyer', [OrderController::class, 'index']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{id}/feedback', [OrderController::class, 'feedback']);
    });

    // ─── Farmers ───────────────────────────────────────────────────

    Route::middleware('role:farmer')->group(function () {
        Route::get('/orders/farmer', [FarmerOrderController::class, 'index']);
        Route::post('/orders/{id}/respond', [FarmerOrderController::class, 'respond']);

        Route::get('/farmer/products', [FarmerProductController::class, 'index']);
        Route::post('/farmer/products', [FarmerProductController::class, 'store']);
        Route::match(['PUT', 'PATCH'], '/farmer/products/{id}', [FarmerProductController::class, 'update']);
        Route::delete('/farmer/products/{id}', [FarmerProductController::class, 'destroy']);
    });

    // ─── Admins ────────────────────────────────────────────────────

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{product}', [AdminProductController::class, 'show']);
        Route::post('/products', [AdminProductController::class, 'store']);
        Route::match(['PUT', 'POST', 'PATCH'], '/products/{product}', [AdminProductController::class, 'update']);
        Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::match(['PUT', 'POST', 'PATCH'], '/categories/{category}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);

        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::post('/orders', [AdminOrderController::class, 'store']);
        Route::post('/orders/{id}/assign-farmers', [AdminOrderController::class, 'assignFarmers']);
        Route::post('/orders/{id}/update-status', [AdminOrderController::class, 'updateStatus']);
        Route::get('/feedback', [AdminOrderController::class, 'feedback']);

        Route::get('/farmer-products', [AdminFarmerProductController::class, 'index']);
        Route::post('/farmer-products', [AdminFarmerProductController::class, 'store']);
        Route::match(['PUT', 'PATCH'], '/farmer-products/{id}', [AdminFarmerProductController::class, 'update']);
        Route::delete('/farmer-products/{id}', [AdminFarmerProductController::class, 'destroy']);
        Route::post('/farmer-products/{id}/rate', [AdminFarmerProductController::class, 'rate']);

        Route::get('/delivery/settings', [DeliveryController::class, 'show']);
        Route::put('/delivery/settings', [DeliveryController::class, 'update']);
    });
});
