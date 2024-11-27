<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\SupplierController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function () {

            Route::apiResource('attributes', AttributeController::class);
            Route::apiResource('products', ProductController::class);
            Route::apiResource('product-categories', ProductCategoryController::class);

            Route::prefix('suppliers')->group(function () {
                Route::get('/', [SupplierController::class, 'index']);
                Route::post('/', [SupplierController::class, 'store']);
                Route::get('/stats', [SupplierController::class, 'getStats']); // New stats endpoint
                Route::get('/trashed', [SupplierController::class, 'trashed']);
                Route::get('/{id}', [SupplierController::class, 'show']);
                Route::put('/{id}', [SupplierController::class, 'update']);
                Route::delete('/{id}', [SupplierController::class, 'destroy']);
                Route::post('/{id}/restore', [SupplierController::class, 'restore']);
            });

        });
    });
