<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// Product routes (public — read only)
Route::get('products',      [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);

// Product routes (protected — requires Sanctum token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('products',          [ProductController::class, 'store']);
    Route::put('products/{id}',      [ProductController::class, 'update']);
    Route::delete('products/{id}',   [ProductController::class, 'destroy']);
});
