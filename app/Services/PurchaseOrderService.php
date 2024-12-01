<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
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
                'total_amount' => 0, // Will be calculated from items
            ]);

            // Create PO items
            $totalAmount = 0;
            foreach ($data['items'] as $item) {
                $poItem = $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'received_quantity' => 0,
                    'price' => $item['price']
                ]);
                $totalAmount += $poItem->price * $poItem->requested_quantity;
            }

            // Update total amount
            $purchaseOrder->update(['total_amount' => $totalAmount]);

            DB::commit();
            return $purchaseOrder->load(['supplier', 'items.product']);
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
                    'items.product'
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
    
            // Update PO details
            $purchaseOrder->update([

                'supplier_id' => $data['supplier_id'] ?? $purchaseOrder->supplier_id,
                'po_date'     => $data['po_date'] ?? $purchaseOrder->po_date,
                'remarks'     => $data['remarks'] ?? $purchaseOrder->remarks,
                'invoice'     => $data['invoice'] ?? $purchaseOrder->invoice,
                'status'      => $data['status'] ?? $purchaseOrder->status,
            ]);
    
            // Update or create items if provided
            if (isset($data['items'])) {
                // Delete existing items not in the new data
                $newItemIds = array_column($data['items'], 'po_item_id');
                $purchaseOrder->items()
                    ->whereNotIn('po_item_id', array_filter($newItemIds))
                    ->delete();
    
                // Update or create items
                $totalAmount = 0;
                $status = $data['status'] ?? $purchaseOrder->status;
                
                foreach ($data['items'] as $item) {
                    if (isset($item['po_item_id'])) {
                        $poItem = PurchaseOrderItem::find($item['po_item_id']);
                        if ($poItem) {
                            $poItem->update($item);
                        }
                    } else {
                        $poItem = $purchaseOrder->items()->create($item);
                    }
    
                    // Calculate total based on status
                    if ($status === 'completed') {
                        $totalAmount += $poItem->price * ($poItem->received_quantity ?? 0);
                    } else {
                        $totalAmount += $poItem->price * $poItem->requested_quantity;
                    }
                }
    
                // Update total amount
                $purchaseOrder->update(['total_amount' => $totalAmount]);
            }
    
            DB::commit();
            return $purchaseOrder->load(['supplier', 'items.product']);
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
}