<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/test', function() {
    return 'rest api is running';
})->name('test');

Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::group([
    'middleware' => 'auth:sanctum'
], function () {
    Route::get('/products/options', [ProductController::class, 'options']);
    Route::apiResource('products', ProductController::class);

    Route::get('/categories/options', [CategoryController::class, 'options']);
    Route::apiResource('categories', CategoryController::class);

    Route::apiResource('users', UserController::class);

    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);
});
