<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BracketItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'bracket_id',
        'min_quantity',
        'max_quantity',
        'price',
        'price_type',
        'label',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Price type constants
    const PRICE_TYPE_REGULAR = 'regular';
    const PRICE_TYPE_WHOLESALE = 'wholesale';
    const PRICE_TYPE_WALK_IN = 'walk_in';

    /**
     * Relationship with ProductPriceBracket
     */
    public function productPriceBracket()
    {
        return $this->belongsTo(ProductPriceBracket::class, 'bracket_id');
    }

    /**
     * Get the product through the bracket relationship
     */
    public function product()
    {
        return $this->hasOneThrough(
            Product::class,
            ProductPriceBracket::class,
            'id',
            'id',
            'bracket_id',
            'product_id'
        );
    }

    /**
     * Scope to get only active bracket items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by price type
     */
    public function scopeOfType($query, $priceType)
    {
        return $query->where('price_type', $priceType);
    }

    /**
     * Scope to find bracket item for specific quantity
     */
    public function scopeForQuantity($query, $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity)
                    ->where(function($q) use ($quantity) {
                        $q->whereNull('max_quantity')
                          ->orWhere('max_quantity', '>=', $quantity);
                    });
    }

    /**
     * Scope to order by sort order and then by price
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('min_quantity', 'asc')
                    ->orderBy('sort_order', 'asc')
                    ->orderBy('price', 'asc');
    }

    /**
     * Get quantity range display
     */
    public function getQuantityRangeAttribute()
    {
        if ($this->max_quantity) {
            return "{$this->min_quantity} - {$this->max_quantity}";
        }
        return "{$this->min_quantity}+";
    }

    /**
     * Check if this price option applies to a given quantity
     */
    public function appliesToQuantity($quantity)
    {
        $minValid = $this->min_quantity <= $quantity;
        $maxValid = is_null($this->max_quantity) || $this->max_quantity >= $quantity;
        
        return $minValid && $maxValid && $this->is_active;
    }

    /**
     * Boot method for model events
     */
 protected static function boot()
{
    parent::boot();

    static::saving(function ($bracketItem) {
        // Basic validation only
        if ($bracketItem->max_quantity && $bracketItem->max_quantity <= $bracketItem->min_quantity) {
            throw new \Exception('Maximum quantity must be greater than minimum quantity.');
        }
        
        // REMOVED overlap validation to allow multiple prices per tier
        // Multiple price options within the same quantity range are now allowed
    });

    static::creating(function ($item) {
        // Auto-set sort order if not provided
        if (is_null($item->sort_order)) {
            $maxOrder = static::where('bracket_id', $item->bracket_id)
                             ->where('min_quantity', $item->min_quantity)
                             ->where('max_quantity', $item->max_quantity)
                             ->max('sort_order');
            $item->sort_order = ($maxOrder ?? 0) + 1;
        }

        // Set default label if not provided
        if (empty($item->label)) {
            $priceTypeLabels = [
                'regular' => 'Regular Price',
                'wholesale' => 'Wholesale Price',
                'walk_in' => 'Walk-in Price'
            ];
            $item->label = $priceTypeLabels[$item->price_type] ?? 'Price Option';
        }
    });
}
}