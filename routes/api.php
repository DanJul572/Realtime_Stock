<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::group([
    'middleware' => 'auth:sanctum'
], function () {
    Route::apiResource('products', ProductController::class);
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
});
