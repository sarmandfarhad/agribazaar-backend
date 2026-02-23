<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
Route::prefix('auth')->group(function () {
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
});
Route::get('/users', function () {
    return User::all();
});
