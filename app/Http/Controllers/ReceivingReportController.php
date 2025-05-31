<?php

namespace App\Http\Controllers;

use App\Models\ReceivingReport;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceivedItem;
use App\Models\PurchaseOrderAdditionalCost;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class ReceivingReportController extends Controller
{
    /**
     * Display a paginated listing of receiving reports with filtering options.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ReceivingReport::with([
                'purchaseOrder.supplier',
                'received_items.product',
                'received_items.attribute',
                'additionalCosts.costType'
            ]);

            $query->whereHas('purchaseOrder', function($q) {
                $q->where(function($innerQ) {
                    $innerQ->where('batch_number', '!=', '2024112588')
                          ->orWhereNull('batch_number');
                });
            });

            // Apply filters
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    // Search by batch number
                    $q->where('batch_number', 'like', "%{$search}%")
                      // Or by related purchase order number
                      ->orWhereHas('purchaseOrder', function ($poQuery) use ($search) {
                          $poQuery->where('po_number', 'like', "%{$search}%");
                      });
                });
            }

            // Filter by supplier
            if ($request->has('supplier_id') && $request->supplier_id != "All") {
                $query->whereHas('purchaseOrder', function ($poQuery) use ($request) {
                    $poQuery->where('supplier_id', $request->supplier_id);
                });
            }

            // Filter by payment status
            if ($request->has('is_paid') && $request->is_paid != "All") {
                $query->where('is_paid', $request->is_paid === 'true' || $request->is_paid === '1');
            }

            // Apply sorting
            $sortColumn = $request->sort_by ?? 'created_at';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortColumn, $sortOrder);

            // Paginate the results
            $perPage = $request->per_page ?? 10;
            $reports = $query->paginate($perPage);

            $transformedReports = $reports->getCollection()->map(function ($report) {
                $totalItems = $report->received_items->sum(function ($item) {
                    return $item->received_quantity * $item->cost_price;
                });
                
                $totalAdditionalCosts = $report->additionalCosts->sum('amount');
                
                return [
                    'rr_id' => $report->rr_id,
                    'batch_number' => $report->batch_number,
                    'po_id' => $report->po_id,
                    'po_number' => $report->purchaseOrder->po_number,
                    'supplier' => $report->purchaseOrder->supplier,
                    'invoice' => $report->invoice,
                    'term' => $report->term,
                    'is_paid' => (bool) $report->is_paid,
                    'created_at' => $report->created_at,
                    'updated_at' => $report->updated_at,
                    'received_items' => $report->received_items,
                    'additional_costs' => $report->additionalCosts,
                    'total_amount' => $totalItems + $totalAdditionalCosts
                ];
            });

            // Return the transformed paginated data
            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_page' => $reports->currentPage(),
                    'data' => $transformedReports,
                    'first_page_url' => $reports->url(1),
                    'from' => $reports->firstItem(),
                    'last_page' => $reports->lastPage(),
                    'last_page_url' => $reports->url($reports->lastPage()),
                    'next_page_url' => $reports->nextPageUrl(),
                    'path' => $reports->path(),
                    'per_page' => $reports->perPage(),
                    'prev_page_url' => $reports->previousPageUrl(),
                    'to' => $reports->lastItem(),
                    'total' => $reports->total(),
                ],
                'message' => 'Receiving reports retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving receiving reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a specific receiving report.
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $report = ReceivingReport::with([
                'purchaseOrder.supplier',
                'received_items.product',
                'received_items.attribute',
                'additionalCosts.costType',
                'attachments'
            ])->findOrFail($id);

            // Calculate totals
            $totalItems = $report->received_items->sum(function ($item) {
                return $item->received_quantity * $item->cost_price;
            });
            
            $totalAdditionalCosts = $report->additionalCosts->sum('amount');

            $data = [
                'rr_id' => $report->rr_id,
                'batch_number' => $report->batch_number,
                'po_id' => $report->po_id,
                'po_number' => $report->purchaseOrder->po_number,
                'supplier' => $report->purchaseOrder->supplier,
                'invoice' => $report->invoice,
                'term' => $report->term,
                'is_paid' => (bool) $report->is_paid,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
                'received_items' => $report->received_items,
                'additional_costs' => $report->additionalCosts,
                'attachments' => $report->attachments,
                'total_amount' => $totalItems + $totalAdditionalCosts
            ];

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'message' => 'Receiving report retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Receiving report not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the payment status of a receiving report.
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updatePaymentStatus(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_paid' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $report = ReceivingReport::findOrFail($id);
            $report->update(['is_paid' => $request->is_paid]);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'rr_id' => $report->rr_id,
                    'is_paid' => (bool) $report->is_paid
                ],
                'message' => 'Payment status updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a receiving report's details.
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Find the receiving report
            $report = ReceivingReport::with([
                'received_items',
                'additionalCosts'
            ])->findOrFail($id);
            
            // Find the purchase order
            $purchaseOrder = PurchaseOrder::findOrFail($report->po_id);
            
            // Cannot update receiving report for cancelled PO
            if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw new Exception('Cannot update receiving report for a cancelled purchase order');
            }
            
            // Validate the incoming request
            $validator = Validator::make($request->all(), [
                'invoice' => 'nullable|string|max:100',
                'term' => 'nullable|integer|min:0',
                'is_paid' => 'nullable|boolean',
                'received_items' => 'required|array|min:1',
                'received_items.*.product_id' => 'required|exists:products,id',
                'received_items.*.attribute_id' => 'required|exists:attributes,id',
                'received_items.*.received_quantity' => 'required|numeric|min:0.01',
                'received_items.*.cost_price' => 'required|numeric|min:0.01',
                'received_items.*.walk_in_price' => 'required|numeric|min:0',
                'received_items.*.wholesale_price' => 'required|numeric|min:0',
                'received_items.*.regular_price' => 'required|numeric|min:0',
                'received_items.*.remarks' => 'nullable|string',
                'additional_costs' => 'sometimes|array',
                'additional_costs.*.cost_type_id' => 'required|exists:additional_cost_types,cost_type_id',
                'additional_costs.*.amount' => 'required|numeric|min:0',
                'additional_costs.*.remarks' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update the receiving report basic info
            $report->update([
                'invoice' => $request->invoice,
                'term' => $request->term ?? 0,
                'is_paid' => $request->is_paid ?? false,
            ]);
            
            // Track existing item IDs to determine which ones to keep, update, or delete
            $existingItemIds = $report->received_items->pluck('received_item_id')->toArray();
            $updatedItemIds = [];
            
            // Process received items - update existing or create new ones
            foreach ($request->received_items as $itemData) {
                // Check if this item has an ID (existing item)
                if (isset($itemData['received_item_id']) && in_array($itemData['received_item_id'], $existingItemIds)) {
                    // Update existing item
                    $receivedItem = PurchaseOrderReceivedItem::findOrFail($itemData['received_item_id']);
                    $receivedItem->update([
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
                    
                    $updatedItemIds[] = $itemData['received_item_id'];
                } else {
                    // Create new item
                    $receivedItem = new PurchaseOrderReceivedItem([
                        'rr_id' => $report->rr_id,
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
                    
                    $report->received_items()->save($receivedItem);
                    $updatedItemIds[] = $receivedItem->received_item_id;
                }
            }
            
            // Delete items that weren't included in the update
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                PurchaseOrderReceivedItem::whereIn('received_item_id', $itemsToDelete)->delete();
            }
            
            // Track existing additional cost IDs
            $existingCostIds = $report->additionalCosts->pluck('additional_cost_id')->toArray();
            $updatedCostIds = [];
            
            // Process additional costs - update existing or create new ones
            if (!empty($request->additional_costs)) {
                foreach ($request->additional_costs as $costData) {
                    // Check if this cost has an ID (existing cost)
                    if (isset($costData['additional_cost_id']) && in_array($costData['additional_cost_id'], $existingCostIds)) {
                        // Update existing cost
                        $additionalCost = PurchaseOrderAdditionalCost::findOrFail($costData['additional_cost_id']);
                        $additionalCost->update([
                            'cost_type_id' => $costData['cost_type_id'],
                            'amount' => $costData['amount'],
                            'remarks' => $costData['remarks'] ?? null,
                        ]);
                        
                        $updatedCostIds[] = $costData['additional_cost_id'];
                    } else {
                        // Create new cost
                        $additionalCost = new PurchaseOrderAdditionalCost([
                            'rr_id' => $report->rr_id,
                            'cost_type_id' => $costData['cost_type_id'],
                            'amount' => $costData['amount'],
                            'remarks' => $costData['remarks'] ?? null,
                        ]);
                        
                        $report->additionalCosts()->save($additionalCost);
                        $updatedCostIds[] = $additionalCost->additional_cost_id;
                    }
                }
            }
            
            // Delete costs that weren't included in the update
            $costsToDelete = array_diff($existingCostIds, $updatedCostIds);
            if (!empty($costsToDelete)) {
                PurchaseOrderAdditionalCost::whereIn('additional_cost_id', $costsToDelete)->delete();
            }
            
            // Update purchase order status - might need to recalculate based on all receiving reports
            $purchaseOrder->updateStatus();
            
            DB::commit();
            
            // Return the updated report with related data
            $updatedReport = ReceivingReport::with([
                'purchaseOrder.supplier',
                'received_items.product',
                'received_items.attribute',
                'additionalCosts.costType'
            ])->findOrFail($id);
            
            return response()->json([
                'status' => 'success',
                'data' => $updatedReport,
                'message' => 'Receiving report updated successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update receiving report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get statistics for receiving reports
     * 
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = [
                'total_reports' => ReceivingReport::count(),
                'paid_reports' => ReceivingReport::where('is_paid', true)->count(),
                'unpaid_reports' => ReceivingReport::where('is_paid', false)->count(),
                'total_received_value' => DB::select(
                    'SELECT SUM(pri.received_quantity * pri.cost_price) as total 
                    FROM purchase_order_received_items pri
                    JOIN receiving_reports rr ON pri.rr_id = rr.rr_id'
                )[0]->total ?? 0,
                'reports_today' => ReceivingReport::whereDate('created_at', date('Y-m-d'))->count(),
                'reports_this_month' => ReceivingReport::whereYear('created_at', date('Y'))
                    ->whereMonth('created_at', date('m'))
                    ->count(),
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Receiving report statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving receiving report statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a receiving report
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $report = ReceivingReport::findOrFail($id);
            
            // Check if the associated purchase order is completed
            if ($report->purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
                throw new Exception('Cannot delete a receiving report from a completed purchase order');
            }
            
            // Delete related received items
            $report->received_items()->delete();
            
            // Delete related additional costs
            $report->additionalCosts()->delete();
            
            // Delete the report itself
            $report->delete();
            
            // Update the purchase order status
            $report->purchaseOrder->updateStatus();
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Receiving report deleted successfully'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete receiving report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}