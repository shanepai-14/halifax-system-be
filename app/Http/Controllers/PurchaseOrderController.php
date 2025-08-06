<?php

namespace App\Http\Controllers;

use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\PurchaseOrder;
use App\Models\ReceivingReport;
use App\Models\PurchaseOrderReceivedItem;
use App\Models\PurchaseOrderAdditionalCost;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
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

    // public function createReceivingReport(Request $request): ReceivingReport
    // {
    //     try {
    //         DB::beginTransaction();
            
    //         $poId = $request->po_id;
    //         $data = $request->all();
            
    //         // Find the purchase order
    //         $purchaseOrder = PurchaseOrder::findOrFail($poId);
            
    //         // Cannot create receiving report for cancelled or completed POs
    //         if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
    //             throw new Exception('Cannot create receiving report for a cancelled purchase order');
    //         }
            
    //         if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
    //             throw new Exception('Cannot create receiving report for a completed purchase order');
    //         }
            
    //         // Create the receiving report
    //         $receivingReport = new ReceivingReport([
    //             'po_id' => $poId,
    //             'invoice' => $data['invoice'],
    //             'term' => $data['term'] ?? 0,
    //         ]);
            
    //         // Let the model auto-generate batch number during save
    //         $receivingReport->save();
            
    //         // Process received items
    //         $totalReceivedAmount = 0;
    //         Log::info($receivingReport);
    //         foreach ($data['received_items'] as $itemData) {
    //             $receivedItem = new PurchaseOrderReceivedItem([
    //                 'rr_id' => $receivingReport->rr_id,
    //                 'product_id' => $itemData['product_id'],
    //                 'attribute_id' => $itemData['attribute_id'] ?? null,
    //                 'received_quantity' => $itemData['received_quantity'],
    //                 'cost_price' => $itemData['cost_price'],
    //                 'walk_in_price' => $itemData['walk_in_price'],
    //                 'term_price' => $itemData['term_price'] ?? 0,
    //                 'wholesale_price' => $itemData['wholesale_price'],
    //                 'regular_price' => $itemData['regular_price'],
    //                 'remarks' => $itemData['remarks'] ?? null,
    //             ]);
                
    //             $receivingReport->received_items()->save($receivedItem);
                
    //             $totalReceivedAmount += $receivedItem->cost_price * $receivedItem->received_quantity;
    //         }
            
    //         // Process additional costs if any
    //         if (!empty($data['additional_costs'])) {
    //             foreach ($data['additional_costs'] as $costData) {
    //                 $additionalCost = new PurchaseOrderAdditionalCost([
    //                     'rr_id' => $receivingReport->rr_id,
    //                     'cost_type_id' => $costData['cost_type_id'],
    //                     'amount' => $costData['amount'],
    //                     'remarks' => $costData['remarks'] ?? null,
    //                 ]);
                    
    //                 $receivingReport->additionalCosts()->save($additionalCost);
    //             }
    //         }
            
    //         // Update purchase order status
    //         $purchaseOrder->status = PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
    //         $purchaseOrder->updateStatus(); // This will set to COMPLETED if all items are fully received
            
    //         DB::commit();
            
    //         // Load relationships for the response
    //         return $receivingReport->load([
    //             'received_items.product',
    //             'received_items.attribute',
    //             'additionalCosts.costType'
    //         ]);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw new Exception('Failed to create receiving report: ' . $e->getMessage());
    //     }
    // }

    public function createReceivingReport(Request $request): ReceivingReport
{
    try {
        DB::beginTransaction();
        
        $poId = $request->po_id;
        $data = $request->all();
        
        // Find the purchase order
        $purchaseOrder = PurchaseOrder::findOrFail($poId);
        
        // Cannot create receiving report for cancelled or completed POs
        if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new Exception('Cannot create receiving report for a cancelled purchase order');
        }
        
        if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
            throw new Exception('Cannot create receiving report for a completed purchase order');
        }
        
        // Create the receiving report
        $receivingReport = new ReceivingReport([
            'po_id' => $poId,
            'invoice' => $data['invoice'],
            'term' => $data['term'] ?? 0,
            'is_paid' => $data['is_paid'] ?? false,
        ]);
        
        // Let the model auto-generate batch number during save
        $receivingReport->save();
        
        // Process received items
        $totalReceivedAmount = 0;
        
        foreach ($data['received_items'] as $itemData) {
            $receivedItem = new PurchaseOrderReceivedItem([
                'rr_id' => $receivingReport->rr_id,
                'product_id' => $itemData['product_id'],
                'attribute_id' => $itemData['attribute_id'] ?? null,
                'received_quantity' => $itemData['received_quantity'],
                'cost_price' => $itemData['cost_price'],
                'distribution_price' => $itemData['distribution_price'],
                'walk_in_price' => $itemData['walk_in_price'],
                'term_price' => $itemData['term_price'] ?? 0,
                'wholesale_price' => $itemData['wholesale_price'] ?? $itemData['walk_in_price'],
                'regular_price' => $itemData['regular_price'] ?? $itemData['walk_in_price'],
                'remarks' => $itemData['remarks'] ?? null,
                
            ]);
            
            $receivingReport->received_items()->save($receivedItem);
            
            // Update inventory immediately for this received item
            $this->updateProductQuantity($receivedItem);
            
            $totalReceivedAmount += $receivedItem->cost_price * $receivedItem->received_quantity;
        }
        
        // Process additional costs if any
        if (!empty($data['additional_costs'])) {
            foreach ($data['additional_costs'] as $costData) {
                $additionalCost = new PurchaseOrderAdditionalCost([
                    'rr_id' => $receivingReport->rr_id,
                    'cost_type_id' => $costData['cost_type_id'],
                    'amount' => $costData['amount'],
                    'remarks' => $costData['remarks'] ?? null,
                ]);
                
                $receivingReport->additionalCosts()->save($additionalCost);
            }
        }
        
        // Update purchase order status
        $purchaseOrder->status = PurchaseOrder::STATUS_PARTIALLY_RECEIVED;
        $purchaseOrder->updateStatus(); // This will set to COMPLETED if all items are fully received
        $receivingReport->refreshTotals();
        DB::commit();
        
        // Load relationships for the response
        return $receivingReport->load([
            'received_items.product',
            'received_items.attribute',
            'additionalCosts.costType'
        ]);
    } catch (Exception $e) {
        DB::rollBack();
        throw new Exception('Failed to create receiving report: ' . $e->getMessage());
    }
}

