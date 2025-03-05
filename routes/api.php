<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseOrderAdditionalCostController;
use App\Http\Controllers\AdditionalCostTypeController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\ReceivingReportController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryCountController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function () {

            Route::apiResource('purchase-order-costs', PurchaseOrderAdditionalCostController::class);
            Route::apiResource('additional-cost-types', AdditionalCostTypeController::class);
            Route::put('additional-cost-types/{id}/toggle-active', [AdditionalCostTypeController::class, 'toggleActive']);

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
                Route::put('/{poNumber}/status', [PurchaseOrderController::class, 'updateStatus']);
                Route::post('/receiving-reports', [PurchaseOrderController::class, 'createReceivingReport']);
                Route::put('/{id}/receiving-reports', [PurchaseOrderController::class, 'updateReceivingReport']);
                Route::post('/{poNumber}/attachments', [AttachmentController::class, 'uploadPOAttachment']);
                Route::get('/{poNumber}/attachments', [AttachmentController::class, 'getPOAttachments']);

                
            });

            Route::prefix('receiving-reports')->group(function () {
                Route::get('/', [ReceivingReportController::class, 'index']);
                Route::get('/stats', [ReceivingReportController::class, 'getStats']);
                Route::get('/{id}', [ReceivingReportController::class, 'show']);
                Route::put('/{id}', [ReceivingReportController::class, 'update']);
                Route::put('/{id}/payment-status', [ReceivingReportController::class, 'updatePaymentStatus']);
                Route::delete('/{id}', [ReceivingReportController::class, 'destroy']);
                Route::post('/{rr_id}/attachments', [AttachmentController::class, 'uploadRRAttachment']);
            });

            Route::prefix('inventory')->group(function () {
                // Main inventory routes
                Route::get('/', [InventoryController::class, 'index']);
                Route::get('/product/{productId}', [InventoryController::class, 'show']);
                
                // Adjustments
                Route::post('/adjustments', [InventoryController::class, 'createAdjustment']);
                Route::get('/adjustments', [InventoryController::class, 'getAdjustments']);
                Route::get('/adjustments/product/{productId}', [InventoryController::class, 'getProductAdjustments']);
                
                // Logs
                Route::get('/logs', [InventoryController::class, 'getLogs']);
                Route::get('/logs/product/{productId}', [InventoryController::class, 'getProductLogs']);
                
                // Transactions
                Route::get('/transactions/product/{productId}', [InventoryController::class, 'getProductTransactions']);
                
                // Warnings
                Route::get('/warnings', [InventoryController::class, 'getWarnings']);
                
                // Inventory counts
                Route::prefix('counts')->group(function () {
                    Route::get('/', [InventoryCountController::class, 'index']);
                    Route::post('/', [InventoryCountController::class, 'store']);
                    Route::get('/{id}', [InventoryCountController::class, 'show']);
                    Route::put('/{id}', [InventoryCountController::class, 'update']);
                    Route::post('/{id}/finalize', [InventoryCountController::class, 'finalize']);
                    Route::post('/{id}/cancel', [InventoryCountController::class, 'cancel']);
                });
            });

             Route::post('attachments/{attachmentId}', [AttachmentController::class, 'deleteAttachment']);

             Route::get('storage/{path}', function($path) {
                return response()->download(storage_path('app/public/' . $path));
            })->where('path', '.*');

        });
    });
