<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderReceivedItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'received_item_id';

    protected $fillable = [
        'rr_id',
        'product_id',
        'attribute_id',
        'received_quantity',
        'sold_quantity',    
        'fully_consumed', 
        'cost_price',
        'distribution_price',
        'walk_in_price',
        'term_price',
        'wholesale_price',
        'regular_price',
        'remarks',
        'processed_for_inventory',
        'processed_at'
    ];

    protected $casts = [
        'received_quantity' => 'decimal:2',
        'sold_quantity' => 'decimal:2',    
        'fully_consumed' => 'boolean', 
        'cost_price' => 'decimal:2',
        'walk_in_price' => 'decimal:2',
        'term_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'regular_price' => 'decimal:2',
    ];

    // Define price types as constants
    const PRICE_TYPE_WALK_IN = 'walk_in';
    const PRICE_TYPE_TERM = 'term';
    const PRICE_TYPE_WHOLESALE = 'wholesale';
    const PRICE_TYPE_REGULAR = 'regular';

    // Relationship with purchase order
    public function purchaseOrder()
    {
        return $this->belongsTo(ReceivingReport::class, 'rr_id', 'rr_id');
    }

    public function receivingReport()
    {
        return $this->belongsTo(ReceivingReport::class, 'rr_id', 'rr_id');
    }

    // Relationship with product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // Relationship with attribute
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }

    // Get total cost amount
    public function getTotalCostAttribute()
    {
        return $this->received_quantity * ($this->cost_price ?? 0);
    }

    public function getAvailableQuantityAttribute()
    {
        return $this->received_quantity - $this->sold_quantity;
    }

    // Get price by type
    public function getPriceByType($type)
    {
        return match ($type) {
            self::PRICE_TYPE_WALK_IN => $this->walk_in_price,
            self::PRICE_TYPE_TERM => $this->term_price,
            self::PRICE_TYPE_WHOLESALE => $this->wholesale_price,
            self::PRICE_TYPE_REGULAR => $this->regular_price,
            default => null,
        };
    }

    // Update prices
    public function updatePrices(array $prices)
    {
        $validPrices = array_intersect_key($prices, [
            self::PRICE_TYPE_WALK_IN => true,
            self::PRICE_TYPE_TERM => true,
            self::PRICE_TYPE_WHOLESALE => true,
            self::PRICE_TYPE_REGULAR => true,
        ]);

        foreach ($validPrices as $type => $price) {
            $column = $type . '_price';
            $this->$column = $price;
        }

        return $this->save();
    }

    // Update received quantity
    public function updateReceivedQuantity($quantity)
    {
        $this->received_quantity = $quantity;
        $this->save();

        // Update parent PO status if needed
        $this->purchaseOrder->updateStatus();
    }

    // Scope for filtering by product
    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // Scope for filtering by attribute
    public function scopeByAttribute($query, $attributeId)
    {
        return $query->where('attribute_id', $attributeId);
    }

    // Boot method for model events
    // protected static function boot()
    // {
    //     parent::boot();

    //     // When creating a new received item
    //     static::created(function ($receivedItem) {
    //         $receivedItem->purchaseOrder->updateStatus();
    //     });

    //     // When deleting a received item
    //     static::deleted(function ($receivedItem) {
    //         if (!$receivedItem->isForceDeleting()) {
    //             $receivedItem->purchaseOrder->updateStatus();
    //         }
    //     });
    // }
}