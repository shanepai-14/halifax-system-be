<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SaleReturnItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'product_id',
        'quantity',
        'price',
        'discount',
        'discount_amount',
        'tax_amount',
        'subtotal',
        'return_reason',
        'condition'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    // Item condition constants
    const CONDITION_NEW = 'new';
    const CONDITION_GOOD = 'good';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_EXPIRED = 'expired';
    const CONDITION_DEFECTIVE = 'defective';

    // Return reason constants 
    const REASON_WRONG_ITEM = 'wrong_item';
    const REASON_DEFECTIVE = 'defective';
    const REASON_DAMAGED = 'damaged';
    const REASON_EXPIRED = 'expired';
    const REASON_CUSTOMER_DISSATISFIED = 'customer_dissatisfied';
    const REASON_OTHER = 'other';

    /**
     * Get the return this item belongs to
     */
    public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    /**
     * Get the original sale item
     */
    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class, 'sale_item_id');
    }

    /**
     * Get the product associated with this return item
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate the total price
     */
    public function getTotalPriceAttribute()
    {
        return $this->price * $this->quantity;
    }

    /**
     * Get if this item is returnable to inventory
     */
    public function getIsReturnableToInventoryAttribute()
    {
        return in_array($this->condition, [self::CONDITION_NEW, self::CONDITION_GOOD]);
    }

    /**
     * Boot the model to add hooks
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-calculate subtotal when saving
        static::saving(function ($item) {
            $item->subtotal = $item->total_price - $item->discount_amount - $item->tax_amount;
        });
    }
}