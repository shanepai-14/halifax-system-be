<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReceivedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

use Exception;

class PurchaseOrderService
{
    private function generatePoNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        // Get the latest PO number for the current year and month
        $latestPO = PurchaseOrder::where('po_number', 'like', "PO{$year}{$month}%")
            ->orderBy('po_number', 'desc')
            ->first();

        if ($latestPO) {
            // Extract the sequence number and increment it
            $sequence = (int) substr($latestPO->po_number, -4);
            $sequence++;
        } else {
            $sequence = 1;
        }

        // Format: PO-YYYYMM-XXXX (e.g., PO-202401-0001)
        return sprintf("PO%s%s%04d", $year, $month, $sequence);
    }
    /**
     * Get all purchase orders with optional filtering
     */
    public function getAllPurchaseOrders(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = PurchaseOrder::with(['supplier', 'items.product']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('po_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('po_date', '<=', $filters['date_to']);
        }

        // Sort
        $query->orderBy($filters['sort_by'] ?? 'po_date', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new purchase order with items
     */
    public function createPurchaseOrder(array $data): PurchaseOrder
    {
        try {
            DB::beginTransaction();
    
            $poNumber = $this->generatePoNumber();
            // Create PO
            $purchaseOrder = PurchaseOrder::create([
                'po_number' => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'po_date' => $data['po_date'],
                'status' => PurchaseOrder::STATUS_PENDING,
                'remarks' => $data['remarks'] ?? null,
                'attachment' => $data['attachment'] ?? null,
                'total_amount' => 0, // Will be calculated from items and additional costs
            ]);
    
            // Create PO items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $poItem = $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'],
                    'attribute_id' => $item['attribute_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'received_quantity' => 0,
                    'price' => $item['price']
                ]);
                $totalAmount += $poItem->price * $poItem->requested_quantity;
            }
    
            // Create additional costs if any
            $additionalCostsTotal = 0;
            if (!empty($data['additional_costs'])) {
                foreach ($data['additional_costs'] as $cost) {
                    $purchaseOrder->additionalCosts()->create([
                        'cost_type_id' => $cost['cost_type_id'],
                        'amount' => $cost['amount'],
                        'remarks' => $cost['remarks'] ?? null
                    ]);
                    $additionalCostsTotal += $cost['amount'];
                }
            }
    
            // Update total amount including additional costs
            $purchaseOrder->update(['total_amount' => $totalAmount + $additionalCostsTotal]);
    
            DB::commit();
            return $purchaseOrder->load(['supplier', 'items.product', 'additionalCosts.costType']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create purchase order: ' . $e->getMessage());
        }
    }

    /**
     * Get purchase order by ID
     */
    public function getPurchaseOrderById(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['supplier', 'items.product'])->findOrFail($id);
    }
    /**
     * Retrieve a purchase order by its PO number with related supplier and items
     *
     * @param string $poNumber The purchase order number to look up
     * @return PurchaseOrder
     * @throws ModelNotFoundException If purchase order not found
     */
    public function getPurchaseOrderByPONumber(String $poNumber): PurchaseOrder 
    {
        if (empty($poNumber)) {
            throw new InvalidArgumentException('PO number cannot be empty');
        }
    
        return PurchaseOrder::query()
            ->with([
                'supplier',
                'items.product',
                'items.attribute',
                'additionalCosts.costType',
                'received_items.product',
                'received_items.attribute',
                'attachments'  // Added this line
            ])
            ->where('po_number', $poNumber)
            ->firstOrFail();
    }

    /**
     * Update purchase order
     */
    public function updatePurchaseOrder(String $poNumber, array $data): PurchaseOrder
    {
        try {
            DB::beginTransaction();
    
            $purchaseOrder = $this->getPurchaseOrderByPONumber($poNumber);
            $currentStatus = $purchaseOrder->status;
    
            // Update PO details
            $purchaseOrder->update([
                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'po_date'     => $data['po_date'] ?? $purchaseOrder->po_date,
                'remarks'     => $data['remarks'] ?? $purchaseOrder->remarks,
                'invoice'     => $data['invoice'] ?? $purchaseOrder->invoice,
                'status'      => $data['status'] ?? $purchaseOrder->status,
            ]);
    
            $totalAmount = 0;
    
            // Handle items based on status
            if (isset($data['items'])) {
                if ($currentStatus === PurchaseOrder::STATUS_PENDING) {
                    // When status is pending, allow updating PurchaseOrderItems
                    $newItemIds = array_column($data['items'], 'po_item_id');
                    $purchaseOrder->items()
                        ->whereNotIn('po_item_id', array_filter($newItemIds))
                        ->delete();
    
                    foreach ($data['items'] as $item) {
                        if (isset($item['po_item_id'])) {
                            $poItem = PurchaseOrderItem::find($item['po_item_id']);
                            if ($poItem) {
                                $poItem->update($item);
                            }
                        } else {
                            $poItem = $purchaseOrder->items()->create($item);
                        }
                        $totalAmount += $poItem->price * $poItem->requested_quantity;
                    }
                } elseif ($currentStatus === PurchaseOrder::STATUS_PARTIALLY_RECEIVED) {

                    $newReceivedItemIds = array_column(
                        array_filter($data['received_items'], function($item) {
                            return isset($item['received_item_id']);
                        }), 
                        'received_item_id'
                    );
                
                    // Delete received items that are not in the new data
                    $purchaseOrder->received_items()
                        ->whereNotIn('received_item_id', array_filter($newReceivedItemIds))
                        ->delete();
                
                    foreach ($data['received_items'] as $item) {
                        if (isset($item['received_item_id'])) {
                            // Update existing received item
                            $receivedItem = PurchaseOrderReceivedItem::find($item['received_item_id']);
                            if ($receivedItem) {
                                $receivedItem->update([
                                    'received_quantity' => $item['received_quantity'],
                                    'cost_price' => $item['cost_price'],
                                    'walk_in_price' => $item['walk_in_price'] ?? null,
                                    'term_price' => $item['term_price'] ?? null,
                                    'wholesale_price' => $item['wholesale_price'] ?? null,
                                    'regular_price' => $item['regular_price'] ?? null,
                                    'remarks' => $item['remarks'] ?? null
                                ]);
                            }
                        } else {
                            // Create new received item
                            $receivedItem = $purchaseOrder->received_items()->create([
                                'product_id' => $item['product_id'],
                                'attribute_id' => $item['attribute_id'],
                                'received_quantity' => $item['received_quantity'],
                                'cost_price' => $item['cost_price'],
                                'walk_in_price' => $item['walk_in_price'] ?? null,
                                'term_price' => $item['term_price'] ?? null,
                                'wholesale_price' => $item['wholesale_price'] ?? null,
                                'regular_price' => $item['regular_price'] ?? null,
                                'remarks' => $item['remarks'] ?? null
                            ]);
                        }
                
                        // Update the total amount
                      $totalAmount += $receivedItem->cost_price * $receivedItem->received_quantity;
    }
                }
            }
    
            // Handle additional costs
            $additionalCostsTotal = 0;
            if (isset($data['additional_costs'])) {
                // Delete costs not in new data
                $newCostIds = array_column($data['additional_costs'], 'po_cost_id');
                $purchaseOrder->additionalCosts()
                    ->whereNotIn('po_cost_id', array_filter($newCostIds))
                    ->delete();
    
                foreach ($data['additional_costs'] as $cost) {
                    if (isset($cost['po_cost_id'])) {
                        $poCost = $purchaseOrder->additionalCosts()->find($cost['po_cost_id']);
                        if ($poCost) {
                            $poCost->update([
                                'amount' => $cost['amount'],
                                'remarks' => $cost['remarks'] ?? null
                            ]);
                        }
                    } else {
                        $poCost = $purchaseOrder->additionalCosts()->create([
                            'cost_type_id' => $cost['cost_type_id'],
                            'amount' => $cost['amount'],
                            'remarks' => $cost['remarks'] ?? null
                        ]);
                    }
                    $additionalCostsTotal += $poCost->amount;
                }
            }
    
            // Update total amount
            $purchaseOrder->update(['total_amount' => $totalAmount + $additionalCostsTotal]);
    
            DB::commit();
            return $purchaseOrder->load([
                'supplier', 
                'items.product', 
                'additionalCosts.costType',
                'received_items.product',
                'received_items.attribute'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update purchase order: ' . $e->getMessage());
        }
    }

    /**
     * Update received quantities
     */
    public function updateReceivedQuantities(int $poId, array $items): PurchaseOrder
    {
        try {
            DB::beginTransaction();

            $purchaseOrder = $this->getPurchaseOrderById($poId);

            foreach ($items as $item) {
                $poItem = $purchaseOrder->items()->findOrFail($item['po_item_id']);
                $poItem->updateReceivedQuantity($item['received_quantity']);
            }

            $purchaseOrder->updateStatus();

            DB::commit();
            return $purchaseOrder->load(['supplier', 'items.product']);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update received quantities: ' . $e->getMessage());
        }
    }

    /**
     * Cancel purchase order
     */
    public function cancelPurchaseOrder(int $id): PurchaseOrder
    {
        try {
            DB::beginTransaction();

            $purchaseOrder = $this->getPurchaseOrderById($id);
            
            if ($purchaseOrder->status === PurchaseOrder::STATUS_COMPLETED) {
                throw new Exception('Cannot cancel a completed purchase order');
            }

            $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);

            DB::commit();
            return $purchaseOrder;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to cancel purchase order: ' . $e->getMessage());
        }
    }

    /**
     * Get purchase order statistics
     */
    public function getPurchaseOrderStats(): array
    {
        return [
            'total_orders' => PurchaseOrder::count(),
            'pending_orders' => PurchaseOrder::where('status', PurchaseOrder::STATUS_PENDING)->count(),
            'completed_orders' => PurchaseOrder::where('status', PurchaseOrder::STATUS_COMPLETED)->count(),
            'partially_received_orders' => PurchaseOrder::where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED)->count(),
            'cancelled_orders' => PurchaseOrder::where('status', PurchaseOrder::STATUS_CANCELLED)->count(),
            'total_amount' => PurchaseOrder::sum('total_amount'),
        ];
    }

    public function updatePurchaseOrderStatus(String $poNumber, string $newStatus): PurchaseOrder
    {
        try {
            DB::beginTransaction();

            $purchaseOrder = $this->getPurchaseOrderByPONumber($poNumber);
            $currentStatus = $purchaseOrder->status;

            // Validate status transition
            if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
                throw new Exception('Invalid status transition from ' . $currentStatus . ' to ' . $newStatus);
            }

            // Additional validation for specific status changes
            if ($newStatus === PurchaseOrder::STATUS_COMPLETED) {
                // Check if all required fields are present for completion
                if (!$purchaseOrder->invoice) {
                    throw new Exception('Invoice number is required to complete the purchase order');
                }

                if (!$purchaseOrder->attachment) {
                    throw new Exception('Attachment is required to complete the purchase order');
                }

                // Check if all items have been received
                foreach ($purchaseOrder->items as $item) {
                    if ($item->received_quantity < $item->requested_quantity) {
                        throw new Exception('All items must be fully received before completing the purchase order');
                    }
                }
            }

            // Update the status
            $purchaseOrder->update([
                'status' => $newStatus
            ]);

            // Handle specific status changes
            if ($newStatus === PurchaseOrder::STATUS_CANCELLED) {
                // You might want to handle cancellation-specific logic here
                // For example, reverting any inventory changes, etc.
            }

            DB::commit();

            return $purchaseOrder->load([
                'supplier', 
                'items.product',
                'additionalCosts.costType',
                'received_items.product',
                'received_items.attribute'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update purchase order status: ' . $e->getMessage());
        }
    }

    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        // Define valid status transitions
        $validTransitions = [
            PurchaseOrder::STATUS_PENDING => [
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_CANCELLED
            ],
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED => [
                PurchaseOrder::STATUS_COMPLETED,
                PurchaseOrder::STATUS_CANCELLED
            ],
            PurchaseOrder::STATUS_COMPLETED => [],  // No transitions allowed from completed
            PurchaseOrder::STATUS_CANCELLED => []   // No transitions allowed from cancelled
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}