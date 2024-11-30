<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'po_item_id';

    protected $fillable = [
        'po_id',
        'product_id',
        'requested_quantity',
        'received_quantity',
        'price',
        'retail_price'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'deleted_at' => 'datetime'
    ];

    // Relationship with purchase order
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id', 'po_id');
    }

    // Relationship with product (assuming you have a Product model)
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    // Calculate total price for the item
    public function getTotalAttribute()
    {
        return $this->price * $this->requested_quantity;
    }

    // Update received quantity and parent PO status
    public function updateReceivedQuantity($quantity)
    {
        $this->received_quantity = $quantity;
        $this->save();
        
        // Update parent PO status
        $this->purchaseOrder->updateStatus();
    }

    // Force delete with inventory adjustment if needed
    protected static function boot()
    {
        parent::boot();

        static::forceDeleted(function ($item) {
            // Add any inventory adjustment logic here if needed
            // This runs only on permanent deletion
        });
    }
}