/**
 * Update product quantity and ensure inventory record exists
 *
 * @param PurchaseOrderReceivedItem $receivedItem
 * @return void
 */
protected function updateProductQuantity(PurchaseOrderReceivedItem $receivedItem): void
{
    try {
        // Find or create an inventory record for this product
        $inventory = Inventory::firstOrCreate(
            ['product_id' => $receivedItem->product_id],
            [
                'quantity' => 0,  // Initialize with 0 for new records
                'avg_cost_price' => $receivedItem->cost_price,
                'last_received_at' => now(),
                'recount_needed' => false
            ]
        );
        
        // Store the current quantity for logging
        $quantityBefore = $inventory->quantity;
        
        // Update inventory using the built-in method that handles average cost calculation
        $inventory->incrementQuantity($receivedItem->received_quantity, $receivedItem->cost_price);
        
        // Calculate new quantity for logging
        $quantityAfter = $inventory->quantity;
        
        // Create log entry for tracking
        InventoryLog::create([
            'product_id' => $receivedItem->product_id,
            'user_id' => Auth::id(), 
            'transaction_type' => 'purchase',
            'reference_type' => 'receiving_report',
            'reference_id' => $receivedItem->rr_id,
            'quantity' => $receivedItem->received_quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'cost_price' => $receivedItem->cost_price,
            'notes' => "Received from receiving report #{$receivedItem->rr_id}"
        ]);
        
        Log::info('Inventory updated successfully', [
            'product_id' => $receivedItem->product_id,
            'quantity_before' => $quantityBefore,
            'added_quantity' => $receivedItem->received_quantity,
            'new_quantity' => $quantityAfter,
            'inventory_created' => $inventory->wasRecentlyCreated
        ]);
        
    } catch (Exception $e) {
        Log::error('Failed to update inventory for received item', [
            'error' => $e->getMessage(),
            'product_id' => $receivedItem->product_id,
            'quantity' => $receivedItem->received_quantity
        ]);
        
        throw $e;
    }
}

/**
 * Update an existing receiving report
 * 
 * @param int $rrId The ID of the receiving report to update
 * @param Request $request
 * @return ReceivingReport
 * @throws Exception
 */
