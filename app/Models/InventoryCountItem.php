<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'count_id',
        'product_id',
        'system_quantity',
        'counted_quantity',
        'notes'
    ];

    protected $casts = [
        'system_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2'
    ];

    /**
     * Get the inventory count this item belongs to
     */
    public function inventoryCount()
    {
        return $this->belongsTo(InventoryCount::class, 'count_id', 'id');
    }

    /**
     * Get the product this count item is for
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Check if this item has a discrepancy
     */
    public function hasDiscrepancy(): bool
    {
        return $this->counted_quantity !== null && 
               $this->system_quantity !== $this->counted_quantity;
    }

    /**
     * Get the discrepancy amount (counted - system)
     */
    public function getDiscrepancyAttribute()
    {
        if ($this->counted_quantity === null) {
            return 0;
        }
        
        return $this->counted_quantity - $this->system_quantity;
    }

    /**
     * Get the discrepancy percentage
     */
    public function getDiscrepancyPercentageAttribute()
    {
        if ($this->counted_quantity === null || $this->system_quantity == 0) {
            return 0;
        }
        
        return round(($this->discrepancy / $this->system_quantity) * 100, 2);
    }

    /**
     * Calculate the value of the discrepancy
     */
    public function getDiscrepancyValueAttribute()
    {
        if ($this->counted_quantity === null) {
            return 0;
        }
        
        $inventory = Inventory::where('product_id', $this->product_id)->first();
        $avgCostPrice = $inventory ? $inventory->avg_cost_price : 0;
        
        return $this->discrepancy * $avgCostPrice;
    }
}