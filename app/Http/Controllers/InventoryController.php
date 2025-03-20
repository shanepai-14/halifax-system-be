<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get all inventory records
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'category_id' => $request->category_id,
                'status' => $request->status,
                'search' => $request->search
            ];

            $inventory = $this->inventoryService->getAllInventory($filters);

            return response()->json([
                'status' => 'success',
                'data' => $inventory,
                'message' => 'Inventory data retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function salesInventory(Request $request): JsonResponse
    {
        try {
           
            $inventory = $this->inventoryService->getAllInventorySales();

            return response()->json([
                'status' => 'success',
                'data' => $inventory,
                'message' => 'Inventory data retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory data',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function show( $productId ): JsonResponse
    {

        $productId = (int)$productId;

        try {
            $inventory = $this->inventoryService->getProductInventory( $productId );

            if (!$inventory) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Inventory record not found for this product'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $inventory,
                'message' => 'Product inventory retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product inventory',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create an inventory adjustment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createAdjustment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|exists:products,id',
                'adjustment_type' => 'required|string',
                'quantity' => 'required|numeric|min:0.01',
                'reason' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            $adjustment = $this->inventoryService->createAdjustment($validated);

            return response()->json([
                'status' => 'success',
                'data' => $adjustment,
                'message' => 'Inventory adjustment created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating inventory adjustment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all inventory adjustments
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAdjustments(Request $request): JsonResponse
    {
        try {
            $filters = [
                'product_id' => $request->product_id,
                'adjustment_type' => $request->adjustment_type,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'search' => $request->search
            ];

            $adjustments = $this->inventoryService->getAdjustments($filters);

            return response()->json([
                'status' => 'success',
                'data' => $adjustments,
                'message' => 'Inventory adjustments retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory adjustments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get adjustments for a specific product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function getProductAdjustments(int $productId): JsonResponse
    {
        $productId = (int)$productId;

        try {
            $adjustments = $this->inventoryService->getProductAdjustments($productId);

            return response()->json([
                'status' => 'success',
                'data' => $adjustments,
                'message' => 'Product adjustments retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product adjustments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all inventory logs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getLogs(Request $request): JsonResponse
    {
        try {
            $filters = [
                'product_id' => $request->product_id,
                'transaction_type' => $request->transaction_type,
                'reference_type' => $request->reference_type,
                'reference_id' => $request->reference_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ];

            $logs = $this->inventoryService->getInventoryLogs($filters);

            return response()->json([
                'status' => 'success',
                'data' => $logs,
                'message' => 'Inventory logs retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get logs for a specific product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function getProductLogs(Int $productId): JsonResponse
    {
        $productId = (int)$productId;

        try {
            $logs = $this->inventoryService->getProductInventoryLogs($productId);

            return response()->json([
                'status' => 'success',
                'data' => $logs,
                'message' => 'Product inventory logs retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product inventory logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions for a specific product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function getProductTransactions(int $productId): JsonResponse
    {
        $productId = (int)$productId;
        
        try {
            $transactions = $this->inventoryService->getProductTransactions($productId);

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'message' => 'Product transactions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProductReport(int $productId): JsonResponse
    {
        $productId = (int)$productId;
        
        try {
            $transactions = $this->inventoryService->getReceivingReportsForProduct($productId);

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'message' => 'Product report retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory warnings
     *
     * @return JsonResponse
     */
    public function getWarnings(): JsonResponse
    {
        try {
            $warnings = $this->inventoryService->getInventoryWarnings();

            return response()->json([
                'status' => 'success',
                'data' => $warnings,
                'message' => 'Inventory warnings retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory warnings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}