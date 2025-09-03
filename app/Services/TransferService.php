<?php

namespace App\Services;

use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\PurchaseOrderReceivedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class TransferService
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Create a new transfer and immediately adjust inventory using FIFO
     *
     * @param array $data
     * @return Transfer
     * @throws Exception
     */
    public function createTransfer(array $data): Transfer
    {
        DB::beginTransaction();
        
        try {
            // Validate warehouse
            $warehouse = Warehouse::active()->findOrFail($data['to_warehouse_id']);
            
            // Create transfer
            $transfer = Transfer::create([
                'transfer_number' => Transfer::generateTransferNumber(),
                'to_warehouse_id' => $data['to_warehouse_id'],
                'created_by' => Auth::id(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => Transfer::STATUS_IN_TRANSIT // Automatically set to in_transit since inventory is adjusted immediately
            ]);
            
            $totalValue = 0;
            
            // Process transfer items with FIFO inventory adjustment (similar to sales)
            $this->processTransferItems($transfer, $data['items']);
            
            // Calculate total value from items
            $totalValue = $transfer->items()->sum('total_cost');
            $transfer->update(['total_value' => $totalValue]);
            
            DB::commit();
            
            return $transfer->load(['warehouse', 'items.product', 'creator']);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Process transfer items with FIFO inventory adjustment
     * Similar to processSaleItems but for transfers
     *
     * @param Transfer $transfer
     * @param array $items
     * @return void
     * @throws Exception
     */
    protected function processTransferItems(Transfer $transfer, array $items): void
    {
        foreach ($items as $item) {
            // Get the product
            $product = Product::findOrFail($item['product_id']);
            
            // Check inventory availability
            $inventory = Inventory::where('product_id', $product->id)->first();
            if (!$inventory || $inventory->quantity < $item['quantity']) {
                throw new Exception("Insufficient inventory for product: {$product->product_name}. Available: " . 
                                  ($inventory ? $inventory->quantity : 0) . ", Requested: {$item['quantity']}");
            }
            
            // Process FIFO costing (same logic as sales)
            $remainingToAllocate = $item['quantity'];
            $totalFifoCost = 0;
            
            // Get available batches ordered by received date (FIFO)
            $receivedItems = PurchaseOrderReceivedItem::where('product_id', $product->id)
                ->whereRaw('received_quantity > sold_quantity')
                ->orderBy('created_at', 'asc')
                ->get();
            
            foreach ($receivedItems as $receivedItem) {
                if ($remainingToAllocate <= 0) break;
                
                $availableQuantity = $receivedItem->received_quantity - $receivedItem->sold_quantity;
                $quantityFromBatch = min($availableQuantity, $remainingToAllocate);
                $costFromBatch = $quantityFromBatch * $receivedItem->distribution_price;
                
                // Update the sold quantity in this batch (treating transfer as consumption)
                $receivedItem->sold_quantity += $quantityFromBatch;
                $receivedItem->fully_consumed = ($receivedItem->received_quantity <= $receivedItem->sold_quantity);
                $receivedItem->save();
                
                // Add to our total cost
                $totalFifoCost += $costFromBatch;
                $remainingToAllocate -= $quantityFromBatch;
            }
            
            // Handle any remaining quantity (inventory discrepancy)
            if ($remainingToAllocate > 0) {
                // Get the latest cost as fallback
                $latestItem = PurchaseOrderReceivedItem::where('product_id', $product->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $fallbackCost = $latestItem ? $latestItem->distribution_price : 0;
                $fallbackTotal = $remainingToAllocate * $fallbackCost;
                
                $totalFifoCost += $fallbackTotal;
            }
            
            // Calculate the average FIFO cost
            $unitCost = $item['quantity'] > 0 ? $totalFifoCost / $item['quantity'] : 0;
            
            // Create transfer item
            TransferItem::create([
                'transfer_id' => $transfer->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalFifoCost,
                'notes' => $item['notes'] ?? null
            ]);
            
            // Update inventory using the same method as sales
            $this->updateInventoryOnTransfer($product->id, $item['quantity'], $transfer);
        }
    }

    /**
     * Update inventory when a transfer is made (similar to updateInventoryOnSale)
     *
     * @param int $productId
     * @param float $quantity
     * @param int $transferId
     * @return void
     * @throws Exception
     */
    protected function updateInventoryOnTransfer(int $productId, float $quantity, $transfer, string $direction = 'out'): void
    {
        // Get inventory
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            throw new Exception("Inventory record not found for product ID: {$productId}");
        }
        
        // Update inventory quantity
        $currentQuantity = $inventory->quantity;
        
        if ($direction === 'out') {
            // Transfer out - decrease inventory
            $inventory->decrementQuantity($quantity);
            
            // Update product quantity
            $product = Product::findOrFail($productId);
            $product->quantity -= $quantity;
            $product->save();
            
            // Create inventory log for transfer out
            $this->inventoryService->createInventoryLog(
                $productId,
                InventoryLog::TYPE_TRANSFER_OUT,
                InventoryLog::REF_TRANSFER,
                substr($transfer->transfer_number, 2),
                $quantity,
                $currentQuantity,
                $inventory->quantity,
                $inventory->avg_cost_price,
                "Product transferred to {$transfer->warehouse->name}"
            );
        } else {
            // Transfer in/restore - increase inventory
            $inventory->incrementQuantity($quantity);
            
            // Update product quantity
            $product = Product::findOrFail($productId);
            $product->quantity += $quantity;
            $product->save();
            
            // Create inventory log for transfer in
            $this->inventoryService->createInventoryLog(
                $productId,
                InventoryLog::TYPE_TRANSFER_IN,
                InventoryLog::REF_TRANSFER,
                substr($transfer->transfer_number, 2),
                $quantity,
                $currentQuantity,
                $inventory->quantity,
                $inventory->avg_cost_price,
                "Product transfer adjusted/restored from {$transfer->warehouse->name}"
            );
        }
    }

    /**
     * Update transfer status (simplified - only for delivery tracking)
     *
     * @param int $transferId
     * @param string $status
     * @param array $data
     * @return Transfer
     * @throws Exception
     */
    public function updateTransferStatus(int $transferId, string $status, array $data = []): Transfer
    {
        DB::beginTransaction();
        
        try {
            $transfer = Transfer::findOrFail($transferId);
            
            // Only allow status updates for tracking purposes (inventory already adjusted)
            if ($status === Transfer::STATUS_COMPLETED) {
                $transfer->update([
                    'status' => Transfer::STATUS_COMPLETED,
                    'delivery_date' => $data['delivery_date'] ?? now()
                ]);
            } elseif ($status === Transfer::STATUS_CANCELLED) {
                // For cancelled transfers, we need to restore inventory
                $this->restoreInventoryForCancelledTransfer($transfer, $data['reason'] ?? 'Transfer cancelled');
            } else {
                throw new Exception('Invalid status update. Only completed or cancelled statuses are allowed.');
            }
            
            DB::commit();
            return $transfer->load(['warehouse', 'items.product']);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Restore inventory when transfer is cancelled
     *
     * @param Transfer $transfer
     * @param string $reason
     * @return void
     */
    protected function restoreInventoryForCancelledTransfer(Transfer $transfer, string $reason): void
    {
        if ($transfer->status === Transfer::STATUS_CANCELLED) {
            throw new Exception('Transfer is already cancelled');
        }

        foreach ($transfer->items as $item) {
            // Restore inventory
            $inventory = Inventory::where('product_id', $item->product_id)->first();
            if ($inventory) {
                $quantityBefore = $inventory->quantity;
                $inventory->incrementQuantity($item->quantity);
                
                // Update product quantity
                $product = $item->product;
                $product->quantity += $item->quantity;
                $product->save();
                
                // Create inventory log for restoration
                $this->inventoryService->createInventoryLog(
                    $item->product_id,
                    InventoryLog::TYPE_TRANSFER_IN,
                    InventoryLog::REF_TRANSFER,
                    substr($transfer->transfer_number, 2),
                    $item->quantity,
                    $quantityBefore,
                    $inventory->quantity,
                    $item->unit_cost,
                    "Transfer cancelled - inventory restored: {$reason}"
                );
            }
            
            // Restore the FIFO batches (reverse the sold_quantity adjustment)
            $this->restoreFifoBatches($item->product_id, $item->quantity);
        }

        $transfer->update([
            'status' => Transfer::STATUS_CANCELLED,
            'notes' => ($transfer->notes ? $transfer->notes . "\n\n" : '') . "Cancelled: {$reason}"
        ]);
    }

    /**
     * Restore FIFO batches when transfer is cancelled
     *
     * @param int $productId
     * @param float $quantity
     * @return void
     */
    protected function restoreFifoBatches(int $productId, float $quantity): void
    {
        $remainingToRestore = $quantity;
        
        // Get batches that were consumed, starting with most recent first (reverse order)
        $batches = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->where('sold_quantity', '>', 0)
            ->orderBy('created_at', 'desc')
            ->get();
        
        foreach ($batches as $batch) {
            if ($remainingToRestore <= 0) break;
            
            $restoreQuantity = min($batch->sold_quantity, $remainingToRestore);
            
            // Restore sold quantity for this batch
            $batch->sold_quantity -= $restoreQuantity;
            $batch->fully_consumed = ($batch->received_quantity <= $batch->sold_quantity);
            $batch->save();
            
            $remainingToRestore -= $restoreQuantity;
        }
    }

    /**
     * Update transfer details (only allowed for in_transit transfers)
     *
     * @param int $transferId
     * @param array $data
     * @return Transfer
     * @throws Exception
     */
    // public function updateTransfer(int $transferId, array $data): Transfer
    // {
    //     DB::beginTransaction();
        
    //     try {
    //         $transfer = Transfer::findOrFail($transferId);
            
    //         // Only allow updates for in_transit transfers (before completion)
    //         if ($transfer->status === Transfer::STATUS_COMPLETED) {
    //             throw new Exception('Cannot update completed transfers');
    //         }
            
    //         if ($transfer->status === Transfer::STATUS_CANCELLED) {
    //             throw new Exception('Cannot update cancelled transfers');
    //         }
            
    //         // Update basic transfer details (not items since inventory is already adjusted)
    //         $transfer->update([
    //             'delivery_date' => $data['delivery_date'] ?? $transfer->delivery_date,
    //             'notes' => $data['notes'] ?? $transfer->notes
    //         ]);
            
    //         DB::commit();
            
    //         return $transfer->load(['warehouse', 'items.product', 'creator']);
            
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

    public function updateTransfer(int $transferId, array $data): Transfer
    {
        DB::beginTransaction();
        
        try {
            $transfer = Transfer::findOrFail($transferId);
            
            // Only allow updates for in_transit transfers (before completion)
            if ($transfer->status === Transfer::STATUS_COMPLETED) {
                throw new Exception('Cannot update completed transfers');
            }
            
            if ($transfer->status === Transfer::STATUS_CANCELLED) {
                throw new Exception('Cannot update cancelled transfers');
            }
            
            // Update basic transfer details
            $transfer->update([
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $transfer->to_warehouse_id,
                'delivery_date' => $data['delivery_date'] ?? $transfer->delivery_date,
                'notes' => $data['notes'] ?? $transfer->notes
            ]);
            
            // Handle item updates
            if (isset($data['items']) && is_array($data['items'])) {
                $this->updateTransferItems($transfer, $data['items']);
                
                // Recalculate total value
                $totalValue = $transfer->items()->sum('total_cost');
                $transfer->update(['total_value' => $totalValue]);
            }
            
            DB::commit();
            
            return $transfer->load(['warehouse', 'items.product', 'creator']);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

protected function updateTransferItems(Transfer $transfer, array $newItems): void
{
    $existingItems = $transfer->items()->get()->keyBy('product_id');
    $newItemsKeyed = collect($newItems)->keyBy('product_id');
    
    // Handle removed items
    foreach ($existingItems as $productId => $existingItem) {
        if (!$newItemsKeyed->has($productId)) {
            // Item was removed - restore inventory
            $this->restoreInventoryForItem($existingItem, $transfer);
            $existingItem->delete();
        }
    }
    
    // Handle new/updated items
    foreach ($newItems as $newItem) {
        $productId = $newItem['product_id'];
        $existingItem = $existingItems->get($productId);
        
        if ($existingItem) {
            // Item exists - check if quantity changed
            $oldQuantity = $existingItem->quantity;
            $newQuantity = $newItem['quantity'];
            
            if ($oldQuantity != $newQuantity) {
                // Calculate the difference for proper logging
                $quantityDifference = $newQuantity - $oldQuantity;
                
                if ($quantityDifference > 0) {
                    // Quantity increased - just process the additional amount
                    $additionalItem = $newItem;
                    $additionalItem['quantity'] = $quantityDifference;
                    $this->processSingleTransferItem($transfer, $additionalItem, null, 'additional');
                    
                    // Update existing item
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'notes' => $newItem['notes'] ?? $existingItem->notes
                    ]);
                } else {
                    // Quantity decreased - restore the reduction amount then update
                    $reductionAmount = abs($quantityDifference);
                    
                    // Log the reduction as transfer in
                    $this->updateInventoryOnTransfer($existingItem->product_id, $reductionAmount, $transfer, 'in');
                    $this->restoreFifoBatches($existingItem->product_id, $reductionAmount);
                    
                    // Update existing item
                    $existingItem->update([
                        'quantity' => $newQuantity,
                        'notes' => $newItem['notes'] ?? $existingItem->notes
                    ]);
                }
            } else {
                // Just update notes if quantity is same
                $existingItem->update([
                    'notes' => $newItem['notes'] ?? $existingItem->notes
                ]);
            }
        } else {
            // New item - process normally
            $this->processSingleTransferItem($transfer, $newItem);
        }
    }
}

    protected function restoreInventoryForItem(TransferItem $item, $transfer): void
    {
        // Use the new direction parameter for proper logging
        $this->updateInventoryOnTransfer($item->product_id, $item->quantity, $transfer, 'in');
        
        // Restore FIFO batches
        $this->restoreFifoBatches($item->product_id, $item->quantity);
    }

protected function processSingleTransferItem(Transfer $transfer, array $itemData, int $existingItemId = null, string $scenario = 'normal'): void
{
    // Get the product
    $product = Product::findOrFail($itemData['product_id']);
    
    // Check inventory availability
    $inventory = Inventory::where('product_id', $product->id)->first();
    if (!$inventory || $inventory->quantity < $itemData['quantity']) {
        throw new Exception("Insufficient inventory for product: {$product->product_name}. Available: " . 
                        ($inventory ? $inventory->quantity : 0) . ", Requested: {$itemData['quantity']}");
    }
    
    // Process FIFO costing (same logic as in processTransferItems)
    $remainingToAllocate = $itemData['quantity'];
    $totalFifoCost = 0;
    
    // Get available batches ordered by received date (FIFO)
    $receivedItems = PurchaseOrderReceivedItem::where('product_id', $product->id)
        ->whereRaw('received_quantity > sold_quantity')
        ->orderBy('created_at', 'asc')
        ->get();
    
    foreach ($receivedItems as $receivedItem) {
        if ($remainingToAllocate <= 0) break;
        
        $availableQuantity = $receivedItem->received_quantity - $receivedItem->sold_quantity;
        $quantityFromBatch = min($availableQuantity, $remainingToAllocate);
        $costFromBatch = $quantityFromBatch * $receivedItem->distribution_price;
        
        // Update the sold quantity in this batch
        $receivedItem->sold_quantity += $quantityFromBatch;
        $receivedItem->fully_consumed = ($receivedItem->received_quantity <= $receivedItem->sold_quantity);
        $receivedItem->save();
        
        $totalFifoCost += $costFromBatch;
        $remainingToAllocate -= $quantityFromBatch;
    }
    
    // Handle any remaining quantity with fallback cost
    if ($remainingToAllocate > 0) {
        $latestItem = PurchaseOrderReceivedItem::where('product_id', $product->id)
            ->orderBy('created_at', 'desc')
            ->first();
        
        $fallbackCost = $latestItem ? $latestItem->distribution_price : 0;
        $fallbackTotal = $remainingToAllocate * $fallbackCost;
        
        $totalFifoCost += $fallbackTotal;
    }
    
    // Calculate the average FIFO cost
    $unitCost = $itemData['quantity'] > 0 ? $totalFifoCost / $itemData['quantity'] : 0;
    
    // Create or update transfer item (only for normal scenarios, not additional)
    if ($scenario === 'normal') {
        if ($existingItemId) {
            TransferItem::where('id', $existingItemId)->update([
                'quantity' => $itemData['quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalFifoCost,
                'notes' => $itemData['notes'] ?? null
            ]);
        } else {
            TransferItem::create([
                'transfer_id' => $transfer->id,
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_cost' => $unitCost,
                'total_cost' => $totalFifoCost,
                'notes' => $itemData['notes'] ?? null
            ]);
        }
    }
    
    // Update inventory - always transfer out for this method
    $this->updateInventoryOnTransfer($product->id, $itemData['quantity'], $transfer, 'out');
}


    /**
     * Get transfers with filters
     *
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTransfers(array $filters = [])
    {
        $query = Transfer::with(['warehouse', 'creator', 'items.product'])
                        ->orderBy('created_at', 'desc');
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['warehouse_id'])) {
            $query->where('to_warehouse_id', $filters['warehouse_id']);
        }
        
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('delivery_date', [$filters['start_date'], $filters['end_date']]);
        }
        
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('transfer_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('warehouse', function ($wq) use ($filters) {
                      $wq->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }
        
        return $query->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Get transfer statistics
     *
     * @param array $filters
     * @return array
     */
    public function getTransferStats(array $filters = []): array
    {
        $baseQuery = Transfer::query();
        
        // Apply date filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $baseQuery->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }
        
        return [
            'total_transfers' => (clone $baseQuery)->count(),
            'in_transit_transfers' => (clone $baseQuery)->where('status', Transfer::STATUS_IN_TRANSIT)->count(),
            'completed_transfers' => (clone $baseQuery)->where('status', Transfer::STATUS_COMPLETED)->count(),
            'cancelled_transfers' => (clone $baseQuery)->where('status', Transfer::STATUS_CANCELLED)->count(),
            'total_value' => (clone $baseQuery)->where('status', '!=', Transfer::STATUS_CANCELLED)->sum('total_value'),
            'completed_value' => (clone $baseQuery)->where('status', Transfer::STATUS_COMPLETED)->sum('total_value'),
        ];
    }

    /**
     * Get single transfer by ID
     *
     * @param int $transferId
     * @return Transfer
     */
    public function getTransferById(int $transferId): Transfer
    {
        return Transfer::with(['warehouse', 'creator', 'items.product','items.product.attribute'])->findOrFail($transferId);
    }

    /**
     * Delete transfer (only allowed for in_transit status)
     *
     * @param int $transferId
     * @return bool
     * @throws Exception
     */
    public function deleteTransfer(int $transferId): bool
    {
        DB::beginTransaction();
        
        try {
            $transfer = Transfer::findOrFail($transferId);
            
            if ($transfer->status === Transfer::STATUS_COMPLETED) {
                throw new Exception('Cannot delete completed transfers');
            }
            
            if ($transfer->status === Transfer::STATUS_IN_TRANSIT) {
                // Restore inventory before deletion
                $this->restoreInventoryForCancelledTransfer($transfer, 'Transfer deleted');
            }
            
            $transfer->delete();
            
            DB::commit();
            return true;
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}