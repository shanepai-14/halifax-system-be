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
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SaleReturnController;
use App\Http\Controllers\PaymentController;







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

        });

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

            Route::prefix('product-prices')->group(function () {
                Route::get('/', [ProductPriceController::class, 'index']);
                Route::post('/', [ProductPriceController::class, 'store']);
                Route::get('/stats', [ProductPriceController::class, 'getStats']);
                Route::get('/trashed', [ProductPriceController::class, 'trashed']);
                Route::get('/by-product/{productId}', [ProductPriceController::class, 'priceHistory']);
                Route::get('/current/{productId}', [ProductPriceController::class, 'getCurrentPrice']);
                Route::post('/update-from-po/{productId}', [ProductPriceController::class, 'updateFromPurchaseOrder']);
                Route::post('/bulk-update', [ProductPriceController::class, 'bulkUpdate']);
                Route::get('/{id}', [ProductPriceController::class, 'show']);
                Route::put('/{id}', [ProductPriceController::class, 'update']);
                Route::post('/{id}/set-active', [ProductPriceController::class, 'setActive']);
                Route::delete('/{id}', [ProductPriceController::class, 'destroy']);
                Route::post('/{id}/restore', [ProductPriceController::class, 'restore']);
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
                Route::get('/', [InventoryController::class, 'index']);
                Route::get('/sales', [InventoryController::class, 'salesInventory']);
                Route::get('/product/{productId}', [InventoryController::class, 'show']);
                Route::post('/adjustments', [InventoryController::class, 'createAdjustment']);
                Route::get('/adjustments', [InventoryController::class, 'getAdjustments']);
                Route::get('/adjustments/product/{productId}', [InventoryController::class, 'getProductAdjustments']);
                Route::get('/logs', [InventoryController::class, 'getLogs']);
                Route::get('/logs/product/{productId}', [InventoryController::class, 'getProductLogs']);
                Route::get('/transactions/product/{productId}', [InventoryController::class, 'getProductTransactions']);
                Route::get('/report/product/{productId}', [InventoryController::class, 'getProductReport']);
                Route::get('/warnings', [InventoryController::class, 'getWarnings']);

                Route::prefix('counts')->group(function () {
                    Route::get('/', [InventoryCountController::class, 'index']);
                    Route::post('/', [InventoryCountController::class, 'store']);
                    Route::get('/{id}', [InventoryCountController::class, 'show']);
                    Route::put('/{id}', [InventoryCountController::class, 'update']);
                    Route::post('/{id}/finalize', [InventoryCountController::class, 'finalize']);
                    Route::post('/{id}/cancel', [InventoryCountController::class, 'cancel']);
                });

            });

            Route::prefix('customers')->group(function () {
                Route::get('/', [CustomerController::class, 'index']);
                Route::post('/', [CustomerController::class, 'store']);
                Route::get('/stats', [CustomerController::class, 'getStats']);
                Route::get('/trashed', [CustomerController::class, 'trashed']);
                Route::get('/{id}', [CustomerController::class, 'show']);
                Route::put('/{id}', [CustomerController::class, 'update']);
                Route::delete('/{id}', [CustomerController::class, 'destroy']);
                Route::post('/{id}/restore', [CustomerController::class, 'restore']);
            });


            Route::prefix('sales')->group(function () {
                Route::get('/', [SaleController::class, 'index']);
                Route::post('/', [SaleController::class, 'store']);
                Route::get('/stats', [SaleController::class, 'getStats']);
                Route::get('/invoice/{invoiceNumber}', [SaleController::class, 'getByInvoiceNumber']);
                Route::get('/{id}', [SaleController::class, 'show']);
                Route::put('/{id}', [SaleController::class, 'update']);
                Route::put('/{id}/payment', [SaleController::class, 'updatePayment']);
                Route::put('/{id}/cancel', [SaleController::class, 'cancel']);
                Route::put('/{id}/deliver', [SaleController::class, 'markAsDelivered']);
            });
            
            // Sale returns routes
            Route::prefix('sale-returns')->group(function () {
                Route::get('/', [SaleReturnController::class, 'index']);
                Route::post('/', [SaleReturnController::class, 'store']);
                Route::get('/stats', [SaleReturnController::class, 'getStats']);
                Route::get('/credit-memo/{creditMemoNumber}', [SaleReturnController::class, 'getByCreditMemoNumber']);
                Route::get('/{id}', [SaleReturnController::class, 'show']);
                Route::put('/{id}', [SaleReturnController::class, 'update']);
                Route::put('/{id}/approve', [SaleReturnController::class, 'approve']);
                Route::put('/{id}/reject', [SaleReturnController::class, 'reject']);
                Route::put('/{id}/complete', [SaleReturnController::class, 'complete']);
            });
            
            Route::middleware('role:admin,cashier')->group(function () {

            Route::prefix('payments')->group(function () {
                Route::get('/', [PaymentController::class, 'index']);
                Route::post('/{saleId}', [PaymentController::class, 'store']);
                Route::get('/{saleId}/history', [PaymentController::class, 'history']);
                Route::put('/{paymentId}/void', [PaymentController::class, 'void']);
                Route::get('/{paymentId}/receipt', [PaymentController::class, 'receipt']);
                Route::get('/stats', [PaymentController::class, 'getStats']);
            });
        });

             Route::post('attachments/{attachmentId}', [AttachmentController::class, 'deleteAttachment']);

             Route::get('storage/{path}', function($path) {
                return response()->download(storage_path('app/public/' . $path));
            })->where('path', '.*');

       
    });
