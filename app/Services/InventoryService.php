<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\InventoryCount;
use App\Models\PurchaseOrderReceivedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Exception;

class InventoryService
{
    /**
     * Get all inventory records, with optional filtering
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllInventory(array $filters = []): Collection
    {
        $query = Inventory::with('product');
        
        // Apply category filter if provided
        if (!empty($filters['category_id'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->where('product_category_id', $filters['category_id']);
            });
        }
        
        // Apply status filter if provided
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'low':
                    $query->whereHas('product', function ($q) {
                        $q->whereRaw('inventory.quantity <= products.reorder_level');
                    });
                    break;
                case 'normal':
                    $query->whereHas('product', function ($q) {
                        $q->whereRaw('inventory.quantity > products.reorder_level AND inventory.quantity <= (products.reorder_level * 3)');
                    });
                    break;
                case 'overstocked':
                    $query->whereHas('product', function ($q) {
                        $q->whereRaw('inventory.quantity > (products.reorder_level * 3)');
                    });
                    break;
            }
        }
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('product_code', 'like', "%{$search}%");
            });
        }
        
        return $query->get();
    }
    
    public function getAllInventorySales(): Collection
    {
        $query = Inventory::with([
            'product.category',
            'product.currentPrice', // Load the active/current price relation
        ]);

        // Get all inventory data with their products
        $inventories = $query->get();

        // Process each inventory item to format with required pricing data
        return $inventories->map(function ($inventory) {
            $product = $inventory->product;
            

            $receivedItem = PurchaseOrderReceivedItem::where('product_id', $product->id)
                            ->whereRaw('received_quantity > sold_quantity')
                            ->orderBy('created_at', 'asc')
                            ->first();

            if (!$receivedItem) {
                    $receivedItem = PurchaseOrderReceivedItem::where('product_id', $product->id)
                                    ->orderBy('created_at', 'desc')
                                    ->first();
            }                   
                
            // Set default prices
            $prices = [
                'distribution_price' => $receivedItem ? $receivedItem->distribution_price : 0,
                'cost_price' => $receivedItem ? $receivedItem->cost_price : 0,
                'walk_in_price' => 0,
                'wholesale_price' => 0,
                'regular_price' => 0
            ];
            
            // If product has current price, use it for retail prices
            if ($product->currentPrice) {
                $prices['walk_in_price'] = $product->currentPrice->walk_in_price;
                $prices['wholesale_price'] = $product->currentPrice->wholesale_price;
                $prices['regular_price'] = $product->currentPrice->regular_price;
            } else if ($receivedItem) {
                // Otherwise, use received item prices
                $prices['walk_in_price'] = $receivedItem->walk_in_price;
                $prices['wholesale_price'] = $receivedItem->wholesale_price;
                $prices['regular_price'] = $receivedItem->regular_price;
            }

            // Add computed status field
            $status = 'normal';
            if ($product->reorder_level > 0) {
                if ($inventory->quantity <= $product->reorder_level) {
                    $status = 'low';
                } elseif ($inventory->quantity > ($product->reorder_level * 3)) {
                    $status = 'overstocked';
                }
            }

            // Format inventory data as requested
            return [
                'id' => $product->id,
                'code' => $product->product_code,
                'name' => $product->product_name,
                'distribution_price' => $prices['distribution_price'],
                'cost_price' => $prices['cost_price'],
                'walk_in_price' => $prices['walk_in_price'],
                'wholesale_price' => $prices['wholesale_price'],
                'regular_price' => $prices['regular_price'],
                'quantity' => (int) $inventory->quantity,
                'category' => $product->category ? $product->category->name : 'Uncategorized',
                'product_image' => $product->product_image,
                'status' => $status,
                'reorder_level' => $product->reorder_level
            ];
        });
    }
     
    public function getInventorySummaryStats(): array
    {
        // Get all available products with their categories
        $products = Product::with('category')->get();
        
        // Get all received items that aren't fully consumed
        $receivedItems = PurchaseOrderReceivedItem::where('fully_consumed', false)
            ->orWhereNull('fully_consumed')
            ->get();
            
        // Count total active products in inventory
        $totalItems = $products->count();
        
        // Calculate total inventory value based on distribution cost of unsold items
        $totalValue = $receivedItems->sum(function ($item) {
            return $item->available_quantity * $item->distribution_price;
        });
        
        // Get low stock items
        $lowStockItems = $products->filter(function ($product) {
            return $product->inventory && $product->inventory->quantity <= $product->reorder_level;
        })->count();
        
        // Get items that need reordering immediately
        $reorderNeeded = $products->filter(function ($product) {
            return $product->inventory && 
                   $product->inventory->quantity <= ($product->reorder_level * 0.7); // 70% of reorder level
        })->count();
        
        // Calculate inventory value by category
        $categoriesWithValue = [];
        
        // Group received items by product
        $productItems = $receivedItems->groupBy('product_id');
        
        // Calculate value for each product and group by category
        foreach ($products as $product) {
            $productId = $product->id;
            $categoryId = $product->product_category_id;
            $categoryName = $product->category ? $product->category->name : 'Uncategorized';
            
            // Skip if no items for this product
            if (!isset($productItems[$productId])) {
                continue;
            }
            
            // Calculate product value from its received items
            $productValue = $productItems[$productId]->sum(function ($item) {
                return $item->available_quantity * $item->distribution_price;
            });
            
            // Initialize category if not exists
            if (!isset($categoriesWithValue[$categoryId])) {
                $categoriesWithValue[$categoryId] = [
                    'name' => $categoryName,
                    'count' => 0,
                    'value' => 0
                ];
            }
            
            // Add product to category stats
            $categoriesWithValue[$categoryId]['count']++;
            $categoriesWithValue[$categoryId]['value'] += $productValue;
        }
        
        // Sort categories by value (descending)
        uasort($categoriesWithValue, function ($a, $b) {
            return $b['value'] <=> $a['value'];
        });
        
        // Get top categories (limit to 5)
        $topCategories = array_slice($categoriesWithValue, 0, 5, true);
        
        return [
            'totalItems' => $totalItems,
            'totalValue' => $totalValue,
            'lowStockItems' => $lowStockItems,
            'reorderNeeded' => $reorderNeeded,
            'categoryCount' => count($categoriesWithValue),
            'topCategories' => $topCategories,
            'categoriesWithValue' => $categoriesWithValue,
        ];
    }
    
    public function getProductInventory(int $productId): ?Inventory
    {
        return Inventory::with('product')
            ->where('product_id', $productId)
            ->first();
    }
    
    /**
     * Get products with low stock
     *
     * @param int $limit
     * @return Collection
     */
    public function getLowStockInventory(int $limit = 10): Collection
    {
        return Inventory::with('product')
            ->join('products', 'inventory.product_id', '=', 'products.id')
            ->whereRaw('inventory.quantity <= products.reorder_level')
            ->where('products.reorder_level', '>', 0)
            ->limit($limit)
            ->get();
    }
    
