<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\PurchaseOrderReceivedItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SaleService
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get all sales with optional filtering
     *
     * @param array $filters
     * @param int|null $perPage
     * @return Collection|LengthAwarePaginator
     */
    public function getAllSales(array $filters = [], ?int $perPage = null)
    {
        $query = Sale::with(['customer', 'user', 'items.product']);
        
        // Handle status filter with special cases
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'all') {
                // Don't apply any status filter - get all statuses
            } elseif ($filters['status'] === 'all_except_cancelled') {
                // Special filter for profit reports - exclude cancelled sales
                $query->where('status', '!=', Sale::STATUS_CANCELLED);
            } elseif ($filters['status'] === 'active') {
                // Active sales (not cancelled or returned)
                $query->whereNotIn('status', [Sale::STATUS_CANCELLED, Sale::STATUS_RETURNED]);
            } elseif ($filters['status'] === 'paid') {
                // Paid sales (completed or partially paid)
                $query->whereIn('status', [Sale::STATUS_COMPLETED, Sale::STATUS_PARTIALLY_PAID]);
            } elseif ($filters['status'] === 'unpaid') {
                // Unpaid sales (pending or unpaid)
                $query->whereIn('status', [Sale::STATUS_PENDING, Sale::STATUS_UNPAID]);
            } else {
                // Specific status filter
                $query->where('status', $filters['status']);
            }
        } else {
            // Default behavior: exclude cancelled sales if no status filter is specified
            $query->where('status', '!=', Sale::STATUS_CANCELLED);
        }
        
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }
        
        if (!empty($filters['customer_type'])) {
            $query->where('customer_type', $filters['customer_type']);
        }
        
        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }
        
        if (!empty($filters['is_delivered'])) {
            $query->where('is_delivered', filter_var($filters['is_delivered'], FILTER_VALIDATE_BOOLEAN));
        }
        
        if (!empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhere('total', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('customer_name', 'like', "%{$search}%")
                                ->orWhere('contact_number', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }
        
        // Sort results
        $sortBy = $filters['sort_by'] ?? 'order_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get a single sale by ID
     *
     * @param int $id
     * @return Sale
     */
    public function getSaleById(int $id): Sale
    {
        return Sale::with([
            'customer', 
            'user', 
            'items.product.category',
            'items.product.attribute',
            'returns.items',
            'payments'
        ])->findOrFail($id);
    }
    
    /**
     * Get a single sale by invoice number
     *
     * @param string $invoiceNumber
     * @return Sale
     */
    public function getSaleByInvoiceNumber(string $invoiceNumber): Sale
    {
        return Sale::with([
            'customer', 
            'user', 
            'items.product',
            'returns.items',
            'payments'
        ])->where('invoice_number', $invoiceNumber)->firstOrFail();
    }
    /**
     * Create a new sale
     *
     * @param array $data
     * @return Sale
     * @throws Exception
     */
    public function createSale(array $data): Sale
    {
        try {
            DB::beginTransaction();
            
            // Generate invoice number if not provided
            if (empty($data['invoice_number'])) {
                $data['invoice_number'] = Sale::generateInvoiceNumber();
            }
            
            // Set user ID to current user if not provided
            if (empty($data['user_id'])) {
                $data['user_id'] = Auth::id();
            }
            
            // Handle customer data - create or get customer
            if (!empty($data['customer'])) {
                $customerData = $data['customer'];
                
                if (!empty($customerData['id'])) {
                    $customer = Customer::find($customerData['id']);
                    $data['customer_id'] = $customer->id;
                } else if (!empty($customerData['customer_name'])) {
                    // Create a new customer
                    $customer = Customer::create([
                        'customer_name' => $customerData['customer_name'],
                        'contact_number' => $customerData['contact_number'] ?? null,
                        'email' => $customerData['email'] ?? null,
                        'address' => $customerData['address'] ?? null,
                        'city' => $customerData['city'] ?? null,
                    ]);
                    $data['customer_id'] = $customer->id;
                }
            }
            
            // Set default values
            $data['cogs'] = 0;
            $data['profit'] = 0;
            $data['total'] = 0;
            $delivery_fee = $data['delivery_fee'] ?? 0;
            $cutting_charges =  $data['cutting_charges'] ?? 0;
            // Create the sale
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'status' => Sale::STATUS_PENDING,
                'customer_type' => $data['customer_type'] ?? Sale::TYPE_REGULAR,
                'payment_method' => $data['payment_method'] ?? Sale::PAYMENT_CASH,
                'order_date' => Carbon::parse($data['order_date'])->format('Y-m-d'),     
                'delivery_date' => Carbon::parse($data['delivery_date'])->format('Y-m-d'),
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'phone' => $data['phone'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'amount_received' => $data['amount_received'] ?? 0,
                'change' => $data['change'] ?? 0,
                'is_delivered' => false,
                'term_days' => $data['term_days'] ?? 0,
                'delivery_fee' => $delivery_fee,
                'cutting_charges' => $cutting_charges,

            ]);

            
            $other_charges = $delivery_fee + $cutting_charges;
            
            // Handle sale items
            if (!empty($data['items']) && is_array($data['items'])) {
                $this->processSaleItems($sale, $data['items'], $other_charges);
            }
            
            // Handle payment if amount received > 0
            if (!empty($data['amount_received']) && $data['amount_received'] > 0) {
                $sale->payments()->create([
                    'payment_method' => $data['payment_method'] ?? Sale::PAYMENT_CASH,
                    'amount' => $data['amount_received'],
                    'payment_date' => now(),
                    'received_by' => Auth::id(),
                    'remarks' => 'Initial payment with sale'
                ]);
            }
            

            
            DB::commit();

            event(new \App\Events\SaleCreated($sale));

            return $this->getSaleById($sale->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    
    
/**
     * Process and add sale items
     *
     * @param Sale $sale
     * @param array $items
     * @return void
     */
    // protected function processSaleItems(Sale $sale, array $items): void
    // {
    //     $totalCogs = 0;
    //     $totalSold = 0;
        
    //     foreach ($items as $item) {
    //         // Get the product
    //         $product = Product::findOrFail($item['product_id']);
            
    //         // Check inventory
    //         $inventory = Inventory::where('product_id', $product->id)->first();
    //         if (!$inventory || $inventory->quantity < $item['quantity']) {
    //             throw new Exception("Insufficient inventory for product: {$product->product_name}");
    //         }
            
    //         // Calculate prices
    //         $distributionPrice = $item['distribution_price'] ?? $product->cost_price ?? 0;
    //         $soldPrice = $item['sold_price'] ?? $this->getPriceByCustomerType($product, $sale->customer_type);
    //         $discount = $item['discount'] ?? 0;
            
    //         // Calculate totals
    //         $totalDistributionPrice = $distributionPrice * $item['quantity'];
    //         $totalSoldBeforeDiscount = $soldPrice * $item['quantity'];
    //         $discountAmount = ($discount / 100) * $totalSoldBeforeDiscount;
    //         $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
            
    //         // Create the sale item
    //         $saleItem = $sale->items()->create([
    //             'product_id' => $product->id,
    //             'distribution_price' => $distributionPrice,
    //             'sold_price' => $soldPrice,
    //             'price_type' => $item['price_type'],
    //             'quantity' => $item['quantity'],
    //             'total_distribution_price' => $totalDistributionPrice,
    //             'total_sold_price' => $totalSoldPrice,
    //             'discount' => $discount,
    //             'is_discount_approved' => $item['is_discount_approved'] ?? false,
    //             'approved_by' => $item['approved_by'] ?? null
    //         ]);
            
    //         // Update running totals
    //         $totalCogs += $totalDistributionPrice;
    //         $totalSold += $totalSoldPrice;
            
    //         // Update inventory
    //         $this->updateInventoryOnSale($product->id, $item['quantity'], $sale->id);
    //     }
        
    //     // Update sale totals
    //     $profit = $totalSold - $totalCogs;
    //     $sale->update([
    //         'cogs' => $totalCogs,
    //         'profit' => $profit,
    //         'total' => $totalSold
    //     ]);
    // }

    protected function processSaleItems(Sale $sale, array $items, $other_charges = 0): void
    {
        $totalCogs = 0;
        $totalSold = 0;
        
        foreach ($items as $item) {
            // Get the product
            $product = Product::findOrFail($item['product_id']);
            
            // Check inventory
            $inventory = Inventory::where('product_id', $product->id)->first();
            if (!$inventory || $inventory->quantity < $item['quantity']) {
                throw new Exception("Insufficient inventory for product: {$product->product_name}");
            }
            
            // Process FIFO costing
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
                
                // Update the sold quantity in this batch
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
            $distributionPrice = $item['quantity'] > 0 ? $totalFifoCost / $item['quantity'] : 0;
            
            // Calculate selling price logic
            $soldPrice = $item['sold_price'] ?? $this->getPriceByCustomerType($product, $sale->customer_type);
            $discount = $item['discount'] ?? 0;
            
            // Calculate totals
            $totalSoldBeforeDiscount = $soldPrice * $item['quantity'];
            $discountAmount = ($discount / 100) * $totalSoldBeforeDiscount;
            $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
            
            // Create the sale item with FIFO cost
            $saleItem = $sale->items()->create([
                'product_id' => $product->id,
                'distribution_price' => $distributionPrice,
                'sold_price' => $soldPrice,
                'price_type' => $item['price_type'],
                'quantity' => $item['quantity'],
                'total_distribution_price' => $totalFifoCost,
                'total_sold_price' => $totalSoldPrice,
                'discount' => $discount,
                'composition'=> $item['composition'] ?? null,
                'is_discount_approved' => $item['is_discount_approved'] ?? false,
                'approved_by' => $item['approved_by'] ?? null
            ]);

            Log::info($item);
            
            // Update running totals
            $totalCogs += $totalFifoCost;
            $totalSold += $totalSoldPrice;
            
            // Update inventory
            $this->updateInventoryOnSale($product->id, $item['quantity'], $sale->id);
        }
        
        // Update sale totals
        $profit = ($totalSold + $other_charges) - $totalCogs;
        $sale->update([
            'cogs' => $totalCogs,
            'profit' => $profit,
            'total' => $totalSold + $other_charges,
        ]);
    }
    
    /**
     * Update inventory when a sale is made
     *
     * @param int $productId
     * @param int $quantity
     * @return void
     */
    // protected function updateInventoryOnSale(int $productId, int $quantity, int $saleId): void
    // {
    //     // Get inventory
    //     $inventory = Inventory::where('product_id', $productId)->first();
    //     if (!$inventory) {
    //         throw new Exception("Inventory record not found for product ID: {$productId}");
    //     }
        
    //     // Update inventory quantity
    //     $currentQuantity = $inventory->quantity;
    //     $inventory->decrementQuantity($quantity);
        
    //     // Create inventory log
    //     InventoryLog::create([
    //         'product_id' => $productId,
    //         'user_id' => Auth::id(),
    //         'transaction_type' => InventoryLog::TYPE_SALES,
    //         'reference_type' => 'sale',
    //         'reference_id' => $saleId,
    //         'quantity' => $quantity,
    //         'quantity_before' => $currentQuantity,
    //         'quantity_after' => $inventory->quantity,
    //         'notes' => "Product sold in sale #{$saleId}"
    //     ]);

    //      $notificationService = app(NotificationService::class);
    //      $notificationService->checkInventoryLevels($productId, $inventory->quantity);
    // }
    

    protected function updateInventoryOnSale(int $productId, int $quantity, int $saleId): void
    {
        // Get inventory
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            throw new Exception("Inventory record not found for product ID: {$productId}");
        }
        
        // Update inventory quantity
        $currentQuantity = $inventory->quantity;
        $newQuantity = max(0, $currentQuantity - $quantity);
        $inventory->quantity = $newQuantity;
        $inventory->save();
        
        // Create inventory log
        InventoryLog::create([
            'product_id' => $productId,
            'user_id' => Auth::id(),
            'transaction_type' => InventoryLog::TYPE_SALES,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'quantity' => $quantity,
            'quantity_before' => $currentQuantity,
            'quantity_after' => $newQuantity,
            'notes' => "Product sold in sale #{$saleId}"
        ]);
        
        // Get product details for notifications
        $product = Product::findOrFail($productId);
        
        // Check inventory levels and create notifications if needed
        if ($newQuantity === 0) {
            // Get all admin users to notify about out of stock
            $adminUsers = User::where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title' => 'Out of Stock Alert',
                    'message' => "Product {$product->product_name} is now out of stock.",
                    'type' => Notification::TYPE_INVENTORY_OUT,
                    'reference_type' => 'product',
                    'reference_id' => $productId,
                    'is_read' => false,
                    'data' => [
                        'product_id' => $productId,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $newQuantity
                    ]
                ]);
            }
        } elseif ($newQuantity <= $product->reorder_level) {
            // Get all admin users to notify about low stock
            $adminUsers = User::where('role', 'admin')->get();
            foreach ($adminUsers as $admin) {
                Notification::create([
                    'user_id' => $admin->id,
                    'title' => 'Low Stock Alert',
                    'message' => "Product {$product->product_name} is below reorder level ({$product->reorder_level}).",
                    'type' => Notification::TYPE_INVENTORY_LOW,
                    'reference_type' => 'product',
                    'reference_id' => $productId,
                    'is_read' => false,
                    'data' => [
                        'product_id' => $productId,
                        'product_name' => $product->product_name,
                        'product_code' => $product->product_code,
                        'quantity' => $newQuantity,
                        'reorder_level' => $product->reorder_level
                    ]
                ]);
            }
        }
    }
    /**
     * Get price based on customer type
     *
     * @param Product $product
     * @param string $customerType
     * @return float
     */
    protected function getPriceByCustomerType(Product $product, string $customerType): float
    {
        $currentPrice = $product->currentPrice;
        
        if (!$currentPrice) {
            return 0;
        }
        
        switch ($customerType) {
            case Sale::TYPE_WALK_IN:
                return $currentPrice->walk_in_price;
            case Sale::TYPE_WHOLESALE:
                return $currentPrice->wholesale_price;
            case Sale::TYPE_REGULAR:
            default:
                return $currentPrice->regular_price;
        }
    }
    
    /**
     * Update a sale
     *
     * @param int $id
     * @param array $data
     * @return Sale
     * @throws Exception
     */
    public function updateSale(int $id, array $data): Sale
    {
        try {
            DB::beginTransaction();
            
            // Get the sale
            $sale = $this->getSaleById($id);
            
            // Check if sale can be updated
            if (in_array($sale->status, [Sale::STATUS_COMPLETED, Sale::STATUS_CANCELLED])) {
                throw new Exception('Cannot update a completed or cancelled sale');
            }
            
            // Update sale data
            $sale->update([
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'customer_type' => $data['customer_type'] ?? $sale->customer_type,
                'payment_method' => $data['payment_method'] ?? $sale->payment_method,
                'delivery_date' => $data['delivery_date'] ?? $sale->delivery_date,
                'address' => $data['address'] ?? $sale->address,
                'city' => $data['city'] ?? $sale->city,
                'phone' => $data['phone'] ?? $sale->phone,
                'remarks' => $data['remarks'] ?? $sale->remarks,
                'is_delivered' => $data['is_delivered'] ?? $sale->is_delivered
            ]);
            
            DB::commit();
            
            return $this->getSaleById($sale->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Update sale payment information
     *
     * @param int $id
     * @param array $data
     * @return Sale
     * @throws Exception
     */
    public function updateSalePayment(int $id, array $data): Sale
    {
        try {
            DB::beginTransaction();
            
            // Get the sale
            $sale = $this->getSaleById($id);
            
            // Check if payment can be updated
            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('Cannot update payment for a cancelled sale');
            }
            
            // Create a payment record
            $sale->payments()->create([
                'payment_method' => $data['payment_method'] ?? Sale::PAYMENT_CASH,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'] ?? now(),
                'reference_number' => $data['reference_number'] ?? null,
                'received_by' => $data['received_by'] ?? Auth::id(),
                'remarks' => $data['remarks'] ?? null
            ]);
            
            // Update the total amount received
            $totalReceived = $sale->payments()->sum('amount');
            $sale->update([
                'amount_received' => $totalReceived,
                'change' => max(0, $totalReceived - $sale->total)
            ]);
            
            
            DB::commit();
            
            return $this->getSaleById($sale->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Cancel a sale
     *
     * @param int $id
     * @param string $reason
     * @return Sale
     * @throws Exception
     */
    public function cancelSale(int $id, string $reason = ''): Sale
    {
        try {
            DB::beginTransaction();
            
            // Get the sale
            $sale = $this->getSaleById($id);
            
            // Check if sale can be cancelled
            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('Sale is already cancelled');
            }
            
            if ($sale->returns()->exists()) {
                throw new Exception('Cannot cancel a sale with returns');
            }
            
            // Return inventory
            foreach ($sale->items as $item) {
                $this->returnInventoryOnCancel($item->product_id, $item->quantity);
            }
            
            // Update sale status
            $sale->update([
                'status' => Sale::STATUS_CANCELLED,
                'remarks' => $reason ? $sale->remarks . ' | Cancelled: ' . $reason : $sale->remarks
            ]);
            
            DB::commit();
            
            return $this->getSaleById($sale->id);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Return inventory when a sale is cancelled
     *
     * @param int $productId
     * @param int $quantity
     * @return void
     */
    protected function returnInventoryOnCancel(int $productId, int $quantity): void
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
            'reference_type' => 'sale_cancel',
            'reference_id' => null,
            'quantity' => $quantity,
            'quantity_before' => $oldQuantity,
            'quantity_after' => $inventory->quantity,
            'notes' => 'Sale cancelled'
        ]);
        
        // Update sold quantities in batches (LIFO for cancellation - reverse of FIFO for sales)
        // We restore quantities to the most recent batches first when canceling
        $remainingToRestore = $quantity;
        
        // Get batches that have been sold, starting with most recent (for cancellation, we use LIFO)
        $batches = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->where('sold_quantity', '>', 0)
            ->orderBy('created_at', 'desc') // Newest first for cancellation
            ->get();
        
        foreach ($batches as $batch) {
            if ($remainingToRestore <= 0) break;
            
            $soldQuantity = $batch->sold_quantity;
            $restoreQuantity = min($soldQuantity, $remainingToRestore);
            
            // Reduce sold quantity for this batch
            $batch->sold_quantity -= $restoreQuantity;
            // Update fully_consumed flag
            $batch->fully_consumed = ($batch->received_quantity <= $batch->sold_quantity);
            $batch->save();
            
            $remainingToRestore -= $restoreQuantity;
        }
        
        // If we couldn't restore all quantities (unusual), log an error
        if ($remainingToRestore > 0) {
            // Log::warning("Could not fully restore sold quantities for cancelled sale. Product ID: {$productId}, Remaining: {$remainingToRestore}");
        }
    }
    
    /**
     * Mark a sale as delivered
     *
     * @param int $id
     * @return Sale
     */
    public function markAsDelivered(int $id): Sale
    {
        $sale = $this->getSaleById($id);
        $sale->update(['is_delivered' => true]);
        return $sale;
    }
    
    public function updateDeliveryDate(int $id, string $deliveryDate): Sale
    {
        $sale = $this->getSaleById($id);
        
        // Optional: Check if sale can be updated (not cancelled, completed, etc.)
        if (in_array($sale->status, ['cancelled', 'completed', 'returned'])) {
            throw new Exception('Cannot update delivery date for this sale status');
        }
        
        $sale->update(['delivery_date' => $deliveryDate]);
        
        return $sale;
    }
    /**
     * Get sales statistics
     *
     * @param array $filters
     * @return array
     */
    public function getSalesStats(array $filters = []): array
    {
        $query = Sale::query();
        
        // Apply date filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('order_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('order_date', '<=', $filters['date_to']);
        }
        
        // Exclude cancelled sales
        $query->where('status', '!=', Sale::STATUS_CANCELLED);
        
        // Get statistics
        $totalSales = $query->count();
        $totalRevenue = $query->sum('total');
        $totalProfit = $query->sum('profit');
        $averageSaleValue = $totalSales > 0 ? $totalRevenue / $totalSales : 0;
        
        return [
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'total_profit' => $totalProfit,
            'average_sale_value' => $averageSaleValue,
            'profit_margin' => $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0
        ];
    }


    // In SaleService.php
    public function getCustomerPurchaseHistory(int $customerId, int $page = 1, int $perPage = 50): array
    {
        // Get the customer
        $customer = Customer::findOrFail($customerId);
        
        // Query to get all sales for this customer (for counting total)
        $salesQuery = Sale::where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED);
        
        // Get total count for pagination
        $totalItems = DB::table('sales')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
            ->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->count();
        
        // Get all sales with items (paginated)
        $sales = Sale::with([
            'items.product',
        ])
        ->where('sales.customer_id', $customerId)
        ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
        ->orderBy('order_date', 'desc')
        ->get();
        
        // Format the data to focus on items with pagination
        $allPurchaseItems = [];
        
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $allPurchaseItems[] = [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'order_date' => $sale->order_date,
                    'delivery_date' => $sale->delivery_date,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name ?? "Product #{$item->product_id}",
                    'product_code' => $item->product->product_code ?? "P-{$item->product_id}",
                    'quantity' => $item->quantity,
                    'price' => $item->sold_price,
                    'discount' => $item->discount,
                    'total' => $item->total_sold_price,
                    'total_sale_amount' => $sale->total,
                    'status' => $sale->status
                ];
            }
        }
        
        // Apply pagination to the collected items
        $offset = ($page - 1) * $perPage;
        $paginatedItems = array_slice($allPurchaseItems, $offset, $perPage);
        
        // Group items by invoice for "By Orders" tab
        $itemsByInvoice = [];
        foreach ($allPurchaseItems as $item) {
            $invoiceNumber = $item['invoice_number'];
            if (!isset($itemsByInvoice[$invoiceNumber])) {
                $itemsByInvoice[$invoiceNumber] = [];
            }
            $itemsByInvoice[$invoiceNumber][] = $item;
        }
        
        // Calculate summary statistics
        $totalOrders = count($itemsByInvoice);
        $totalQuantity = array_sum(array_column($allPurchaseItems, 'quantity'));
        $totalValue = array_sum(array_column($allPurchaseItems, 'total'));
        
        return [
            'customer' => $customer,
            'stats' => [
                'total_orders' => $totalOrders,
                'total_items' => count($allPurchaseItems),
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ],
            'items' => $paginatedItems,
            'items_by_invoice' => $itemsByInvoice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'has_more' => $totalItems > ($page * $perPage)
            ]
        ];
    }

    // For better performance, here's an optimized version that does proper pagination at the database level
    public function getCustomerPurchaseHistoryOptimized(int $customerId, int $page = 1, int $perPage = 50): array
    {
        // Get the customer
        $customer = Customer::findOrFail($customerId);
        
        // Count total items for pagination
        $totalItems = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
            ->count();
        
        // Get paginated items
        $paginatedItems = DB::table('sale_items')
            ->select([
                'sale_items.id as item_id',
                'sale_items.product_id',
                'sale_items.quantity',
                'sale_items.sold_price as price',
                'sale_items.discount',
                'sale_items.total_sold_price as total',
                'sales.id as sale_id',
                'sales.invoice_number',
                'sales.order_date',
                'sales.delivery_date',
                'sales.status',
                'products.product_name',
                'products.product_code'
            ])
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
            ->orderBy('sales.order_date', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($item) {
                // Format the data
                return [
                    'item_id' => $item->item_id,
                    'sale_id' => $item->sale_id,
                    'invoice_number' => $item->invoice_number,
                    'order_date' => $item->order_date,
                    'delivery_date' => $item->delivery_date,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name ?? "Product #{$item->product_id}",
                    'product_code' => $item->product_code ?? "P-{$item->product_id}",
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'discount' => $item->discount,
                    'total' => $item->total,
                    'status' => $item->status
                ];
            })
            ->toArray();
        
        // Get all items grouped by invoice (for the "By Orders" tab)
        $itemsByInvoice = DB::table('sale_items')
            ->select([
                'sale_items.id as item_id',
                'sale_items.product_id',
                'sale_items.quantity',
                'sale_items.sold_price as price',
                'sale_items.discount',
                'sale_items.total_sold_price as total',
                'sales.id as sale_id',
                'sales.invoice_number',
                'sales.order_date',
                'sales.delivery_date',
                'sales.status',
                'products.product_name',
                'products.product_code'
            ])
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
            ->orderBy('sales.order_date', 'desc')
            ->get()
            ->groupBy('invoice_number')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'item_id' => $item->item_id,
                        'sale_id' => $item->sale_id,
                        'invoice_number' => $item->invoice_number,
                        'order_date' => $item->order_date,
                        'delivery_date' => $item->delivery_date,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name ?? "Product #{$item->product_id}",
                        'product_code' => $item->product_code ?? "P-{$item->product_id}",
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'total' => $item->total,
                        'status' => $item->status
                    ];
                });
            })
            ->toArray();
        
        // Calculate statistics
        $statsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->where('sales.customer_id', $customerId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED);
        
        $totalQuantity = $statsQuery->sum('sale_items.quantity');
        $totalValue = $statsQuery->sum('sale_items.total_sold_price');
        $totalOrders = count($itemsByInvoice);
        
        return [
            'customer' => $customer,
            'stats' => [
                'total_orders' => $totalOrders,
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue
            ],
            'items' => $paginatedItems,
            'items_by_invoice' => $itemsByInvoice,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'has_more' => $totalItems > ($page * $perPage)
            ]
        ];
    }

    public function updateSaleWithItems(int $saleId, array $data): Sale
    {
        try {
            DB::beginTransaction();
            
            // Get the existing sale
            $sale = Sale::with(['items.product', 'customer'])->findOrFail($saleId);
            
            // Check if sale can be edited
            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('Cannot edit a cancelled sale');
            }
            
            if ($sale->status === Sale::STATUS_COMPLETED) {
                throw new Exception('Cannot edit a completed sale');
            }
            
            // Handle customer updates
            if (!empty($data['customer'])) {
                $customerData = $data['customer'];
                
                if (!empty($customerData['id'])) {
                    $customer = Customer::find($customerData['id']);
                    $data['customer_id'] = $customer->id;
                } else if (!empty($customerData['customer_name'])) {
                    // Create a new customer if needed
                    $customer = Customer::create([
                        'customer_name' => $customerData['customer_name'],
                        'contact_number' => $customerData['contact_number'] ?? null,
                        'email' => $customerData['email'] ?? null,
                        'address' => $customerData['address'] ?? null,
                        'city' => $customerData['city'] ?? null,
                    ]);
                    $data['customer_id'] = $customer->id;
                }
            }
            
            // Store original items for inventory restoration
            $originalItems = $sale->items->keyBy('id');
            
            // Update sale basic information
            $sale->update([
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'payment_method' => $data['payment_method'],
                'delivery_date' => Carbon::parse($data['delivery_date'])->format('Y-m-d'),
                'address' => $data['address'],
                'city' => $data['city'],
                'phone' => $data['phone'],
                'term_days' => $data['term_days'] ?? 0,
                'delivery_fee' => $data['delivery_fee'] ?? 0,
                'cutting_charges' => $data['cutting_charges'] ?? 0,
            ]);
            
            // Handle removed items first - restore FIFO batches
            if (!empty($data['removed_items'])) {
                foreach ($data['removed_items'] as $removedItemId) {
                    $removedItem = $originalItems->get($removedItemId);
                    if ($removedItem) {
                        // Restore FIFO batches for removed item
                        $this->restoreFifoBatchesForItem($removedItem);
                        // Restore inventory for removed item
                        $this->restoreInventoryForItem($removedItem);
                        // Delete the item
                        $removedItem->delete();
                    }
                }
            }
            
            // Process sale items with FIFO batch management
            $this->updateSaleItemsWithFifo($sale, $data['items'], $originalItems);
            
            // Recalculate totals
            $this->recalculateSaleTotals($sale);
            
            DB::commit();
            
            // Return updated sale with relationships
            return $this->getSaleById($sale->id);
            
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update sale items with FIFO batch management
     */
    private function updateSaleItemsWithFifo(Sale $sale, array $items, $originalItems)
    {
        $deliveryFee = $sale->delivery_fee ?? 0;
        $cuttingCharges = $sale->cutting_charges ?? 0;
        $otherCharges = $deliveryFee + $cuttingCharges;
        
        foreach ($items as $itemData) {
            $product = Product::with(['inventory'])->findOrFail($itemData['product_id']);
            
            if (!empty($itemData['id']) && $originalItems->has($itemData['id'])) {
                // Update existing item
                $existingItem = $originalItems->get($itemData['id']);
                $quantityDifference = $itemData['quantity'] - $existingItem->quantity;
                
                if ($quantityDifference != 0) {
                    // Handle FIFO batch adjustments for quantity changes
                    $this->adjustFifoBatchesForQuantityChange($existingItem, $quantityDifference);
                    
                    // Update inventory
                    $this->updateInventoryForQuantityChange($product, $quantityDifference);
                    
                    // Recalculate FIFO cost for the new quantity
                    $newFifoCost = $this->calculateFifoCostForQuantity($product->id, $itemData['quantity']);
                    $newDistributionPrice = $itemData['quantity'] > 0 ? $newFifoCost / $itemData['quantity'] : 0;
                    
                    // Update the sale item
                    $existingItem->update([
                        'quantity' => $itemData['quantity'],
                        'distribution_price' => $newDistributionPrice,
                        'total_distribution_price' => $newFifoCost,
                        'sold_price' => $itemData['sold_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'price_type' => $itemData['price_type'] ?? 'regular',
                        'composition' => $itemData['composition'] ?? null,
                    ]);
                    
                    // Recalculate total sold price
                    $totalSoldBeforeDiscount = $itemData['sold_price'] * $itemData['quantity'];
                    $discountAmount = (($itemData['discount'] ?? 0) / 100) * $totalSoldBeforeDiscount;
                    $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
                    
                    $existingItem->update(['total_sold_price' => $totalSoldPrice]);
                    

                    
                } else {
                    // Only price/discount changes, no quantity change
                    $existingItem->update([
                        'sold_price' => $itemData['sold_price'],
                        'discount' => $itemData['discount'] ?? 0,
                        'price_type' => $itemData['price_type'] ?? 'regular',
                        'composition' => $itemData['composition'] ?? null,
                    ]);
                    
                    // Recalculate total sold price
                    $totalSoldBeforeDiscount = $itemData['sold_price'] * $itemData['quantity'];
                    $discountAmount = (($itemData['discount'] ?? 0) / 100) * $totalSoldBeforeDiscount;
                    $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
                    
                    $existingItem->update(['total_sold_price' => $totalSoldPrice]);
                }
                
            } else {
                // Create new item with FIFO processing
                $newSaleItem = $this->createNewSaleItemWithFifo($sale, $product, $itemData);
                
            }
        }
    }

    /**
     * Restore FIFO batches when item is removed
     */
    private function restoreFifoBatchesForItem($saleItem)
    {
        $productId = $saleItem->product_id;
        $quantityToRestore = $saleItem->quantity;
        
        // Get batches that have sold quantities (reverse FIFO - newest first for restoration)
        $batches = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->where('sold_quantity', '>', 0)
            ->orderBy('created_at', 'desc') // Newest first for restoration
            ->get();
        
        $remainingToRestore = $quantityToRestore;
        
        foreach ($batches as $batch) {
            if ($remainingToRestore <= 0) break;
            
            $soldQuantity = $batch->sold_quantity;
            $restoreQuantity = min($soldQuantity, $remainingToRestore);
            
            // Reduce sold quantity for this batch
            $batch->sold_quantity -= $restoreQuantity;
            // Update fully_consumed flag
            $batch->fully_consumed = ($batch->received_quantity <= $batch->sold_quantity);
            $batch->save();
            
            $remainingToRestore -= $restoreQuantity;
            
            Log::info("Restored {$restoreQuantity} units to batch {$batch->id} for product {$productId}");
        }
    }

    /**
     * Adjust FIFO batches for quantity changes in existing items
     */
    private function adjustFifoBatchesForQuantityChange($saleItem, $quantityDifference)
    {
        $productId = $saleItem->product_id;
        
        if ($quantityDifference > 0) {
            // Quantity increased - allocate more from FIFO batches
            $this->allocateFromFifoBatches($productId, $quantityDifference);
        } else if ($quantityDifference < 0) {
            // Quantity decreased - restore to FIFO batches
            $quantityToRestore = abs($quantityDifference);
            $this->restoreToFifoBatches($productId, $quantityToRestore);
        }
    }

    /**
     * Allocate quantity from FIFO batches
     */
    private function allocateFromFifoBatches($productId, $quantityToAllocate)
    {
        // Get available batches ordered by received date (FIFO)
        $receivedItems = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->whereRaw('received_quantity > sold_quantity')
            ->orderBy('created_at', 'asc')
            ->get();
        
        $remainingToAllocate = $quantityToAllocate;
        
        foreach ($receivedItems as $receivedItem) {
            if ($remainingToAllocate <= 0) break;
            
            $availableQuantity = $receivedItem->received_quantity - $receivedItem->sold_quantity;
            $quantityFromBatch = min($availableQuantity, $remainingToAllocate);
            
            // Update the sold quantity in this batch
            $receivedItem->sold_quantity += $quantityFromBatch;
            $receivedItem->fully_consumed = ($receivedItem->received_quantity <= $receivedItem->sold_quantity);
            $receivedItem->save();
            
            $remainingToAllocate -= $quantityFromBatch;
            
            Log::info("Allocated {$quantityFromBatch} units from batch {$receivedItem->id} for product {$productId}");
        }
        
        if ($remainingToAllocate > 0) {
            throw new Exception("Insufficient inventory in FIFO batches for product ID: {$productId}. Remaining needed: {$remainingToAllocate}");
        }
    }

    /**
     * Restore quantity to FIFO batches (reverse FIFO)
     */
    private function restoreToFifoBatches($productId, $quantityToRestore)
    {
        // Get batches that have sold quantities (reverse FIFO - newest first for restoration)
        $batches = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->where('sold_quantity', '>', 0)
            ->orderBy('created_at', 'desc') // Newest first for restoration
            ->get();
        
        $remainingToRestore = $quantityToRestore;
        
        foreach ($batches as $batch) {
            if ($remainingToRestore <= 0) break;
            
            $soldQuantity = $batch->sold_quantity;
            $restoreQuantity = min($soldQuantity, $remainingToRestore);
            
            // Reduce sold quantity for this batch
            $batch->sold_quantity -= $restoreQuantity;
            // Update fully_consumed flag
            $batch->fully_consumed = ($batch->received_quantity <= $batch->sold_quantity);
            $batch->save();
            
            $remainingToRestore -= $restoreQuantity;
            
            Log::info("Restored {$restoreQuantity} units to batch {$batch->id} for product {$productId}");
        }
    }

    /**
     * Calculate FIFO cost for a specific quantity
     */
    private function calculateFifoCostForQuantity($productId, $quantity)
    {
        // Get available batches ordered by received date (FIFO)
        $receivedItems = PurchaseOrderReceivedItem::where('product_id', $productId)
            ->whereRaw('received_quantity > sold_quantity')
            ->orderBy('created_at', 'asc')
            ->get();
        
        $remainingToAllocate = $quantity;
        $totalFifoCost = 0;
        
        foreach ($receivedItems as $receivedItem) {
            if ($remainingToAllocate <= 0) break;
            
            $availableQuantity = $receivedItem->received_quantity - $receivedItem->sold_quantity;
            $quantityFromBatch = min($availableQuantity, $remainingToAllocate);
            $costFromBatch = $quantityFromBatch * $receivedItem->distribution_price;
            
            $totalFifoCost += $costFromBatch;
            $remainingToAllocate -= $quantityFromBatch;
        }
        
        // Handle any remaining quantity using latest cost as fallback
        if ($remainingToAllocate > 0) {
            $latestItem = PurchaseOrderReceivedItem::where('product_id', $productId)
                ->orderBy('created_at', 'desc')
                ->first();
            
            $fallbackCost = $latestItem ? $latestItem->distribution_price : 0;
            $fallbackTotal = $remainingToAllocate * $fallbackCost;
            
            $totalFifoCost += $fallbackTotal;
        }
        
        return $totalFifoCost;
    }

    /**
     * Create new sale item with FIFO processing
     */
    private function createNewSaleItemWithFifo(Sale $sale, Product $product, array $itemData)
    {
        $availableInventory = $product->inventory ? $product->inventory->quantity : 0;
        if ($itemData['quantity'] > $availableInventory) {
            throw new Exception("Insufficient inventory for {$product->product_name}. Available: {$availableInventory}, Requested: {$itemData['quantity']}");
        }
        
        // Process FIFO costing for new item
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
        $distributionPrice = $itemData['quantity'] > 0 ? $totalFifoCost / $itemData['quantity'] : 0;
        
        // Calculate totals
        $totalSoldBeforeDiscount = $itemData['sold_price'] * $itemData['quantity'];
        $discountAmount = (($itemData['discount'] ?? 0) / 100) * $totalSoldBeforeDiscount;
        $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
        
        // Create new sale item
        $saleItem = $sale->items()->create([
            'product_id' => $itemData['product_id'],
            'quantity' => $itemData['quantity'],
            'distribution_price' => $distributionPrice,
            'total_distribution_price' => $totalFifoCost,
            'sold_price' => $itemData['sold_price'],
            'total_sold_price' => $totalSoldPrice,
            'discount' => $itemData['discount'] ?? 0,
            'price_type' => $itemData['price_type'] ?? 'regular',
            'composition' => $itemData['composition'] ?? null,
        ]);
        
        // Update inventory for new item
        $this->updateInventoryForNewItem($product, $itemData['quantity']);
        
        Log::info("Created new sale item with FIFO cost: {$totalFifoCost} for quantity: {$itemData['quantity']}");
    }

    /**
     * Restore inventory when item is removed
     */
    private function restoreInventoryForItem($saleItem)
    {
        $product = Product::with('inventory')->findOrFail($saleItem->product_id);
        
        if ($product->inventory) {
            $product->inventory->increment('quantity', $saleItem->quantity);
        } else {
            // Create inventory record if it doesn't exist
            $product->inventory()->create([
                'quantity' => $saleItem->quantity,
                'reorder_level' => $product->reorder_level ?? 0,
            ]);
        }

        // Log inventory adjustment
        InventoryLog::create([
            'product_id' => $product->id,
            'type' => 'addition',
            'quantity' => $saleItem->quantity,
            'transaction_type' => InventoryLog::TYPE_RETURN,
            'quantity_before' => ($product->inventory->quantity ?? 0) - $saleItem->quantity,
            'quantity_after' => $product->inventory->quantity ?? $saleItem->quantity,
            'reference_type' => 'sale_item_removal',
            'reference_id' => $saleItem->id,
            'user_id' => Auth::id(),
            'notes' => "Inventory restored from sale item removal - Sale #{$saleItem->sale->invoice_number}"
        ]);
    }

    /**
     * Update inventory for quantity changes
     */
    private function updateInventoryForQuantityChange(Product $product, int $quantityDifference)
    {
            if ($product->inventory) {
                $previousQuantity = $product->inventory->quantity;
                $product->inventory->decrement('quantity', $quantityDifference);
                $newQuantity = $product->inventory->quantity;
            
            // Log inventory adjustment
            InventoryLog::create([
                'product_id' => $product->id,
                'type' => $quantityDifference > 0 ? 'reduction' : 'addition',
                'transaction_type' => $quantityDifference > 0 ? InventoryLog::TYPE_SALES : InventoryLog::TYPE_RETURN,
                'quantity' => abs($quantityDifference),
                'quantity_before' => $previousQuantity,
                'quantity_after' => $newQuantity,
                'reference_type' => 'sale_update',
                'reference_id' => $product->id,
                'user_id' => Auth::id(),
                'notes' => "Inventory updated from sale modification"
            ]);
        }
    }

    /**
     * Update inventory for new items
     */
    private function updateInventoryForNewItem(Product $product, int $quantity)
    {
        if ($product->inventory) {
            $previousQuantity = $product->inventory->quantity;
            $product->inventory->decrement('quantity', $quantity);
            
            // Log inventory adjustment
            InventoryLog::create([
                'product_id' => $product->id,
                'type' => 'reduction',
                'transaction_type' => InventoryLog::TYPE_SALES,
                'quantity' => $quantity,
                'quantity_before' => $previousQuantity,
                'quantity_after' => $product->inventory->quantity,
                'reference_type' => 'sale_update',
                'reference_id' => $product->id,
                'user_id' => Auth::id(),
                'notes' => "Inventory reduced from new sale item"
            ]);
        }
    }


    private function recalculateSaleTotals(Sale $sale)
    {
        $sale->load('items');
        
        $deliveryFee = $sale->delivery_fee ?? 0;
        $cuttingCharges = $sale->cutting_charges ?? 0;
        $otherCharges = $deliveryFee + $cuttingCharges;
  
        $totalCOGS = 0;
        $totalRevenue = 0;
        
        // Calculate COGS and revenue for each item
        foreach ($sale->items as $item) {

            // COGS = FIFO cost (total_distribution_price) + item's share of other charges
            $itemCOGS = ($item->total_distribution_price ?? 0) ;
            $totalCOGS += $itemCOGS;
            
            // Revenue = sold_price * quantity - discount
            $itemSubtotal = $item->sold_price * $item->quantity;
            $discountAmount = $itemSubtotal * ($item->discount / 100);
            $itemRevenue = $itemSubtotal - $discountAmount;
            $totalRevenue += $itemRevenue;
            
            Log::info("Item {$item->id}: FIFO Cost={$item->total_distribution_price}, COGS={$itemCOGS}, Revenue={$itemRevenue}");
        }
        
        // Total amount = revenue + other charges
        $totalAmount = $totalRevenue + $otherCharges;
        
        // Profit = Total Revenue - Total COGS
        $totalProfit = $totalAmount - $totalCOGS;
        
        Log::info("Final Calculation:");
        Log::info("Total COGS: {$totalCOGS}");
        Log::info("Total Revenue: {$totalRevenue}");
        Log::info("Other Charges: {$otherCharges}");
        Log::info("Total Amount: {$totalAmount}");
        Log::info("Total Profit: {$totalProfit}");
        
        $sale->update([
            'cogs' => $totalCOGS,
            'profit' => $totalProfit,
            'total' => $totalAmount
        ]);
    }
}