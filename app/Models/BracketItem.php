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
        'is_active'
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean'
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
            'id', // Foreign key on ProductPriceBracket table
            'id', // Foreign key on Product table
            'bracket_id', // Local key on BracketItem table
            'product_id' // Local key on ProductPriceBracket table
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
     * Check if quantity falls within this bracket item
     */
    public function containsQuantity($quantity)
    {
        $withinMin = $quantity >= $this->min_quantity;
        $withinMax = is_null($this->max_quantity) || $quantity <= $this->max_quantity;
        
        return $withinMin && $withinMax;
    }

    /**
     * Get formatted bracket range
     */
    public function getFormattedRangeAttribute()
    {
        if (is_null($this->max_quantity)) {
            return "{$this->min_quantity}+";
        }
        
        return "{$this->min_quantity} - {$this->max_quantity}";
    }

    /**
     * Get price type label
     */
    public function getPriceTypeLabelAttribute()
    {
        return match($this->price_type) {
            self::PRICE_TYPE_REGULAR => 'Regular',
            self::PRICE_TYPE_WHOLESALE => 'Wholesale',
            self::PRICE_TYPE_WALK_IN => 'Walk-in',
            default => ucfirst($this->price_type)
        };
    }

    /**
     * Calculate total price for given quantity
     */
    public function calculateTotal($quantity)
    {
        if (!$this->containsQuantity($quantity)) {
            return null;
        }
        
        return $this->price * $quantity;
    }

    /**
     * Validate bracket item doesn't overlap with siblings
     */
    public function validateNoOverlap()
    {
        $query = self::where('bracket_id', $this->bracket_id)
                     ->where('price_type', $this->price_type)
                     ->where('is_active', true);

        if ($this->exists) {
            $query->where('id', '!=', $this->id);
        }

        $overlapping = $query->where(function($q) {
            $q->where(function($subQ) {
                // Check if our min_quantity falls within existing range
                $subQ->where('min_quantity', '<=', $this->min_quantity)
                     ->where(function($rangeQ) {
                         $rangeQ->whereNull('max_quantity')
                                ->orWhere('max_quantity', '>=', $this->min_quantity);
                     });
            })->orWhere(function($subQ) {
                // Check if our max_quantity falls within existing range (if we have max_quantity)
                if (!is_null($this->max_quantity)) {
                    $subQ->where('min_quantity', '<=', $this->max_quantity)
                         ->where(function($rangeQ) {
                             $rangeQ->whereNull('max_quantity')
                                    ->orWhere('max_quantity', '>=', $this->max_quantity);
                         });
                }
            })->orWhere(function($subQ) {
                // Check if existing bracket item falls within our range
                $subQ->where('min_quantity', '>=', $this->min_quantity);
                if (!is_null($this->max_quantity)) {
                    $subQ->where('min_quantity', '<=', $this->max_quantity);
                }
            });
        })->exists();

        return !$overlapping;
    }

    /**
     * Get the next bracket item (higher quantity)
     */
    public function getNextBracketItem()
    {
        return self::where('bracket_id', $this->bracket_id)
                   ->where('price_type', $this->price_type)
                   ->where('is_active', true)
                   ->where('min_quantity', '>', $this->max_quantity ?? $this->min_quantity)
                   ->orderBy('min_quantity', 'asc')
                   ->first();
    }

    /**
     * Get the previous bracket item (lower quantity)
     */
    public function getPreviousBracketItem()
    {
        return self::where('bracket_id', $this->bracket_id)
                   ->where('price_type', $this->price_type)
                   ->where('is_active', true)
                   ->where('min_quantity', '<', $this->min_quantity)
                   ->orderBy('min_quantity', 'desc')
                   ->first();
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($bracketItem) {
            // Validate quantity range
            if ($bracketItem->max_quantity && $bracketItem->max_quantity <= $bracketItem->min_quantity) {
                throw new \Exception('Maximum quantity must be greater than minimum quantity.');
            }

            // Validate no overlapping bracket items
            if (!$bracketItem->validateNoOverlap()) {
                throw new \Exception('Bracket item overlaps with existing bracket item for the same price type.');
            }
        });
    }
}