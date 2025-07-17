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
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PurposeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\BracketPricingController;
use App\Http\Controllers\ReportsController;




Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::middleware('role:admin')->group(function () {

            Route::prefix('users')->group(function () {
                Route::get('/', [UserController::class, 'index']);
                Route::post('/', [UserController::class, 'store']);
                Route::get('/stats', [UserController::class, 'getStats']);
                Route::get('/{id}', [UserController::class, 'show']);
                Route::put('/{id}', [UserController::class, 'update']);
                Route::delete('/{id}', [UserController::class, 'destroy']);
            });

        });

        Route::apiResource('purchase-order-costs', PurchaseOrderAdditionalCostController::class);
        Route::apiResource('additional-cost-types', AdditionalCostTypeController::class);
        Route::put('additional-cost-types/{id}/toggle-active', [AdditionalCostTypeController::class, 'toggleActive']);
       
        Route::apiResource('purposes', PurposeController::class);
        Route::apiResource('attributes', AttributeController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('product-categories', ProductCategoryController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::post('products/{id}/image', [ProductController::class, 'uploadImage']);




              Route::prefix('notifications')->group(function () {
                Route::get('/', [NotificationController::class, 'index']);
                Route::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
                Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
                Route::put('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
                Route::delete('/{id}', [NotificationController::class, 'destroy']);
            });

            Route::prefix('suppliers')->group(function () {
                Route::get('/', [SupplierController::class, 'index']);
                Route::post('/', [SupplierController::class, 'store']);
                Route::get('/stats', [SupplierController::class, 'getStats']); // New stats endpoint
                Route::get('/trashed', [SupplierController::class, 'trashed']);
                Route::get('/{id}', [SupplierController::class, 'show']);
                Route::put('/{id}', [SupplierController::class, 'update']);
                Route::delete('/{id}', [SupplierController::class, 'destroy']);
                Route::get('/{id}/purchase-history', [SupplierController::class, 'purchaseHistory']);
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
                Route::get('/summary-stats', [InventoryController::class, 'getSummaryStats']);

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
                Route::get('/customers/{customerId}/purchase-history', [SaleController::class, 'getCustomerPurchaseHistory']);
                Route::patch('/{id}/delivery-date', [SaleController::class, 'updateDeliveryDate']);
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
                Route::put('/{paymentId}/complete', [PaymentController::class, 'complete']);
            });

            Route::prefix('employees')->group(function () {
                Route::get('/', [EmployeeController::class, 'index']);
                Route::post('/', [EmployeeController::class, 'store']);
                Route::get('/stats', [EmployeeController::class, 'getStats']);
                Route::get('/trashed', [EmployeeController::class, 'trashed']);
                Route::get('/{id}', [EmployeeController::class, 'show']);
                Route::put('/{id}', [EmployeeController::class, 'update']);
                Route::delete('/{id}', [EmployeeController::class, 'destroy']);
                Route::post('/{id}/restore', [EmployeeController::class, 'restore']);
            });
        
            // Petty Cash routes
            Route::prefix('petty-cash')->group(function () {
                // General
                Route::get('/balance', [PettyCashController::class, 'getBalance']);
                Route::get('/stats', [PettyCashController::class, 'getStats']);
                
                // Funds
                Route::get('/funds', [PettyCashController::class, 'indexFunds']);
                Route::post('/funds', [PettyCashController::class, 'storeFund']);
                Route::put('/funds/{id}/approve', [PettyCashController::class, 'approveFund']);
                
                // Transactions
                Route::get('/transactions', [PettyCashController::class, 'indexTransactions']);
                Route::post('/transactions', [PettyCashController::class, 'storeTransaction']);
                Route::put('/transactions/{id}/settle', [PettyCashController::class, 'settleTransaction']);
                Route::put('/transactions/{id}/approve', [PettyCashController::class, 'approveTransaction']);
                Route::put('/transactions/{id}/cancel', [PettyCashController::class, 'cancelTransaction']);
                
                // Employee transactions
                Route::get('/employees/{employeeId}/transactions', [PettyCashController::class, 'getTransactionsByEmployee']);
            });

            Route::prefix('bracket-pricing')->group(function () {
            // Product-specific brackets
            Route::get('/products/{productId}/brackets', [BracketPricingController::class, 'getProductBrackets']);
            Route::get('/products/{productId}/active-bracket', [BracketPricingController::class, 'getActiveBracket']);
            Route::post('/products/{productId}/brackets', [BracketPricingController::class, 'store']);
            Route::delete('/products/{productId}/deactivate', [BracketPricingController::class, 'deactivate']);
            
            // Pricing calculations
            Route::get('/products/{productId}/breakdown', [BracketPricingController::class, 'getPricingBreakdown']);
            Route::post('/products/{productId}/calculate-price', [BracketPricingController::class, 'calculatePrice']);
            Route::get('/products/{productId}/suggestions', [BracketPricingController::class, 'getOptimalPricingSuggestions']);
            
            // Import functionality
            Route::post('/products/{productId}/import-csv', [BracketPricingController::class, 'importFromCsv']);
            
            // Bracket-specific operations
            Route::get('/brackets/{bracketId}', [BracketPricingController::class, 'show']);
            Route::put('/brackets/{bracketId}', [BracketPricingController::class, 'update']);
            Route::delete('/brackets/{bracketId}', [BracketPricingController::class, 'destroy']);
            Route::post('/brackets/{bracketId}/activate', [BracketPricingController::class, 'activate']);
            Route::post('/brackets/{bracketId}/clone', [BracketPricingController::class, 'clone']);
        });
        });

           Route::prefix('reports')->name('reports.')->group(function () {
        // Dashboard and overview
        Route::get('/dashboard', [ReportsController::class, 'dashboard'])->name('dashboard');
        Route::get('/chart-data', [ReportsController::class, 'getChartData'])->name('chart-data');
        
        // Period-based reports
        Route::get('/monthly', [ReportsController::class, 'getMonthlyReport'])->name('monthly');
        Route::get('/yearly', [ReportsController::class, 'getYearlyReport'])->name('yearly');
        Route::get('/daily', [ReportsController::class, 'getDailyReport'])->name('daily');
        
        // Breakdown reports
        Route::get('/payment-methods', [ReportsController::class, 'getPaymentMethodsBreakdown'])->name('payment-methods');
        Route::get('/customer-types', [ReportsController::class, 'getCustomerTypesBreakdown'])->name('customer-types');
        
        // Trend analysis
        Route::get('/profit-trends', [ReportsController::class, 'getProfitTrends'])->name('profit-trends');
        Route::get('/performance-metrics', [ReportsController::class, 'getPerformanceMetrics'])->name('performance-metrics');
        
        // Forecasting
        Route::get('/forecast', [ReportsController::class, 'getForecastData'])->name('forecast');
        
        // Advanced analytics
        Route::get('/advanced-analytics', [ReportsController::class, 'getAdvancedAnalytics'])->name('advanced-analytics');
        Route::get('/top-performing', [ReportsController::class, 'getTopPerformingPeriods'])->name('top-performing');
        Route::get('/summary-statistics', [ReportsController::class, 'getSummaryStatistics'])->name('summary-statistics');
        
        // Export functionality
        Route::get('/export', [ReportsController::class, 'exportSummaryData'])->name('export');
        
        // System maintenance (admin only)
        Route::middleware('role:admin')->group(function () {
            Route::post('/rebuild-summaries', [ReportsController::class, 'rebuildSummaries'])->name('rebuild-summaries');
            Route::get('/health-check', [ReportsController::class, 'getHealthCheck'])->name('health-check');
        });
    });

             Route::post('attachments/{attachmentId}', [AttachmentController::class, 'deleteAttachment']);

             Route::get('storage/{path}', function($path) {
                return response()->download(storage_path('app/public/' . $path));
            })->where('path', '.*');

       
    });
