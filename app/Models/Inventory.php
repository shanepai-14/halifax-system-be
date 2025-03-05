<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'id';
    
    protected $fillable = [
        'product_id',
        'quantity',
        'avg_cost_price',
        'last_received_at',
        'recount_needed'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'avg_cost_price' => 'decimal:2',
        'last_received_at' => 'datetime',
        'recount_needed' => 'boolean'
    ];

    /**
     * Get the product that this inventory belongs to
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Get inventory logs for this inventory record
     */
    public function logs()
    {
        return $this->hasMany(InventoryLog::class, 'product_id', 'product_id');
    }

    /**
     * Get all adjustments for this inventory record
     */
    public function adjustments()
    {
        return $this->hasMany(InventoryAdjustment::class, 'product_id', 'product_id');
    }

    /**
     * Calculate if the inventory is low
     */
    public function isLowStock(): bool
    {
        if (!$this->product) {
            return false;
        }
        
        return $this->quantity <= $this->product->reorder_level;
    }

    /**
     * Calculate if the inventory is overstocked
     */
    public function isOverStocked(): bool
    {
        if (!$this->product || $this->product->reorder_level <= 0) {
            return false;
        }
        
        return $this->quantity > ($this->product->reorder_level * 3);
    }

    /**
     * Get inventory status
     */
    public function getStatus(): string
    {
        if ($this->isLowStock()) {
            return 'low';
        } elseif ($this->isOverStocked()) {
            return 'overstocked';
        } else {
            return 'normal';
        }
    }

    /**
     * Update average cost price based on a new purchase
     */
    public function updateAverageCostPrice(float $newQuantity, float $newCostPrice): void
    {
        if ($newQuantity <= 0 || $newCostPrice <= 0) {
            return;
        }
        
        $currentTotal = $this->quantity * $this->avg_cost_price;
        $newTotal = $newQuantity * $newCostPrice;
        $newTotalQuantity = $this->quantity + $newQuantity;
        
        if ($newTotalQuantity > 0) {
            $this->avg_cost_price = ($currentTotal + $newTotal) / $newTotalQuantity;
        }
    }

    /**
     * Increment inventory quantity
     */
    public function incrementQuantity(float $quantity, ?float $costPrice = null): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        
        // Update average cost price if provided
        if ($costPrice !== null && $costPrice > 0) {
            $this->updateAverageCostPrice($quantity, $costPrice);
        }
        
        $this->quantity += $quantity;
        $this->last_received_at = now();
        
        return $this->save();
    }

    // /**
    //  * Decrement inventory quantity
    //  */
    public function decrementQuantity(float $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }
        
        // Prevent negative inventory (or implement your business logic)
        if ($this->quantity < $quantity) {
            $this->quantity = 0;
        } else {
            $this->quantity -= $quantity;
        }
        
        return $this->save();
    }

    /**
     * Set inventory quantity directly (for corrections/recounts)
     */
    public function setQuantity(float $newQuantity): bool
    {
        if ($newQuantity < 0) {
            return false;
        }
        
        $this->quantity = $newQuantity;
        $this->recount_needed = false;
        
        return $this->save();
    }
}