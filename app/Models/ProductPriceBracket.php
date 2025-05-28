<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPriceBracket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'is_selected',
        'effective_from',
        'effective_to',
        'created_by'
    ];

    protected $casts = [
        'is_selected' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime'
    ];

    /**
     * Relationship with Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship with BracketItems
     */
    public function bracketItems()
    {
        return $this->hasMany(BracketItem::class, 'bracket_id');
    }

    /**
     * Relationship with active BracketItems
     */
    public function activeBracketItems()
    {
        return $this->hasMany(BracketItem::class, 'bracket_id')
                    ->where('is_active', true);
    }

    /**
     * Get the user who created this bracket
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only selected brackets
     */
    public function scopeSelected($query)
    {
        return $query->where('is_selected', true);
    }

    /**
     * Scope to get only active brackets
     */
    public function scopeActive($query)
    {
        return $query->where('is_selected', true)
                     ->where(function($q) {
                         $q->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', now());
                     })
                     ->where('effective_from', '<=', now());
    }

    /**
     * Get price for specific quantity and price type
     */
    public static function getPriceForQuantity($productId, $quantity, $priceType = 'regular')
    {
        $activeBracket = self::where('product_id', $productId)
                            ->active()
                            ->first();

        if (!$activeBracket) {
            return null;
        }

        $bracketItem = $activeBracket->activeBracketItems()
                                    ->where('price_type', $priceType)
                                    ->where('min_quantity', '<=', $quantity)
                                    ->where(function($q) use ($quantity) {
                                        $q->whereNull('max_quantity')
                                          ->orWhere('max_quantity', '>=', $quantity);
                                    })
                                    ->orderBy('min_quantity', 'desc')
                                    ->first();

        return $bracketItem ? $bracketItem->price : null;
    }

    /**
     * Get all bracket items for a specific price type
     */
    public function getBracketItemsForPriceType($priceType)
    {
        return $this->activeBracketItems()
                    ->where('price_type', $priceType)
                    ->orderBy('min_quantity', 'asc')
                    ->get();
    }

    /**
     * Check if this bracket is currently active
     */
    public function isCurrentlyActive()
    {
        if (!$this->is_selected) {
            return false;
        }

        $now = now();
        $isEffectiveFromValid = $this->effective_from <= $now;
        $isEffectiveToValid = is_null($this->effective_to) || $this->effective_to >= $now;

        return $isEffectiveFromValid && $isEffectiveToValid;
    }

    /**
     * Get price breakdown for different quantities
     */
    public function getPriceBreakdown($priceType = 'regular', $quantities = [1, 5, 10, 25, 50, 100])
    {
        $breakdown = [];
        
        foreach ($quantities as $quantity) {
            $bracketItem = $this->activeBracketItems()
                               ->where('price_type', $priceType)
                               ->where('min_quantity', '<=', $quantity)
                               ->where(function($q) use ($quantity) {
                                   $q->whereNull('max_quantity')
                                     ->orWhere('max_quantity', '>=', $quantity);
                               })
                               ->orderBy('min_quantity', 'desc')
                               ->first();

            $breakdown[] = [
                'quantity' => $quantity,
                'price' => $bracketItem ? $bracketItem->price : null,
                'total' => $bracketItem ? $bracketItem->price * $quantity : null,
                'bracket_item_id' => $bracketItem ? $bracketItem->id : null
            ];
        }

        return $breakdown;
    }

    /**
     * Activate this bracket (deactivate others for the same product)
     */
    public function activate()
    {
        // Deactivate other brackets for the same product
        self::where('product_id', $this->product_id)
            ->where('id', '!=', $this->id)
            ->update(['is_selected' => false]);

        // Activate this bracket
        $this->update(['is_selected' => true]);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($bracket) {
            // Delete all related bracket items
            $bracket->bracketItems()->delete();
        });
    }
}