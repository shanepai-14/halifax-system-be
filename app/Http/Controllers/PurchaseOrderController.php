<?php

namespace App\Http\Controllers;

use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
                'items.*.attribute_id' => 'required|exists:attributes,id',
                'items.*.requested_quantity' => 'required|integer|min:1',
                'items.*.price' => 'required|numeric|min:0',
                // Add validation for additional costs
                'additional_costs' => 'nullable|array',
                'additional_costs.*.cost_type_id' => 'required|exists:additional_cost_types,cost_type_id',
                'additional_costs.*.amount' => 'required|numeric|min:0',
                'additional_costs.*.remarks' => 'nullable|string'
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
            'items.*.attribute_id' => 'required|exists:attributes,id',
            'items.*.requested_quantity' => 'required|integer|min:1',
            'items.*.received_quantity' => 'nullable|integer|min:0',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.retail_price' => 'nullable|numeric|min:0',
            'received_items' => 'nullable|array|min:0',
            'received_items.*.po_id' => 'nullable|exists:purchase_orders,po_id',
            'received_items.*.product_id' => 'nullable|exists:products,id',
            'received_items.*.attribute_id' => 'nullable|exists:attributes,id',
            'received_items.*.received_quantity' => 'nullable|numeric|min:1',
            'received_items.*.cost_price' => 'nullable|numeric|min:0',
            'received_items.*.walk_in_price' => 'nullable|numeric|min:0',
            'received_items.*.term_price' => 'nullable|numeric|min:0',
            'received_items.*.wholesale_price' => 'nullable|numeric|min:0',
            'received_items.*.regular_price' => 'nullable|numeric|min:0',
            'received_items.*.remarks' => 'nullable|string|max:255',
            'status' => 'required|string',
            'invoice' => 'nullable|string',
            'additional_costs' => 'nullable|array',
            'additional_costs.*.cost_type_id' => 'required|exists:additional_cost_types,cost_type_id',
            'additional_costs.*.amount' => 'required|numeric|min:0',
            'additional_costs.*.remarks' => 'nullable|string'
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

public function updateStatus(Request $request, String $poNumber)
{
    try {
        $validator = Validator::make($request->all(), [
            'status' => [
                'required',
                Rule::in([
                    PurchaseOrder::STATUS_PENDING,
                    PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                    PurchaseOrder::STATUS_COMPLETED,
                    PurchaseOrder::STATUS_CANCELLED
                ])
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $purchaseOrder = $this->purchaseOrderService->updatePurchaseOrderStatus(
            $poNumber, 
            $request->status
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order status updated successfully',
            'data' => $purchaseOrder
        ]);
    } catch (Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update purchase order status',
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