public function updateReceivingReport(int $id, Request $request): ReceivingReport
{
    try {
        DB::beginTransaction();
        
        $data = $request->all();
        
        // Find the receiving report
        $receivingReport = ReceivingReport::with([
            'received_items',
            'additionalCosts'
        ])->findOrFail($id);
        
        // Find the purchase order
        $purchaseOrder = PurchaseOrder::findOrFail($receivingReport->po_id);
        
        // Cannot update receiving report for cancelled or completed POs
        if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
            throw new Exception('Cannot update receiving report for a cancelled purchase order');
        }
        
        // if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
        //     throw new Exception('Cannot update receiving report for a completed purchase order');
        // }
        
        // Update the receiving report basic info
        $receivingReport->update([
            'invoice' => $data['invoice'],
            'term' => $data['term'] ?? 0,
            'is_paid' => $data['is_paid'] ?? false,
        ]);
        
        // Track existing item IDs to determine which ones to keep, update, or delete
        $existingItemIds = $receivingReport->received_items->pluck('received_item_id')->toArray();
        $updatedItemIds = [];
        
        // Process received items - update existing or create new ones
        foreach ($data['received_items'] as $itemData) {
            // Check if this item has an ID (existing item)
            if (isset($itemData['received_item_id']) && in_array($itemData['received_item_id'], $existingItemIds)) {
                // Update existing item
                $receivedItem = PurchaseOrderReceivedItem::findOrFail($itemData['received_item_id']);
                $receivedItem->update([
                    'product_id' => $itemData['product_id'],
                    'attribute_id' => $itemData['attribute_id'] ?? null,
                    'received_quantity' => $itemData['received_quantity'],
                    'cost_price' => $itemData['cost_price'],
                    'distribution_price' => $itemData['distribution_price'],
                    'walk_in_price' => $itemData['walk_in_price'],
                    'term_price' => $itemData['term_price'] ?? 0,
                    'wholesale_price' => $itemData['wholesale_price'] ?? $itemData['walk_in_price'],
                    'regular_price' => $itemData['regular_price'] ?? $itemData['walk_in_price'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
                
                $updatedItemIds[] = $itemData['received_item_id'];
            } else {
                // Create new item
                $receivedItem = new PurchaseOrderReceivedItem([
                    'rr_id' => $receivingReport->rr_id,
                    'product_id' => $itemData['product_id'],
                    'attribute_id' => $itemData['attribute_id'] ?? null,
                    'received_quantity' => $itemData['received_quantity'],
                    'cost_price' => $itemData['cost_price'],
                    'walk_in_price' => $itemData['walk_in_price'],
                    'term_price' => $itemData['term_price'] ?? 0,
                    'wholesale_price' => $itemData['wholesale_price'] ?? $itemData['walk_in_price'],
                    'regular_price' => $itemData['regular_price'] ?? $itemData['walk_in_price'],
                    'remarks' => $itemData['remarks'] ?? null,
                ]);
                
                $receivingReport->received_items()->save($receivedItem);
                $updatedItemIds[] = $receivedItem->received_item_id;
            }
        }
        
        // Delete items that weren't included in the update
        $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
        if (!empty($itemsToDelete)) {
            PurchaseOrderReceivedItem::whereIn('received_item_id', $itemsToDelete)->delete();
        }
        
        // Track existing additional cost IDs
        $existingCostIds = $receivingReport->additionalCosts->pluck('po_cost_id')->toArray();
        $updatedCostIds = [];
        
        // Process additional costs - update existing or create new ones
        if (!empty($data['additional_costs'])) {
            foreach ($data['additional_costs'] as $costData) {
                // Check if this cost has an ID (existing cost)
                if (isset($costData['po_cost_id']) && in_array($costData['po_cost_id'], $existingCostIds)) {
                    // Update existing cost
                    $additionalCost = PurchaseOrderAdditionalCost::findOrFail($costData['po_cost_id']);
                    $additionalCost->update([
                        'cost_type_id' => $costData['cost_type_id'],
                        'amount' => $costData['amount'],
                        'remarks' => $costData['remarks'] ?? null,
                    ]);
                    
                    $updatedCostIds[] = $costData['po_cost_id'];
                } else {
                    // Create new cost
                    $additionalCost = new PurchaseOrderAdditionalCost([
                        'rr_id' => $receivingReport->rr_id,
                        'cost_type_id' => $costData['cost_type_id'],
                        'amount' => $costData['amount'],
                        'remarks' => $costData['remarks'] ?? null,
                    ]);
                    
                    $receivingReport->additionalCosts()->save($additionalCost);
                    $updatedCostIds[] = $additionalCost->po_cost_id;
                }
            }
        }
        
        // Delete costs that weren't included in the update
        $costsToDelete = array_diff($existingCostIds, $updatedCostIds);
        if (!empty($costsToDelete)) {
            PurchaseOrderAdditionalCost::whereIn('po_cost_id', $costsToDelete)->delete();
        }
        
        // Update purchase order status - might need to recalculate based on all receiving reports
        $purchaseOrder->updateStatus(); // This will set to COMPLETED if all items are fully received or keep as PARTIALLY_RECEIVED
        $receivingReport->refreshTotals();
        DB::commit();
        
        // Load relationships for the response
        return $receivingReport->load([
            'received_items.product',
            'received_items.attribute',
            'additionalCosts.costType'
        ]);
    } catch (Exception $e) {
        DB::rollBack();
        throw new Exception('Failed to update receiving report: ' . $e->getMessage());
    }
}
}