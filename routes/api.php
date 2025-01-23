<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function() {
    return 'rest api is running';
})->name('test');

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::group([
    'middleware' => 'auth:sanctum'
], function () {
    Route::apiResource('products', ProductController::class);
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
});
