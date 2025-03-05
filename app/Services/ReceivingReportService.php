<?php

namespace App\Services;

use App\Models\ReceivingReport;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceivedItem;
use App\Models\PurchaseOrderAdditionalCost;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

class ReceivingReportService
{
    /**
     * Get all receiving reports with optional filtering and pagination
     * 
     * @param array $filters
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getAllReceivingReports(array $filters = [], ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = ReceivingReport::with([
            'purchaseOrder.supplier',
            'received_items.product',
            'received_items.attribute',
            'additionalCosts.costType'
        ]);

        // Apply filters
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                  ->orWhereHas('purchaseOrder', function ($poQuery) use ($search) {
                      $poQuery->where('po_number', 'like', "%{$search}%");
                  });
            });
        }

        if (isset($filters['supplier_id'])) {
            $query->whereHas('purchaseOrder', function ($poQuery) use ($filters) {
                $poQuery->where('supplier_id', $filters['supplier_id']);
            });
        }

        if (isset($filters['is_paid'])) {
            $query->where('is_paid', filter_var($filters['is_paid'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Sort
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        // Paginate or get all
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get a single receiving report by ID
     * 
     * @param int $id
     * @return ReceivingReport
     */
    public function getReceivingReportById(int $id): ReceivingReport
    {
        return ReceivingReport::with([
            'purchaseOrder.supplier',
            'received_items.product',
            'received_items.attribute',
            'additionalCosts.costType',
            'attachments'
        ])->findOrFail($id);
    }

    /**
     * Create a new receiving report
     * 
     * @param array $data
     * @return ReceivingReport
     */
    public function createReceivingReport(array $data): ReceivingReport
    {
        try {
            DB::beginTransaction();
            
            // Get the purchase order
            $purchaseOrder = PurchaseOrder::findOrFail($data['po_id']);
            
            // Cannot create receiving report for cancelled POs
            if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw new Exception('Cannot create receiving report for a cancelled purchase order');
            }
            
            if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
                throw new Exception('Cannot create receiving report for a completed purchase order');
            }
            
            // Create the receiving report
            $receivingReport = new ReceivingReport([
                'po_id' => $data['po_id'],
                'invoice' => $data['invoice'] ?? null,
                'term' => $data['term'] ?? 0,
                'is_paid' => $data['is_paid'] ?? false,
            ]);
            
            // Save to generate the batch number
            $receivingReport->save();
            
            // Process received items
            if (isset($data['received_items']) && is_array($data['received_items'])) {
                foreach ($data['received_items'] as $itemData) {
                    $receivedItem = new PurchaseOrderReceivedItem([
                        'rr_id' => $receivingReport->rr_id,
                        'product_id' => $itemData['product_id'],
                        'attribute_id' => $itemData['attribute_id'] ?? null,
                        'received_quantity' => $itemData['received_quantity'],
                        'cost_price' => $itemData['cost_price'],
                        'walk_in_price' => $itemData['walk_in_price'],
                        'term_price' => $itemData['term_price'] ?? 0,
                        'wholesale_price' => $itemData['wholesale_price'],
                        'regular_price' => $itemData['regular_price'],
                        'remarks' => $itemData['remarks'] ?? null,
                    ]);
                    
                    $receivingReport->received_items()->save($receivedItem);
                }
            }
            
