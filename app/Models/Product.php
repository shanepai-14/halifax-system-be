<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Services\BracketPricingService;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'product_name',
        'product_category_id',
        'attribute_id',
        'product_type',
        'reorder_level',
        'product_image',
        'quantity',
        'use_bracket_pricing' // Add this field
    ];

    protected $casts = [
        'use_bracket_pricing' => 'boolean'
    ];

    // Existing relationships...
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

        public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'attribute_id');
    }


    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes')
                    ->withPivot('value')
                    ->withTimestamps();
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_id', 'id');
    }

    public function inventoryLogs()
    {
        return $this->hasMany(InventoryLog::class, 'product_id', 'id');
    }

    public function inventoryAdjustments()
    {
        return $this->hasMany(InventoryAdjustment::class, 'product_id', 'id');
    }

    public function prices()
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function currentPrice()
    {
        return $this->hasOne(ProductPrice::class)->active()->latest();
    }

    // NEW: Bracket pricing relationships
    public function priceBrackets(): HasMany
    {
        return $this->hasMany(ProductPriceBracket::class);
    }

    public function activePriceBracket(): HasOne
    {
        return $this->hasOne(ProductPriceBracket::class)
                    ->where('is_selected', true)
                    ->where(function($query) {
                        $query->whereNull('effective_to')
                              ->orWhere('effective_to', '>=', now());
                    })
                    ->where('effective_from', '<=', now());
    }

    // Existing price accessors...
    public function getCurrentPricesAttribute()
    {
        $price = $this->currentPrice;
        if (!$price) {
            return [
                'regular_price' => 0,
                'wholesale_price' => 0,
                'walk_in_price' => 0,
                'cost_price' => 0
            ];
        }
        return $price;
    }

    public function getRegularPriceAttribute()
    {
        return $this->currentPrice ? $this->currentPrice->regular_price : 0;
    }

    public function getWholesalePriceAttribute()
    {
        return $this->currentPrice ? $this->currentPrice->wholesale_price : 0;
    }

    public function getWalkInPriceAttribute()
    {
        return $this->currentPrice ? $this->currentPrice->walk_in_price : 0;
    }

    public function getCostPriceAttribute()
    {
        return $this->currentPrice ? $this->currentPrice->cost_price : 0;
    }

    // NEW: Bracket pricing methods
    
    /**
     * Get price for specific quantity and price type using bracket pricing
     */
    public function getBracketPrice(int $quantity, string $priceType = 'regular'): ?float
    {
        if (!$this->use_bracket_pricing) {
            return null;
        }

        $bracketService = app(BracketPricingService::class);
        return $bracketService->calculatePriceForQuantity($this->id, $quantity, $priceType);
    }

    /**
     * Get the effective price for a quantity (bracket or traditional)
     */
    public function getEffectivePrice(int $quantity = 1, string $priceType = 'regular'): float
    {
        // Try bracket pricing first if enabled
        if ($this->use_bracket_pricing) {
            $bracketPrice = $this->getBracketPrice($quantity, $priceType);
            if ($bracketPrice !== null) {
                return $bracketPrice;
            }
        }

        // Fall back to traditional pricing
        return $this->getTraditionalPrice($priceType);
    }

    /**
     * Get traditional pricing (non-bracket)
     */
    public function getTraditionalPrice(string $priceType = 'regular'): float
    {
        $currentPrice = $this->currentPrice;
        
        if (!$currentPrice) {
            return 0;
        }
        
        return match($priceType) {
            'walk_in' => $currentPrice->walk_in_price,
            'wholesale' => $currentPrice->wholesale_price,
            'regular' => $currentPrice->regular_price,
            default => $currentPrice->regular_price,
        };
    }

    /**
     * Check if product has active bracket pricing
     */
    public function hasActiveBracketPricing(): bool
    {
        return $this->use_bracket_pricing && $this->activePriceBracket !== null;
    }

    /**
     * Get pricing summary for display
     */
    public function getPricingSummaryAttribute(): array
    {
        if ($this->hasActiveBracketPricing()) {
            $bracket = $this->activePriceBracket;
            $summary = [
                'type' => 'bracket',
                'bracket_id' => $bracket->id,
                'tier_count' => $bracket->bracketItems->count(),
                'price_types' => $bracket->bracketItems->pluck('price_type')->unique()->values()->toArray()
            ];

            // Get price ranges for each type
            foreach (['regular', 'wholesale', 'walk_in'] as $priceType) {
                $items = $bracket->bracketItems->where('price_type', $priceType)->where('is_active', true);
                if ($items->isNotEmpty()) {
                    $prices = $items->pluck('price');
                    $summary['ranges'][$priceType] = [
                        'min' => $prices->min(),
                        'max' => $prices->max(),
                        'base' => $items->where('min_quantity', '<=', 1)->first()?->price ?? $prices->min()
                    ];
                }
            }

            return $summary;
        }

        // Traditional pricing
        return [
            'type' => 'traditional',
            'prices' => [
                'regular' => $this->getTraditionalPrice('regular'),
                'wholesale' => $this->getTraditionalPrice('wholesale'),
                'walk_in' => $this->getTraditionalPrice('walk_in')
            ]
        ];
    }

    /**
     * Get current stock quantity from inventory relation
     */
    public function getCurrentStockAttribute()
    {
        return $this->inventory ? $this->inventory->quantity : 0;
    }

    /**
     * Get stock status based on reorder level
     */
    public function getStockStatusAttribute()
    {
        if (!$this->inventory) {
            return 'no_stock';
        }
        
        return $this->inventory->getStatus();
    }

    /**
     * Check if product is low on stock
     */
    public function isLowStock(): bool
    {
        return $this->inventory ? $this->inventory->isLowStock() : false;
    }

    /**
     * Check if product is out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->inventory ? $this->inventory->quantity <= 0 : true;
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When bracket pricing is disabled, deactivate all brackets
        static::updating(function ($product) {
            if ($product->isDirty('use_bracket_pricing') && !$product->use_bracket_pricing) {
                $product->priceBrackets()->update(['is_selected' => false]);
            }
        });
    }
}