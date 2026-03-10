<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\Api\Auth\AuthController;

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
    });
});
