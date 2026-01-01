<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ObatController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\OrderControllerUser;

// Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Harus Login / Punya Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);

    // Obat - Semua user bisa lihat (index & show)
    Route::get('/obat', [ObatController::class, 'index']);
    Route::get('/obat/{obat}', [ObatController::class, 'show']);

    // Obat - Hanya admin yang bisa create, update, delete
    Route::middleware('admin')->group(function () {
        Route::post('/obat', [ObatController::class, 'store']);
        Route::put('/obat/{obat}', [ObatController::class, 'update']);
        Route::patch('/obat/{obat}', [ObatController::class, 'update']);
        Route::delete('/obat/{obat}', [ObatController::class, 'destroy']);
    });

    // Profile - User hanya bisa akses profile sendiri
    Route::apiResource('/profile', UserController::class);
    Route::put('/profile/{users_id}', [UserController::class, 'update']);
    Route::get('/orders', [OrderControllerUser::class, 'index']);
    Route::apiResource('/ordersPayment', OrderControllerUser::class);
});

// RUTE GAMBAR BNGSD
Route::get('/image/{path}', function ($path) {
    return response()->file(storage_path('app/public/' . $path));
})->where('path', '.*');
