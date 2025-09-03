<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TransferService;
use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class TransferController extends Controller
{
    protected $transferService;

    public function __construct(TransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    /**
     * Get all transfers with filters and pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'warehouse_id' => $request->input('warehouse_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'search' => $request->input('search'),
                'per_page' => $request->input('per_page', 20)
            ];

            $transfers = $this->transferService->getTransfers($filters);

            return response()->json([
                'success' => true,
                'data' => $transfers,
                'message' => 'Transfers retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single transfer with details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $transfer = $this->transferService->getTransferById($id);

            return response()->json([
                'success' => true,
                'data' => $transfer,
                'message' => 'Transfer retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transfer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new transfer
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'to_warehouse_id' => 'required|exists:warehouses,id',
                'delivery_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.01|max:999999.99',
                'items.*.notes' => 'nullable|string|max:500'
            ], [
                'to_warehouse_id.required' => 'Destination warehouse is required',
                'to_warehouse_id.exists' => 'Selected warehouse does not exist',
                'delivery_date.after_or_equal' => 'Delivery date cannot be in the past',
                'items.required' => 'At least one item is required',
                'items.min' => 'At least one item is required',
                'items.*.product_id.required' => 'Product is required for each item',
                'items.*.product_id.exists' => 'One or more selected products do not exist',
                'items.*.quantity.required' => 'Quantity is required for each item',
                'items.*.quantity.min' => 'Quantity must be greater than 0',
                'items.*.quantity.max' => 'Quantity cannot exceed 999,999.99'
            ]);

            $transfer = $this->transferService->createTransfer($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Transfer created successfully. Inventory has been adjusted.',
                'data' => $transfer
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update transfer details (only for in_transit transfers)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
             $validatedData = $request->validate([
                'to_warehouse_id' => 'required|exists:warehouses,id',
                'delivery_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|numeric|min:0.01|max:999999.99',
                'items.*.notes' => 'nullable|string|max:500'
            ], [
                'to_warehouse_id.required' => 'Destination warehouse is required',
                'to_warehouse_id.exists' => 'Selected warehouse does not exist',
                'delivery_date.after_or_equal' => 'Delivery date cannot be in the past',
                'items.required' => 'At least one item is required',
                'items.min' => 'At least one item is required',
                'items.*.product_id.required' => 'Product is required for each item',
                'items.*.product_id.exists' => 'One or more selected products do not exist',
                'items.*.quantity.required' => 'Quantity is required for each item',
                'items.*.quantity.min' => 'Quantity must be greater than 0',
                'items.*.quantity.max' => 'Quantity cannot exceed 999,999.99'
            ]);

            $transfer = $this->transferService->updateTransfer($id, $validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Transfer updated successfully',
                'data' => $transfer
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update transfer status (complete or cancel)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'status' => 'required|in:completed,cancelled',
                'delivery_date' => 'nullable|date',
                'reason' => 'required_if:status,cancelled|max:500'
            ], [
                'status.required' => 'Status is required',
                'status.in' => 'Invalid status. Only completed or cancelled are allowed',
                'reason.required_if' => 'Cancellation reason is required when cancelling a transfer'
            ]);

            $transfer = $this->transferService->updateTransferStatus(
                $id, 
                $validatedData['status'], 
                $validatedData
            );

            $message = $validatedData['status'] === 'completed' 
                ? 'Transfer marked as completed successfully'
                : 'Transfer cancelled and inventory restored successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $transfer
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transfer status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete transfer (soft delete with inventory restoration if needed)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->transferService->deleteTransfer($id);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transfer deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transfer'
            ], 500);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfer statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date')
            ];

            $stats = $this->transferService->getTransferStats($filters);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfer statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses for dropdown selection
     *
     * @return JsonResponse
     */
    public function warehouses(): JsonResponse
    {
        try {
            $warehouses = Warehouse::active()
                ->select('id', 'name', 'code', 'location')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses,
                'message' => 'Warehouses retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfer history for a specific product
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function productTransferHistory(Request $request, int $productId): JsonResponse
    {
        try {
            $transfers = Transfer::with(['warehouse', 'creator'])
                ->whereHas('items', function ($query) use ($productId) {
                    $query->where('product_id', $productId);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => $transfers,
                'message' => 'Product transfer history retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product transfer history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfer items for a specific transfer
     *
     * @param int $transferId
     * @return JsonResponse
     */
    public function transferItems(int $transferId): JsonResponse
    {
        try {
            $transfer = Transfer::with(['items.product.category'])
                ->findOrFail($transferId);

            return response()->json([
                'success' => true,
                'data' => [
                    'transfer' => $transfer,
                    'items' => $transfer->items
                ],
                'message' => 'Transfer items retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfer items',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transfers by warehouse
     *
     * @param Request $request
     * @param int $warehouseId
     * @return JsonResponse
     */
    public function warehouseTransfers(Request $request, int $warehouseId): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($warehouseId);

            $transfers = Transfer::with(['creator', 'items.product'])
                ->where('to_warehouse_id', $warehouseId)
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse' => $warehouse,
                    'transfers' => $transfers
                ],
                'message' => 'Warehouse transfers retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse transfers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk update transfer status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'transfer_ids' => 'required|array|min:1',
                'transfer_ids.*' => 'required|exists:transfers,id',
                'status' => 'required|in:completed,cancelled',
                'reason' => 'required_if:status,cancelled|string|max:500'
            ]);

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($validatedData['transfer_ids'] as $transferId) {
                try {
                    $transfer = $this->transferService->updateTransferStatus(
                        $transferId,
                        $validatedData['status'],
                        $validatedData
                    );
                    $results[$transferId] = [
                        'success' => true,
                        'transfer' => $transfer
                    ];
                    $successCount++;
                } catch (Exception $e) {
                    $results[$transferId] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => $errorCount === 0,
                'message' => "Bulk update completed. Success: {$successCount}, Errors: {$errorCount}",
                'data' => $results,
                'summary' => [
                    'total' => count($validatedData['transfer_ids']),
                    'success' => $successCount,
                    'errors' => $errorCount
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to perform bulk update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export transfers to CSV/Excel
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'warehouse_id' => $request->input('warehouse_id'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'search' => $request->input('search')
            ];

            // Get all transfers without pagination for export
            $transfers = Transfer::with(['warehouse', 'creator', 'items.product'])
                ->when($filters['status'], function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->when($filters['warehouse_id'], function ($query, $warehouseId) {
                    return $query->where('to_warehouse_id', $warehouseId);
                })
                ->when($filters['start_date'] && $filters['end_date'], function ($query) use ($filters) {
                    return $query->whereBetween('delivery_date', [$filters['start_date'], $filters['end_date']]);
                })
                ->when($filters['search'], function ($query, $search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('transfer_number', 'like', '%' . $search . '%')
                          ->orWhereHas('warehouse', function ($wq) use ($search) {
                              $wq->where('name', 'like', '%' . $search . '%');
                          });
                    });
                })
                ->orderBy('created_at', 'desc')
                ->get();

            // Format data for export
            $exportData = $transfers->map(function ($transfer) {
                return [
                    'Transfer Number' => $transfer->transfer_number,
                    'Destination Warehouse' => $transfer->warehouse->name,
                    'Location' => $transfer->warehouse->location,
                    'Status' => $transfer->status_label,
                    'Total Items' => $transfer->total_items,
                    'Total Quantity' => $transfer->total_quantity,
                    'Total Value' => $transfer->total_value,
                    'Delivery Date' => $transfer->delivery_date?->format('Y-m-d'),
                    'Created By' => $transfer->creator->name,
                    'Created At' => $transfer->created_at->format('Y-m-d H:i:s'),
                    'Notes' => $transfer->notes
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $exportData,
                'message' => 'Export data prepared successfully',
                'count' => $exportData->count()
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare export data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}