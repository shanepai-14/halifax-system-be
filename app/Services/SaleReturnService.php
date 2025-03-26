<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class SaleReturnService
{
    /**
     * Get all returns with optional filtering
     *
     * @param array $filters
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getAllReturns(array $filters = [], ?int $perPage = null)
    {
        $query = SaleReturn::with(['sale', 'customer', 'user', 'items.product']);
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        
        if (!empty($filters['sale_id'])) {
            $query->where('sale_id', $filters['sale_id']);
        }
        
        if (!empty($filters['refund_method'])) {
            $query->where('refund_method', $filters['refund_method']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('credit_memo_number', 'like', "%{$search}%")
                  ->orWhereHas('sale', function ($saleQuery) use ($search) {
                      $saleQuery->where('invoice_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('customer', function ($customerQuery) use ($search) {
                      $customerQuery->where('customer_name', 'like', "%{$search}%");
                  });
            });
        }
        
        // Sort results
        $sortBy = $filters['sort_by'] ?? 'return_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $perPage ? $query->paginate($perPage) : $query->get();
    }
    
    /**
     * Get return by ID
     *
     * @param int $id
     * @return SaleReturn
     */
    public function getReturnById(int $id): SaleReturn
    {
        return SaleReturn::with([
            'sale.items.product',
            'customer',
            'user',
            'items.product',
            'items.saleItem'
        ])->findOrFail($id);
    }
    
    /**
     * Get return by credit memo number
     *
     * @param string $creditMemoNumber
     * @return SaleReturn
     */
    public function getReturnByCreditMemoNumber(string $creditMemoNumber): SaleReturn
    {
        return SaleReturn::with([
            'sale.items.product',
            'customer',
            'user',
            'items.product',
            'items.saleItem'
        ])->where('credit_memo_number', $creditMemoNumber)->firstOrFail();
    }
    
    /**
     * Create a new sales return
     *
     * @param array $data
     * @return SaleReturn
     * @throws Exception
     */
    public function createReturn(array $data): SaleReturn
    {
        try {
            DB::beginTransaction();
            
            // Get the sale
            $sale = Sale::findOrFail($data['sale_id']);
            
            // Validate if sale can be returned
            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('Cannot create return for a cancelled sale');
            }
            
            // Generate credit memo number if not provided
            if (empty($data['credit_memo_number'])) {
                $data['credit_memo_number'] = SaleReturn::generateCreditMemoNumber();
            }
            
            // Set user ID to current user if not provided
            if (empty($data['user_id'])) {
                $data['user_id'] = Auth::id();
            }
            
            // Create the return
            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'credit_memo_number' => $data['credit_memo_number'],
                'user_id' => $data['user_id'],
                'customer_id' => $sale->customer_id,
                'return_date' => $data['return_date'] ?? now(),
                'remarks' => $data['remarks'] ?? null,
                'status' => SaleReturn::STATUS_APPROVED,
                'refund_method' => $data['refund_method'] ?? SaleReturn::REFUND_NONE,
                'refund_amount' => $data['refund_amount'] ?? 0
            ]);
            
            // Process return items
            $totalAmount = 0;
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    // Get original sale item
                    $saleItem = SaleItem::findOrFail($itemData['sale_item_id']);
                    
                    // Validate if item can be returned
                    if (!$saleItem->canBeReturned($itemData['quantity'])) {
                        throw new Exception("Cannot return {$itemData['quantity']} of product {$saleItem->product->product_name}. Maximum available for return: " . ($saleItem->quantity - $saleItem->returned_quantity));
                    }
                    
                    // Create return item
                    $returnItem = $return->items()->create([
                        'sale_item_id' => $saleItem->id,
                        'product_id' => $saleItem->product_id,
                        'quantity' => $itemData['quantity'],
                        'price' => $saleItem->sold_price,
                        'discount' => $saleItem->discount,
                        'discount_amount' => ($saleItem->discount / 100) * $saleItem->sold_price * $itemData['quantity'],
                        'subtotal' => $saleItem->sold_price * $itemData['quantity'] * (1 - $saleItem->discount / 100),
                        'return_reason' => $itemData['return_reason'] ?? SaleReturnItem::REASON_OTHER,
                        'condition' => $itemData['condition'] ?? SaleReturnItem::CONDITION_GOOD
                    ]);
                    
                    $totalAmount += $returnItem->subtotal;
                    
                    // Return to inventory if applicable
                    if ($returnItem->is_returnable_to_inventory) {
                        $this->returnInventory($returnItem->product_id, $returnItem->quantity, $return->id);
                    }
                }
            }
            
            // Update total amount
            $return->update(['total_amount' => $totalAmount]);
            
            // If refund method is specified, process refund
            if (!empty($data['refund_method']) && $data['refund_method'] !== SaleReturn::REFUND_NONE) {
                $refundAmount = $data['refund_amount'] ?? $totalAmount;
                $return->update([
                    'refund_method' => $data['refund_method'],
                    'refund_amount' => $refundAmount
                ]);
            }
            
            
            DB::commit();
            
            return $this->getReturnById($return->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Return inventory for a product
     *
     * @param int $productId
     * @param int $quantity
     * @param int $returnId
     * @return void
     */
    protected function returnInventory(int $productId, int $quantity, int $returnId): void
    {
        // Get inventory
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            // Create inventory if it doesn't exist
            $inventory = Inventory::create([
                'product_id' => $productId,
                'quantity' => 0,
                'avg_cost_price' => 0,
                'recount_needed' => false
            ]);
        }
        
        // Update inventory quantity
        $oldQuantity = $inventory->quantity;
        $inventory->incrementQuantity($quantity);
        
        // Create inventory log
        InventoryLog::create([
            'product_id' => $productId,
            'user_id' => Auth::id(),
            'transaction_type' => InventoryLog::TYPE_RETURN,
            'reference_type' => 'sale_return',
            'reference_id' => $returnId,
            'quantity' => $quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $inventory->quantity,
            'notes' => "Return from Credit Memo #{$returnId}"
        ]);
    }
    
    /**
     * Update a sales return
     *
     * @param int $id
     * @param array $data
     * @return SaleReturn
     * @throws Exception
     */
    public function updateReturn(int $id, array $data): SaleReturn
    {
        try {
            DB::beginTransaction();
            
            // Get the return
            $return = $this->getReturnById($id);
            
            // Check if return can be updated
            if ($return->status !== SaleReturn::STATUS_PENDING) {
                throw new Exception('Cannot update a return that is not in pending status');
            }
            
            // Update return data
            $return->update([
                'remarks' => $data['remarks'] ?? $return->remarks,
                'refund_method' => $data['refund_method'] ?? $return->refund_method,
                'refund_amount' => $data['refund_amount'] ?? $return->refund_amount
            ]);
            
            DB::commit();
            
            return $this->getReturnById($return->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Approve a sales return
     *
     * @param int $id
     * @return SaleReturn
     * @throws Exception
     */
    public function approveReturn(int $id): SaleReturn
    {
        try {
            DB::beginTransaction();
            
            // Get the return
            $return = $this->getReturnById($id);
            
            // Check if return can be approved
            if ($return->status !== SaleReturn::STATUS_PENDING) {
                throw new Exception('Cannot approve a return that is not in pending status');
            }
            
            // Approve the return
            $return->update(['status' => SaleReturn::STATUS_APPROVED]);
            
            // If refund method is specified, process refund
            if ($return->refund_method !== SaleReturn::REFUND_NONE) {
                // Logic for processing refund
                // This would integrate with payment processors, etc.
                
                // Mark as completed after refund
                $return->complete();
            }
            
            DB::commit();
            
            return $this->getReturnById($return->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Reject a sales return
     *
     * @param int $id
     * @param string $reason
     * @return SaleReturn
     * @throws Exception
     */
    public function rejectReturn(int $id, string $reason = ''): SaleReturn
    {
        try {
            DB::beginTransaction();
            
            // Get the return
            $return = $this->getReturnById($id);
            
            // Check if return can be rejected
            if ($return->status !== SaleReturn::STATUS_PENDING) {
                throw new Exception('Cannot reject a return that is not in pending status');
            }
            
            // For any items that were already returned to inventory, remove them again
            foreach ($return->items as $item) {
                if ($item->is_returnable_to_inventory) {
                    $this->removeReturnedInventory($item->product_id, $item->quantity, $return->id);
                }
            }
            
            // Reject the return
            $return->update([
                'status' => SaleReturn::STATUS_REJECTED,
                'remarks' => $reason ? $return->remarks . ' | Rejected: ' . $reason : $return->remarks
            ]);
            
            // Update sale status
            $return->sale->updateStatus();
            
            DB::commit();
            
            return $this->getReturnById($return->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Remove returned inventory when a return is rejected
     *
     * @param int $productId
     * @param int $quantity
     * @param int $returnId
     * @return void
     */
    protected function removeReturnedInventory(int $productId, int $quantity, int $returnId): void
    {
        // Get inventory
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            return;
        }
        
        // Update inventory quantity
        $oldQuantity = $inventory->quantity;
        $inventory->decrementQuantity($quantity);
        
        // Create inventory log
        InventoryLog::create([
            'product_id' => $productId,
            'user_id' => Auth::id(),
            'transaction_type' => InventoryLog::TYPE_ADJUSTMENT_OUT,
            'reference_type' => 'return_reject',
            'reference_id' => $returnId,
            'quantity' => $quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $inventory->quantity,
            'notes' => "Return rejected for Credit Memo #{$returnId}"
        ]);
    }
    
    /**
     * Complete a sales return
     *
     * @param int $id
     * @return SaleReturn
     * @throws Exception
     */
    public function completeReturn(int $id): SaleReturn
    {
        try {
            DB::beginTransaction();
            
            // Get the return
            $return = $this->getReturnById($id);
            
            // Check if return can be completed
            if ($return->status !== SaleReturn::STATUS_APPROVED) {
                throw new Exception('Cannot complete a return that is not in approved status');
            }
            
            // Complete the return
            $return->complete();
            
            DB::commit();
            
            return $this->getReturnById($return->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get return statistics
     *
     * @param array $filters
     * @return array
     */
    public function getReturnStats(array $filters = []): array
    {
        $query = SaleReturn::query();
        
        // Apply date filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('return_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('return_date', '<=', $filters['date_to']);
        }
        
        // Get statistics
        $totalReturns = $query->count();
        $totalApproved = $query->where('status', SaleReturn::STATUS_APPROVED)->count();
        $totalRejected = $query->where('status', SaleReturn::STATUS_REJECTED)->count();
        $totalCompleted = $query->where('status', SaleReturn::STATUS_COMPLETED)->count();
        $totalAmount = $query->sum('total_amount');
        $totalRefunded = $query->sum('refund_amount');
        
        return [
            'total_returns' => $totalReturns,
            'total_approved' => $totalApproved,
            'total_rejected' => $totalRejected,
            'total_completed' => $totalCompleted,
            'total_amount' => $totalAmount,
            'total_refunded' => $totalRefunded,
            'approval_rate' => $totalReturns > 0 ? ($totalApproved / $totalReturns) * 100 : 0
        ];
    }
}