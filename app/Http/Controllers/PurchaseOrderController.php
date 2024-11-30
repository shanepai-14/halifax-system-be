<?php

namespace App\Http\Controllers;

use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class PurchaseOrderController extends Controller
{
    protected $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Display a listing of purchase orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->status,
                'supplier_id' => $request->supplier_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $purchaseOrders = $this->purchaseOrderService->getAllPurchaseOrders(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $purchaseOrders,
                'message' => 'Purchase orders retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created purchase order
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_id' => 'required|exists:suppliers,supplier_id',
                'po_date' => 'required|date',
                'remarks' => 'nullable|string',
                'attachment' => 'nullable|string',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.requested_quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0'
            ]);

            $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($validated);

            return response()->json([
                'status' => 'success',
                'data' => $purchaseOrder,
                'message' => 'Purchase order created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase order
     */
    public function show(string $poNumber): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->getPurchaseOrderByPONumber($poNumber);

            return response()->json([
                'status' => 'success',
                'data' => $purchaseOrder,
                'message' => 'Purchase order retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Purchase order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified purchase order
     */
// Regular update without file
public function update(Request $request, string $poNumber): JsonResponse
{
    try {
        $validated = $request->validate([
            'supplier_id' => 'sometimes|exists:suppliers,supplier_id',
            'po_date' => 'sometimes|date',
            'remarks' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.po_item_id' => 'sometimes|exists:purchase_order_items,po_item_id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.requested_quantity' => 'required|integer|min:1',
            'items.*.received_quantity' => 'nullable|integer|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.retail_price' => 'nullable|numeric|min:0',
            'status' => 'required|string',
            'invoice' => 'nullable|string',
        ]);

        $purchaseOrder = $this->purchaseOrderService->updatePurchaseOrder($poNumber, $validated);

        return response()->json([
            'status' => 'success',
            'data' => $purchaseOrder,
            'message' => 'Purchase order updated successfully'
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error updating purchase order',
            'error' => $e->getMessage()
        ], 500);
    }
}

// Separate endpoint for file upload
        public function uploadAttachment(Request $request, string $poNumber): JsonResponse
        {
            try {
                $validated = $request->validate([
                    'attachment' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
                ]);

                $file = $request->file('attachment');
                $extension = $file->getClientOriginalExtension();
                $fileName = $poNumber . '.' . $extension;

                // Delete old file if exists
                $purchaseOrder = $this->purchaseOrderService->getPurchaseOrderByPONumber($poNumber);
                if ($purchaseOrder->attachment) {
                    Storage::disk('public')->delete($purchaseOrder->attachment);
                }

                // Store with custom filename
                $path = $file->storeAs('purchase-orders', $fileName, 'public');
                
                $purchaseOrder->update(['attachment' => $path]);

                return response()->json([
                    'status' => 'success',
                    'data' => ['attachment_path' => $path],
                    'message' => 'Attachment uploaded successfully'
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error uploading attachment',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
    /**
     * Update received quantities
     */
    public function updateReceived(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'items' => 'required|array|min:1',
                'items.*.po_item_id' => 'required|exists:purchase_order_items,po_item_id',
                'items.*.received_quantity' => 'required|integer|min:0'
            ]);

            $purchaseOrder = $this->purchaseOrderService->updateReceivedQuantities($id, $validated['items']);

            return response()->json([
                'status' => 'success',
                'data' => $purchaseOrder,
                'message' => 'Received quantities updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating received quantities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel purchase order
     */
    public function cancel(string $id): JsonResponse
    {
        try {
            $purchaseOrder = $this->purchaseOrderService->cancelPurchaseOrder($id);

            return response()->json([
                'status' => 'success',
                'data' => $purchaseOrder,
                'message' => 'Purchase order cancelled successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cancelling purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get purchase order statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->purchaseOrderService->getPurchaseOrderStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Purchase order statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving purchase order statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}