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

Route::get('/users', function () {
    return User::with(['farmer', 'buyer'])->get()->map(function ($user) {
        return [
            'id'        => $user->id,
            'phone'     => $user->phone,
            'email'     => $user->email,
            'user_type' => $user->user_type,
            'status'    => $user->status,
            'profile'   => $user->profile(),
        ];
    });
});
