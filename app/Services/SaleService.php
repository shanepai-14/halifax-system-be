<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
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
        
        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
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
            'items.product',
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
            
            // Create the sale
            $sale = Sale::create([
                'invoice_number' => $data['invoice_number'],
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'status' => Sale::STATUS_PENDING,
                'customer_type' => $data['customer_type'] ?? Sale::TYPE_REGULAR,
                'payment_method' => $data['payment_method'] ?? Sale::PAYMENT_CASH,
                'order_date' => $data['order_date'] ?? now(),
                'delivery_date' => $data['delivery_date'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'phone' => $data['phone'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'amount_received' => $data['amount_received'] ?? 0,
                'change' => $data['change'] ?? 0,
                'is_delivered' => false,
                'term_days' => $data['term_days'] ?? 0,
            ]);
            
            // Handle sale items
            if (!empty($data['items']) && is_array($data['items'])) {
                $this->processSaleItems($sale, $data['items']);
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
    protected function processSaleItems(Sale $sale, array $items): void
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
            
            // Calculate prices
            $distributionPrice = $item['distribution_price'] ?? $product->cost_price ?? 0;
            $soldPrice = $item['sold_price'] ?? $this->getPriceByCustomerType($product, $sale->customer_type);
            $discount = $item['discount'] ?? 0;
            
            // Calculate totals
            $totalDistributionPrice = $distributionPrice * $item['quantity'];
            $totalSoldBeforeDiscount = $soldPrice * $item['quantity'];
            $discountAmount = ($discount / 100) * $totalSoldBeforeDiscount;
            $totalSoldPrice = $totalSoldBeforeDiscount - $discountAmount;
            
            // Create the sale item
            $saleItem = $sale->items()->create([
                'product_id' => $product->id,
                'distribution_price' => $distributionPrice,
                'sold_price' => $soldPrice,
                'price_type' => $item['price_type'],
                'quantity' => $item['quantity'],
                'total_distribution_price' => $totalDistributionPrice,
                'total_sold_price' => $totalSoldPrice,
                'discount' => $discount,
                'is_discount_approved' => $item['is_discount_approved'] ?? false,
                'approved_by' => $item['approved_by'] ?? null
            ]);
            
            // Update running totals
            $totalCogs += $totalDistributionPrice;
            $totalSold += $totalSoldPrice;
            
            // Update inventory
            $this->updateInventoryOnSale($product->id, $item['quantity'], $sale->id);
        }
        
        // Update sale totals
        $profit = $totalSold - $totalCogs;
        $sale->update([
            'cogs' => $totalCogs,
            'profit' => $profit,
            'total' => $totalSold
        ]);
    }
    
    /**
     * Update inventory when a sale is made
     *
     * @param int $productId
     * @param int $quantity
     * @return void
     */
    protected function updateInventoryOnSale(int $productId, int $quantity, int $saleId): void
    {
        // Get inventory
        $inventory = Inventory::where('product_id', $productId)->first();
        if (!$inventory) {
            throw new Exception("Inventory record not found for product ID: {$productId}");
        }
        
        // Update inventory quantity
        $currentQuantity = $inventory->quantity;
        $inventory->decrementQuantity($quantity);
        
        // Create inventory log
        InventoryLog::create([
            'product_id' => $productId,
            'user_id' => Auth::id(),
            'transaction_type' => InventoryLog::TYPE_SALES,
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'quantity' => $quantity,
            'quantity_before' => $currentQuantity,
            'quantity_after' => $inventory->quantity,
            'notes' => "Product sold in sale #{$saleId}"
        ]);
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

}