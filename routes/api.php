<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ErrorLogController;
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
    Route::get('/products/by-code', [ProductController::class, 'findByCode']);
    Route::get('/products/check-code', [ProductController::class, 'checkCode']);
    Route::apiResource('products', ProductController::class);

    Route::get('/categories/options', [CategoryController::class, 'options']);
    Route::apiResource('categories', CategoryController::class);

    Route::apiResource('users', UserController::class);

    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index']);
    
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy']);

    Route::middleware('admin')->group(function () {
        Route::get('/audit-logs/summary', [AuditLogController::class, 'summary']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{auditLog}', [AuditLogController::class, 'show']);

        Route::get('/error-logs', [ErrorLogController::class, 'index']);
        Route::get('/error-logs/{errorLog}', [ErrorLogController::class, 'show']);
    });
});
