<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ObatController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OrderController;

// Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Harus Login / Punya Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::apiResource('/obat', ObatController::class);
    Route::apiResource('/profile', UserController::class);
    
    // ========== ORDERS ==========
    // User endpoints
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/notify-payment', [OrderController::class, 'notifyPayment']);
    
    // Cashier endpoints
    Route::get('/cashier/orders', [OrderController::class, 'allOrders']);
    Route::get('/cashier/orders/pending-payments', [OrderController::class, 'pendingPayments']);
    Route::get('/cashier/orders/{id}', [OrderController::class, 'showForCashier']);
    Route::post('/cashier/orders/{id}/confirm-payment', [OrderController::class, 'confirmPayment']);
});

// RUTE GAMBAR BNGSD
Route::get('/image/{path}', function ($path) {
    return response()->file(storage_path('app/public/' . $path));
})->where('path', '.*');
