<?php

namespace App\Http\Controllers;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class InventoryCountController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get all inventory counts
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->status,
                'search' => $request->search,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date
            ];

            $counts = $this->inventoryService->getInventoryCounts($filters);

            return response()->json([
                'status' => 'success',
                'data' => $counts,
                'message' => 'Inventory counts retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory counts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new inventory count
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'description' => 'nullable|string',
                'items' => 'required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.counted_quantity' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string'
            ]);

            $count = $this->inventoryService->createInventoryCount($validated);

            return response()->json([
                'status' => 'success',
                'data' => $count,
                'message' => 'Inventory count created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific inventory count
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $count = $this->inventoryService->getInventoryCount($id);

            return response()->json([
                'status' => 'success',
                'data' => $count,
                'message' => 'Inventory count retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an inventory count
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string',
                'items' => 'sometimes|required|array',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.counted_quantity' => 'required|numeric|min:0',
                'items.*.notes' => 'nullable|string'
            ]);

            $count = $this->inventoryService->updateInventoryCount($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $count,
                'message' => 'Inventory count updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize an inventory count
     *
     * @param string $id
     * @return JsonResponse
     */
    public function finalize(string $id): JsonResponse
    {
        try {
            $count = $this->inventoryService->finalizeInventoryCount($id);

            return response()->json([
                'status' => 'success',
                'data' => $count,
                'message' => 'Inventory count finalized successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error finalizing inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an inventory count
     *
     * @param string $id
     * @return JsonResponse
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $count = $this->inventoryService->cancelInventoryCount($id);

            return response()->json([
                'status' => 'success',
                'data' => $count,
                'message' => 'Inventory count cancelled successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cancelling inventory count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}