            // Process additional costs
            if (isset($data['additional_costs']) && is_array($data['additional_costs'])) {
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
            
            DB::commit();
            
            return $this->getReceivingReportById($receivingReport->rr_id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update the payment status of a receiving report
     * 
     * @param int $id
     * @param bool $isPaid
     * @return ReceivingReport
     */
    public function updatePaymentStatus(int $id, bool $isPaid): ReceivingReport
    {
        $report = ReceivingReport::findOrFail($id);
        $report->update(['is_paid' => $isPaid]);
        return $report;
    }

    /**
     * Update receiving report details
     * 
     * @param int $id
     * @param array $data
     * @return ReceivingReport
     */
    public function updateReceivingReport(int $id, array $data): ReceivingReport
    {
        try {
            DB::beginTransaction();
            
            $report = ReceivingReport::with([
                'received_items',
                'additionalCosts'
            ])->findOrFail($id);
            
            // Cannot update receiving report for cancelled PO
            if ($report->purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw new Exception('Cannot update receiving report for a cancelled purchase order');
            }
            
            // Update basic information
            $report->update([
                'invoice' => $data['invoice'] ?? $report->invoice,
                'term' => $data['term'] ?? $report->term,
                'is_paid' => $data['is_paid'] ?? $report->is_paid,
            ]);
            
            // Update received items
            if (isset($data['received_items']) && is_array($data['received_items'])) {
                $this->processReceivedItems($report, $data['received_items']);
            }
            
            // Update additional costs
            if (isset($data['additional_costs']) && is_array($data['additional_costs'])) {
                $this->processAdditionalCosts($report, $data['additional_costs']);
            }
            
            // Update the purchase order status
            $report->purchaseOrder->updateStatus();
            
            DB::commit();
            
            // Reload the report with all relations
            return $this->getReceivingReportById($id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a receiving report
     * 
     * @param int $id
     * @return bool
     */
    public function deleteReceivingReport(int $id): bool
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
            $result = $report->delete();
            
            // Update the purchase order status
            $report->purchaseOrder->updateStatus();
            
            DB::commit();
            
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get receiving report statistics
     * 
     * @return array
     */
    public function getReceivingReportStats(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        
        return [
            'total_reports' => ReceivingReport::count(),
            'paid_reports' => ReceivingReport::where('is_paid', true)->count(),
            'unpaid_reports' => ReceivingReport::where('is_paid', false)->count(),
            'total_received_value' => DB::select(
                'SELECT SUM(pri.received_quantity * pri.cost_price) as total 
                FROM purchase_order_received_items pri
                JOIN receiving_reports rr ON pri.rr_id = rr.rr_id'
            )[0]->total ?? 0,
            'reports_today' => ReceivingReport::whereDate('created_at', $now->toDateString())->count(),
            'reports_this_month' => ReceivingReport::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count(),
        ];
    }

    /**
     * Process received items during update
     * 
     * @param ReceivingReport $report
     * @param array $items
     * @return void
     */
    private function processReceivedItems(ReceivingReport $report, array $items): void
    {
        $existingItemIds = $report->received_items->pluck('received_item_id')->toArray();
        $updatedItemIds = [];
        
        foreach ($items as $itemData) {
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
                    'wholesale_price' => $itemData['wholesale_price'],
                    'regular_price' => $itemData['regular_price'],
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
                    'wholesale_price' => $itemData['wholesale_price'],
                    'regular_price' => $itemData['regular_price'],
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
    }

    /**
     * Process additional costs during update
     * 
     * @param ReceivingReport $report
     * @param array $costs
     * @return void
     */
    private function processAdditionalCosts(ReceivingReport $report, array $costs): void
    {
        $existingCostIds = $report->additionalCosts->pluck('additional_cost_id')->toArray();
        $updatedCostIds = [];
        
        foreach ($costs as $costData) {
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
        
        // Delete costs that weren't included in the update
        $costsToDelete = array_diff($existingCostIds, $updatedCostIds);
        if (!empty($costsToDelete)) {
            PurchaseOrderAdditionalCost::whereIn('additional_cost_id', $costsToDelete)->delete();
        }
    }

    /**
     * Calculate total amount for a receiving report
     * 
     * @param ReceivingReport $report
     * @return float
     */
    public function calculateTotal(ReceivingReport $report): float
    {
        $itemsTotal = $report->received_items->sum(function ($item) {
            return $item->received_quantity * $item->cost_price;
        });
        
        $costsTotal = $report->additionalCosts->sum('amount');
        
        return $itemsTotal + $costsTotal;
    }

    /**
     * Check if all purchase order items are fully received
     * 
     * @param PurchaseOrder $purchaseOrder
     * @return bool
     */
    public function isFullyReceived(PurchaseOrder $purchaseOrder): bool
    {
        // Get all received items for this PO across all receiving reports
        $receivedQuantities = DB::table('purchase_order_received_items')
            ->join('receiving_reports', 'receiving_reports.rr_id', '=', 'purchase_order_received_items.rr_id')
            ->where('receiving_reports.po_id', $purchaseOrder->po_id)
            ->select('purchase_order_received_items.product_id', DB::raw('SUM(purchase_order_received_items.received_quantity) as total_received'))
            ->groupBy('purchase_order_received_items.product_id')
            ->get()
            ->keyBy('product_id');
        
        // Check if all PO items are fully received
        foreach ($purchaseOrder->items as $item) {
            $received = $receivedQuantities[$item->product_id]->total_received ?? 0;
            if ($received < $item->requested_quantity) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get reports by purchase order ID
     * 
     * @param int $poId
     * @return Collection
     */
    public function getReportsByPurchaseOrder(int $poId): Collection
    {
        return ReceivingReport::with([
            'received_items.product',
            'received_items.attribute',
            'additionalCosts.costType',
            'attachments'
        ])->where('po_id', $poId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get reports by supplier ID
     * 
     * @param int $supplierId
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getReportsBySupplier(int $supplierId, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = ReceivingReport::with([
            'purchaseOrder',
            'received_items.product',
            'additionalCosts'
        ])->whereHas('purchaseOrder', function ($query) use ($supplierId) {
            $query->where('supplier_id', $supplierId);
        })->orderBy('created_at', 'desc');
        
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get reports by date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getReportsByDateRange(string $startDate, string $endDate, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = ReceivingReport::with([
            'purchaseOrder.supplier',
            'received_items.product',
            'additionalCosts'
        ])->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');
        
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Search for reports by various criteria
     * 
     * @param string $keyword
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function searchReports(string $keyword, ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = ReceivingReport::with([
            'purchaseOrder.supplier',
            'received_items.product',
            'additionalCosts'
        ])->where(function ($q) use ($keyword) {
            // Search by batch number
            $q->where('batch_number', 'like', "%{$keyword}%")
                ->orWhere('invoice', 'like', "%{$keyword}%")
                // Or by related purchase order number
                ->orWhereHas('purchaseOrder', function ($poQuery) use ($keyword) {
                    $poQuery->where('po_number', 'like', "%{$keyword}%");
                })
                // Or by supplier name
                ->orWhereHas('purchaseOrder.supplier', function ($supplierQuery) use ($keyword) {
                    $supplierQuery->where('supplier_name', 'like', "%{$keyword}%");
                })
                // Or by product name in received items
                ->orWhereHas('received_items.product', function ($productQuery) use ($keyword) {
                    $productQuery->where('product_name', 'like', "%{$keyword}%");
                });
        })->orderBy('created_at', 'desc');
        
        return $perPage ? $query->paginate($perPage) : $query->get();
    }
}