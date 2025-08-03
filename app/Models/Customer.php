<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_name',
        'business_name',
        'business_address',
        'contact_number',
        'email',
        'address',
        'city',
        'is_valued_customer',       
        'valued_customer_notes',    
        'valued_since'   
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_valued_customer' => 'boolean',  
        'valued_since' => 'datetime' 
    ];

      public function customPrices()
    {
        return $this->hasMany(CustomerCustomPrice::class);
    }

    // NEW: Get active custom prices
    public function activeCustomPrices()
    {
        return $this->hasMany(CustomerCustomPrice::class)->active();
    }

    // NEW: Get custom price for specific product and quantity
    public function getCustomPrice(int $productId, int $quantity, string $priceType = 'regular'): ?float
    {
        if (!$this->is_valued_customer) {
            return null;
        }

        $customPrice = $this->activeCustomPrices()
            ->where('product_id', $productId)
            ->forQuantity($quantity)
            ->orderBy('min_quantity', 'desc') // Prefer higher quantity tiers
            ->first();

        return $customPrice ? $customPrice->price : null;
    }

    // NEW: Check if customer has custom pricing for product
    public function hasCustomPricingFor(int $productId): bool
    {
        if (!$this->is_valued_customer) {
            return false;
        }

        return $this->activeCustomPrices()
            ->where('product_id', $productId)
            ->exists();
    }

    // NEW: Mark as valued customer
    public function markAsValuedCustomer(string $notes = null): void
    {
        $this->update([
            'is_valued_customer' => true,
            'valued_since' => now(),
            'valued_customer_notes' => $notes
        ]);
    }

    // NEW: Remove valued customer status
    public function removeValuedCustomerStatus(): void
    {
        $this->update([
            'is_valued_customer' => false,
            'valued_since' => null,
            'valued_customer_notes' => null
        ]);

        // Optionally deactivate all custom prices
        $this->customPrices()->update(['is_active' => false]);
    }

    // NEW: Get pricing summary for this customer
    public function getPricingSummary(): array
    {
        if (!$this->is_valued_customer) {
            return ['type' => 'standard'];
        }

        $customPrices = $this->activeCustomPrices()->with('product')->get();
        
        return [
            'type' => 'custom',
            'total_custom_prices' => $customPrices->count(),
            'products_with_custom_pricing' => $customPrices->groupBy('product_id')->count(),
            'valued_since' => $this->valued_since,
            'notes' => $this->valued_customer_notes
        ];
    }
}