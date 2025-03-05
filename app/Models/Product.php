<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_code',
        'product_name',
        'product_category_id',
        'reorder_level',
        'product_image',
        'quantity'
    ];

    // Relationship with ProductCategory
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    // Relationship with Attributes through product_attributes
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

   /**
    * Get inventory logs for this product
    */
   public function inventoryLogs()
   {
       return $this->hasMany(InventoryLog::class, 'product_id', 'id');
   }

   /**
    * Get inventory adjustments for this product
    */
   public function inventoryAdjustments()
   {
       return $this->hasMany(InventoryAdjustment::class, 'product_id', 'id');
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
}