<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function () {

            Route::apiResource('attributes', AttributeController::class);
            Route::apiResource('products', ProductController::class);
            Route::apiResource('product-categories', ProductCategoryController::class);
            Route::post('products/{id}/image', [ProductController::class, 'uploadImage']);

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

            Route::prefix('purchase-orders')->group(function () {
                Route::get('/', [PurchaseOrderController::class, 'index']);
                Route::post('/', [PurchaseOrderController::class, 'store']);
                Route::get('/stats', [PurchaseOrderController::class, 'getStats']);
                Route::get('/{poNumber}', [PurchaseOrderController::class, 'show']);
                Route::put('/{poNumber}', [PurchaseOrderController::class, 'update']);
                Route::put('/{id}/received', [PurchaseOrderController::class, 'updateReceived']);
                Route::put('/{id}/cancel', [PurchaseOrderController::class, 'cancel']);
                Route::post('/{poNumber}/attachment', [PurchaseOrderController::class, 'uploadAttachment']);
            });





        });
    });