    /**
     * Create an inventory adjustment
     *
     * @param array $data
     * @return InventoryAdjustment
     * @throws Exception
     */
    public function createAdjustment(array $data): InventoryAdjustment
    {
        // Validate required data
        if (empty($data['id']) || 
            empty($data['adjustment_type']) || 
            !isset($data['quantity']) ||
            empty($data['reason'])) {
            throw new Exception('Missing required adjustment data');
        }
        
        try {
            DB::beginTransaction();
            
            $product = Product::findOrFail($data['id']);
            $inventory = $this->getOrCreateInventory($data['id']);
            
            // Create adjustment data
            $adjustmentData = [
                'product_id' => $data['id'],
                'user_id' => Auth::id(),
                'adjustment_type' => $data['adjustment_type'],
                'quantity' => $data['quantity'],
                'quantity_before' => $inventory->quantity,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
            ];
            
            // Determine if this is positive or negative adjustment
            $isPositive = in_array($data['adjustment_type'], [
                InventoryAdjustment::TYPE_ADDITION,
                InventoryAdjustment::TYPE_RETURN
            ]);
            
            $currentProductQuantity = $product->quantity ?? 0;

            if ($isPositive) {
                $inventory->incrementQuantity($data['quantity']);
                $product->quantity = $currentProductQuantity + $data['quantity'];
            } else {
                 if ($currentProductQuantity < $data['quantity']) {
                    throw new Exception('Insufficient inventory for reduction');
                }
    
                $inventory->decrementQuantity($data['quantity']);
                $product->quantity = $currentProductQuantity - $data['quantity'];
            }
            $product->save();
            // Set quantity after in adjustment data
            $adjustmentData['quantity_after'] = $inventory->quantity;
            
            // Create adjustment record
            $adjustment = InventoryAdjustment::create($adjustmentData);
            
            // Create inventory log
            $this->createInventoryLog(
                $data['id'],
                $isPositive ? InventoryLog::TYPE_ADJUSTMENT_IN : InventoryLog::TYPE_ADJUSTMENT_OUT,
                InventoryLog::REF_ADJUSTMENT,
                $adjustment->id,
                $data['quantity'],
                $adjustmentData['quantity_before'],
                $adjustmentData['quantity_after'],
                null,
                $data['notes'] ?? null
            );
            
            DB::commit();
            return $adjustment;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get all inventory adjustments
     *
     * @param array $filters
     * @return Collection
     */
    public function getAdjustments(array $filters = []): Collection
    {
        $query = InventoryAdjustment::with(['product', 'user']);
        
        // Apply product filter if provided
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        
        // Apply type filter if provided
        if (!empty($filters['adjustment_type'])) {
            $query->where('adjustment_type', $filters['adjustment_type']);
        }
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('product_name', 'like', "%{$search}%")
                        ->orWhere('product_code', 'like', "%{$search}%");
                  });
            });
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
    
    /**
     * Get product adjustments
     *
     * @param int $productId
     * @return Collection
     */
    public function getProductAdjustments(int $productId): Collection
    {
        return InventoryAdjustment::with(['user'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get inventory logs
     *
     * @param array $filters
     * @return Collection
     */
    public function getInventoryLogs(array $filters = []): Collection
    {
        $query = InventoryLog::with(['product', 'user']);
        
        // Apply product filter if provided
        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }
        
        // Apply transaction type filter if provided
        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        
        // Apply reference filter if provided
        if (!empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
            
            if (!empty($filters['reference_id'])) {
                $query->where('reference_id', $filters['reference_id']);
            }
        }
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
    
    /**
     * Get product inventory logs
     *
     * @param int $productId
     * @return Collection
     */
    public function getProductInventoryLogs(int $productId): Collection
    {
        return InventoryLog::with(['user'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get product transactions (purchase, sales, etc.)
     *
     * @param int $productId
     * @return Collection
     */
    public function getProductTransactions(int $productId): Collection
    {
        return InventoryLog::with(['user'])
            ->where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public static function getReceivingReportsForProduct($productId) : Collection
    {
        return PurchaseOrderReceivedItem::where('product_id', $productId)
            ->with([
                'purchaseOrder',
                'purchaseOrder.purchaseOrder',
                'purchaseOrder.purchaseOrder.supplier'
            ])
            ->get()
            ->map(function ($receivedItem) {
                // Check if relationships exist before accessing their properties
                $purchaseOrder = $receivedItem->purchaseOrder;
                
                if (!$purchaseOrder) {
                    return [
                        'batch_number' => 'N/A',
                        'invoice' => 'N/A',
                        'quantity_received' => $receivedItem->received_quantity,
                        'payment_status' => 'N/A',
                        'supplier' => 'N/A',
                        'distribution_price' => $receivedItem->distribution_price,
                        'received_at' => $receivedItem->created_at ? $receivedItem->created_at->format('Y-m-d') : 'N/A',
                        'received_item_id' => $receivedItem->received_item_id
                    ];
                }
                
                $po = $purchaseOrder->purchaseOrder;
                $supplier = $po && $po->supplier ? $po->supplier->supplier_name: 'N/A';
                
                return [
                    'batch_number' => $purchaseOrder->batch_number ?? 'N/A',
                    'invoice' => $purchaseOrder->invoice ?? 'N/A',
                    'quantity_received' => $receivedItem->received_quantity,
                    'payment_status' => isset($purchaseOrder->is_paid) ? ($purchaseOrder->is_paid ? 'Paid' : 'Unpaid') : 'N/A',
                    'supplier' => $supplier,
                    'distribution_price' => $receivedItem->distribution_price,
                    'received_at' => $purchaseOrder->created_at ? $purchaseOrder->created_at->format('Y-m-d') : 'N/A',
                    'received_item_id' => $receivedItem->received_item_id
                ];
            });
    }
    
    /**
     * Create a new inventory count session
     *
     * @param array $data
     * @return InventoryCount
     * @throws Exception
     */
    public function createInventoryCount(array $data): InventoryCount
    {
        try {
            DB::beginTransaction();
            
            // Create inventory count
            $count = InventoryCount::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => InventoryCount::STATUS_DRAFT,
                'created_by' => Auth::id()
            ]);
            
            // Create count items
            if (!empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    // Skip if product ID or counted quantity not provided
                    if (empty($item['product_id']) || !isset($item['counted_quantity'])) {
                        continue;
                    }
                    
                    // Get inventory record
                    $inventory = $this->getOrCreateInventory($item['product_id']);
                    
                    // Create count item
                    $count->items()->create([
                        'product_id' => $item['product_id'],
                        'system_quantity' => $inventory->quantity,
                        'counted_quantity' => $item['counted_quantity'],
                        'notes' => $item['notes'] ?? null
                    ]);
                }
            }
            
            DB::commit();
            return $count->load('items');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Update an inventory count
     *
     * @param int $countId
     * @param array $data
     * @return InventoryCount
     * @throws Exception
     */
    public function updateInventoryCount(int $countId, array $data): InventoryCount
    {
        try {
            DB::beginTransaction();
            
            // Get the count
            $count = InventoryCount::findOrFail($countId);
            
            // Check if count is editable
            if (!$count->isEditable()) {
                throw new Exception('Inventory count cannot be updated because it has been finalized or cancelled');
            }
            
            // Update count data
            $count->update([
                'name' => $data['name'] ?? $count->name,
                'description' => $data['description'] ?? $count->description,
                'status' => InventoryCount::STATUS_IN_PROGRESS
            ]);
            
            // Update or create count items
            if (!empty($data['items']) && is_array($data['items'])) {
                // Track processed items
                $processedIds = [];
                
                foreach ($data['items'] as $item) {
                    // Skip if product ID not provided
                    if (empty($item['product_id'])) {
                        continue;
                    }
                    
                    // Check if item exists for this product
                    $countItem = $count->items()->where('product_id', $item['product_id'])->first();
                    
                    if ($countItem) {
                        // Update existing item
                        if (isset($item['counted_quantity'])) {
                            $countItem->update([
                                'counted_quantity' => $item['counted_quantity'],
                                'notes' => $item['notes'] ?? $countItem->notes
                            ]);
                        }
                        
                        $processedIds[] = $countItem->id;
                    } else {
                        // Create new item if counted quantity provided
                        if (isset($item['counted_quantity'])) {
                            // Get inventory record
                            $inventory = $this->getOrCreateInventory($item['product_id']);
                            
                            // Create count item
                            $newItem = $count->items()->create([
                                'product_id' => $item['product_id'],
                                'system_quantity' => $inventory->quantity,
                                'counted_quantity' => $item['counted_quantity'],
                                'notes' => $item['notes'] ?? null
                            ]);
                            
                            $processedIds[] = $newItem->id;
                        }
                    }
                }
                
                // Delete items not in the updated list
                if (!empty($processedIds)) {
                    $count->items()->whereNotIn('id', $processedIds)->delete();
                }
            }
            
            DB::commit();
            return $count->load('items');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Finalize an inventory count and apply discrepancies
     *
     * @param int $countId
     * @return InventoryCount
     * @throws Exception
     */
    public function finalizeInventoryCount(int $countId): InventoryCount
    {
        try {
            DB::beginTransaction();
            
            // Get the count with items
            $count = InventoryCount::with('items.product')->findOrFail($countId);
            
            // Check if count is editable
            if (!$count->isEditable()) {
                throw new Exception('Inventory count cannot be finalized because it has already been finalized or cancelled');
            }
            
            // Get items with discrepancies
            $discrepancyItems = $count->getDiscrepancyItems();
            
            // Process each discrepancy
            foreach ($discrepancyItems as $item) {
                $inventory = $this->getOrCreateInventory($item->product_id);
                $difference = $item->counted_quantity - $item->system_quantity;
                
                // Skip if no difference
                if ($difference == 0) {
                    continue;
                }
                
                // Determine adjustment type
                $adjustmentType = $difference > 0 
                    ? InventoryAdjustment::TYPE_CORRECTION 
                    : InventoryAdjustment::TYPE_CORRECTION;
                
                // Create adjustment record
                $adjustment = InventoryAdjustment::create([
                    'product_id' => $item->product_id,
                    'user_id' => Auth::id(),
                    'adjustment_type' => $adjustmentType,
                    'quantity' => abs($difference),
                    'quantity_before' => $inventory->quantity,
                    'reason' => 'Inventory count adjustment',
                    'notes' => "Adjustment from inventory count #{$count->id}: " . ($item->notes ?: 'No notes')
                ]);
                
                // Update inventory
                if ($difference > 0) {
                    $inventory->incrementQuantity(abs($difference));
                } else {
                    $inventory->decrementQuantity(abs($difference));
                }
                
                // Update adjustment's quantity_after
                $adjustment->update(['quantity_after' => $inventory->quantity]);
                
                // Create inventory log
                $this->createInventoryLog(
                    $item->product_id,
                    $difference > 0 ? InventoryLog::TYPE_ADJUSTMENT_IN : InventoryLog::TYPE_ADJUSTMENT_OUT,
                    InventoryLog::REF_INVENTORY_COUNT,
                    $count->id,
                    abs($difference),
                    $inventory->quantity - $difference, // Before
                    $inventory->quantity, // After
                    null,
                    "Inventory count adjustment: " . ($item->notes ?: 'No notes')
                );
            }
            
            // Mark count as finalized
            $count->finalize(Auth::id());
            
            DB::commit();
            return $count->load('items');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Cancel an inventory count
     *
     * @param int $countId
     * @return InventoryCount
     * @throws Exception
     */
    public function cancelInventoryCount(int $countId): InventoryCount
    {
        try {
            DB::beginTransaction();
            
            // Get the count
            $count = InventoryCount::findOrFail($countId);
            
            // Check if count is editable
            if (!$count->isEditable()) {
                throw new Exception('Inventory count cannot be cancelled because it has already been finalized or cancelled');
            }
            
            // Mark count as cancelled
            $count->cancel();
            
            DB::commit();
            return $count;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get all inventory counts
     *
     * @param array $filters
     * @return Collection
     */
    public function getInventoryCounts(array $filters = []): Collection
    {
        $query = InventoryCount::with(['creator']);
        
        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Apply date range filter if provided
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
    
    /**
     * Get inventory count by ID
     *
     * @param int $countId
     * @return InventoryCount
     */
    public function getInventoryCount(int $countId): InventoryCount
    {
        return InventoryCount::with(['items.product', 'creator', 'finalizer'])
            ->findOrFail($countId);
    }
    
    /**
     * Get inventory warnings (low stock, items that need recounting, etc.)
     *
     * @return array
     */
    public function getInventoryWarnings(): array
    {
        // Get low stock items
        $lowStock = $this->getLowStockInventory();
        
        // Get items that need recounting
        $needRecount = Inventory::with('product')
            ->where('recount_needed', true)
            ->get();
        
        // Get out of stock items
        $outOfStock = Inventory::with('product')
            ->where('quantity', 0)
            ->whereHas('product', function($q) {
                $q->where('reorder_level', '>', 0);
            })
            ->get();
        
        return [
            'low_stock' => $lowStock,
            'need_recount' => $needRecount,
            'out_of_stock' => $outOfStock,
            'low_stock_count' => $lowStock->count(),
            'need_recount_count' => $needRecount->count(),
            'out_of_stock_count' => $outOfStock->count()
        ];
    }
    
    /**
     * Get or create inventory record for a product
     *
     * @param int $productId
     * @return Inventory
     */
    protected function getOrCreateInventory(int $productId): Inventory
    {
        $inventory = Inventory::where('product_id', $productId)->first();
        
        if (!$inventory) {
            $inventory = Inventory::create([
                'product_id' => $productId,
                'quantity' => 0,
                'avg_cost_price' => 0,
                'recount_needed' => false
            ]);
        }
        
        return $inventory;
    }
    
    /**
     * Create an inventory log entry
     *
     * @param int $productId
     * @param string $transactionType
     * @param string $referenceType
     * @param int|null $referenceId
     * @param float $quantity
     * @param float $quantityBefore
     * @param float $quantityAfter
     * @param float|null $costPrice
     * @param string|null $notes
     * @return InventoryLog
     */
    protected function createInventoryLog(
        int $productId,
        string $transactionType,
        string $referenceType,
        ?int $referenceId,
        float $quantity,
        float $quantityBefore,
        float $quantityAfter,
        ?float $costPrice = null,
        ?string $notes = null
    ): InventoryLog {
        return InventoryLog::create([
            'product_id' => $productId,
            'user_id' => Auth::id(),
            'transaction_type' => $transactionType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'cost_price' => $costPrice,
            'notes' => $notes
        ]);
    }
}