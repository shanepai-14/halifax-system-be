<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'product_id',
        'distribution_price',
        'sold_price',
        'price_type',
        'quantity',
        'total_distribution_price',
        'total_sold_price',
        'discount',
        'composition',
        'is_discount_approved',
        'approved_by'
    ];

    protected $casts = [
        'distribution_price' => 'decimal:2',
        'sold_price' => 'decimal:2',
        'quantity' => 'integer',
        'total_distribution_price' => 'decimal:2',
        'total_sold_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'is_discount_approved' => 'boolean'
    ];

    /**
     * Get the sale this item belongs to
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the product associated with this item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who approved the discount
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get returns associated with this sale item
     */
    public function returns()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function getTotalReturnedQuantityAttribute()
    {
        return $this->returns()->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->sum('sale_return_items.quantity');
    }

    /**
     * Check if this item can be returned
     */
    public function canBeReturned($quantity = 1)
    {
        return ($this->quantity - $this->returned_quantity) >= $quantity;
    }

    /**
     * Calculate the profit for this item
     */
    public function getProfitAttribute()
    {
        return $this->total_sold_price - $this->total_distribution_price;
    }

    /**
     * Calculate the final price after discount
     */
    public function getFinalPriceAttribute()
    {
        if (!$this->discount) {
            return $this->sold_price;
        }
        
        return $this->sold_price * (1 - ($this->discount / 100));
    }

    /**
     * Boot the model to add hooks
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calculate totals when creating or updating
        static::saving(function ($item) {
            $item->total_distribution_price = $item->distribution_price * $item->quantity;
            $item->total_sold_price = $item->final_price * $item->quantity;
        });
    }
}