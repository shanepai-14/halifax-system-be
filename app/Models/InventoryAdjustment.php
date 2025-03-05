<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'user_id',
        'adjustment_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'deleted_at' => 'datetime'
    ];

    // Adjustment types
    const TYPE_ADDITION = 'addition';
    const TYPE_REDUCTION = 'reduction';
    const TYPE_DAMAGE = 'damage';
    const TYPE_LOSS = 'loss';
    const TYPE_RETURN = 'return';
    const TYPE_CORRECTION = 'correction';

    /**
     * Get the valid adjustment types
     */
    public static function getAdjustmentTypes(): array
    {
        return [
            self::TYPE_ADDITION => 'Addition',
            self::TYPE_REDUCTION => 'Reduction',
            self::TYPE_DAMAGE => 'Damage',
            self::TYPE_LOSS => 'Loss',
            self::TYPE_RETURN => 'Return',
            self::TYPE_CORRECTION => 'Correction'
        ];
    }

    /**
     * Check if this is a positive adjustment (increases inventory)
     */
    public function isPositiveAdjustment(): bool
    {
        return in_array($this->adjustment_type, [
            self::TYPE_ADDITION,
            self::TYPE_RETURN
        ]);
    }

    /**
     * Get the product that this adjustment is for
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Get the user who made this adjustment
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope query to filter by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope query to filter by adjustment type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('adjustment_type', $type);
    }

    /**
     * Scope query to filter by reason contains
     */
    public function scopeWithReasonContaining($query, $text)
    {
        return $query->where('reason', 'like', "%{$text}%");
    }

    /**
     * Scope query to filter by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Calculate the impact on inventory value
     */
    public function getValueImpactAttribute()
    {
        // No impact if no product relation or no average cost
        if (!$this->product || !$this->product->inventory || $this->product->inventory->avg_cost_price <= 0) {
            return 0;
        }
        
        $avgCost = $this->product->inventory->avg_cost_price;
        $impact = $this->quantity * $avgCost;
        
        return $this->isPositiveAdjustment() ? $impact : -$impact;
    }
}