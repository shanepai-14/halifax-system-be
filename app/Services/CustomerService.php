<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerService
{
    /**
     * Get all customers with optional filtering
     */
public function getAllCustomers(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
{
    $query = Customer::query();
    
    // Always eager load custom prices with product information
    $query->with([
        'customPrices' => function ($query) {
            $query->with(['product:id,product_code,product_name'])
                  ->orderBy('product_id')
                  ->orderBy('min_quantity');
        }
    ]);
    
    // Apply search filter if provided
    if (!empty($filters['search'])) {
        $search = $filters['search'];
        $query->where(function ($q) use ($search) {
            $q->where('customer_name', 'like', "%{$search}%")
              ->orWhere('business_name', 'like', "%{$search}%")
              ->orWhere('contact_number', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('city', 'like', "%{$search}%");
        });
    }

    // Apply city filter if provided
    if (!empty($filters['city'])) {
        $query->where('city', $filters['city']);
    }

    // Filter by valued customers only if specified
    if (isset($filters['valued_customers_only']) && $filters['valued_customers_only']) {
        $query->where('is_valued_customer', true);
    }

    // Filter customers with custom pricing if specified
    if (isset($filters['has_custom_pricing']) && $filters['has_custom_pricing']) {
        $query->whereHas('customPrices');
    }

    // Filter by specific product if provided (customers with custom pricing for this product)
    if (!empty($filters['product_id'])) {
        $query->whereHas('customPrices', function ($q) use ($filters) {
            $q->where('product_id', $filters['product_id']);
        });
    }

    // Sort by created date if not specified
    $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

    $result = $perPage ? $query->paginate($perPage) : $query->get();

    // Transform the data to group custom prices by product
    if ($result instanceof LengthAwarePaginator) {
        $result->getCollection()->transform(function ($customer) {
            return $this->transformCustomerWithPricing($customer);
        });
    } else {
        $result->transform(function ($customer) {
            return $this->transformCustomerWithPricing($customer);
        });
    }

    return $result;
}

/**
 * Transform customer data to include grouped custom pricing
 */
private function transformCustomerWithPricing($customer)
{
    if (!$customer->customPrices || $customer->customPrices->isEmpty()) {
        $customer->custom_pricing_summary = [
            'has_custom_pricing' => false,
            'total_products' => 0,
            'total_price_ranges' => 0,
            'active_price_ranges' => 0,
            'products_with_pricing' => []
        ];
        return $customer;
    }

    // Group custom prices by product
    $groupedPrices = $customer->customPrices->groupBy('product_id')->map(function ($pricesByProduct, $productId) {
        $firstPrice = $pricesByProduct->first();
        return [
            'product_id' => $productId,
            'product' => $firstPrice->product ? [
                'id' => $firstPrice->product->id,
                'product_code' => $firstPrice->product->product_code,
                'product_name' => $firstPrice->product->product_name,
            ] : null,
            'price_ranges' => $pricesByProduct->map(function ($price) {
                return [
                    'id' => $price->id,
                    'min_quantity' => $price->min_quantity,
                    'max_quantity' => $price->max_quantity,
                    'quantity_range' => $price->max_quantity 
                        ? "{$price->min_quantity} - {$price->max_quantity}"
                        : "{$price->min_quantity}+",
                    'price' => (float) $price->price,
                    'formatted_price' => number_format($price->price, 2),
                    'label' => $price->label,
                    'notes' => $price->notes,
                    'is_active' => (bool) $price->is_active,
                    'effective_from' => $price->effective_from,
                    'effective_to' => $price->effective_to,
                    'created_at' => $price->created_at,
                    'updated_at' => $price->updated_at
                ];
            })->values()->toArray(),
            'total_ranges' => $pricesByProduct->count(),
            'active_ranges' => $pricesByProduct->where('is_active', true)->count(),
            'has_inactive_ranges' => $pricesByProduct->where('is_active', false)->count() > 0,
            'price_range_summary' => $this->generatePriceRangeSummary($pricesByProduct)
        ];
    })->values();

    // Add custom pricing summary
    $customer->custom_pricing_summary = [
        'has_custom_pricing' => true,
        'total_products' => $groupedPrices->count(),
        'total_price_ranges' => $customer->customPrices->count(),
        'active_price_ranges' => $customer->customPrices->where('is_active', true)->count(),
        'products_with_pricing' => $groupedPrices->pluck('product.product_name')->filter()->toArray(),
        'last_updated' => $customer->customPrices->max('updated_at')
    ];

    // Replace the original relationship with grouped data
    $customer->setRelation('custom_pricing_groups', $groupedPrices);
    
    // Remove the original relationship to avoid confusion
    $customer->unsetRelation('customPrices');

    return $customer;
}

/**
 * Generate a summary string for price ranges
 */
private function generatePriceRangeSummary($pricesByProduct)
{
    $sortedPrices = $pricesByProduct->sortBy('min_quantity');
    $ranges = [];
    
    foreach ($sortedPrices as $price) {
        $range = $price->max_quantity 
            ? "{$price->min_quantity}-{$price->max_quantity}"
            : "{$price->min_quantity}+";
        $formattedPrice = number_format($price->price, 2);
        $ranges[] = "{$range}: ₱{$formattedPrice}";
    }
    
    return implode(' | ', $ranges);
}


    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        try {
            DB::beginTransaction();
            
            $customer = Customer::create($data);
            
            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $id): Customer
    {
        $customer = Customer::find($id);
        
        if (!$customer) {
            throw new ModelNotFoundException("Customer with ID {$id} not found");
        }

        return $customer;
    }

    /**
     * Update customer
     */
    public function updateCustomer(int $id, array $data): Customer
    {
        try {
            DB::beginTransaction();

            $customer = $this->getCustomerById($id);
            $customer->update($data);

            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update customer: ' . $e->getMessage());
        }
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(int $id): bool 
    {
        try {
            DB::beginTransaction();
            
            $customer = $this->getCustomerById($id);
            $result = $customer->delete(); // This will trigger soft delete
            
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete customer: ' . $e->getMessage());
        }
    }

    /**
     * Get trashed customers
     */
    public function getTrashedCustomers(): Collection
    {
        return Customer::onlyTrashed()->get();
    }

    /**
     * Restore trashed customer
     */
    public function restoreCustomer(int $id): Customer
    {
        try {
            DB::beginTransaction();

            $customer = Customer::onlyTrashed()->findOrFail($id);
            $customer->restore();

            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to restore customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(): array
    {
        return [
            'total_customers' => Customer::count(),
            'new_customers_this_month' => Customer::whereMonth('created_at', date('m'))
                                            ->whereYear('created_at', date('Y'))
                                            ->count(),
            'cities' => Customer::select('city')
                                    ->whereNotNull('city')
                                    ->groupBy('city')
                                    ->pluck('city')
                                    ->count(),
            'trashed_customers' => Customer::onlyTrashed()->count()
        ];
    }
}