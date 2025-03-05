<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'transaction_type',
        'reference_type',
        'reference_id',
        'quantity',
        'quantity_before',
        'quantity_after',
        'cost_price',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'cost_price' => 'decimal:2'
    ];

    // Transaction types
    const TYPE_PURCHASE = 'purchase';
    const TYPE_SALES = 'sales';
    const TYPE_ADJUSTMENT_IN = 'adjustment_in';
    const TYPE_ADJUSTMENT_OUT = 'adjustment_out';
    const TYPE_INVENTORY_COUNT = 'inventory_count';
    const TYPE_RETURN = 'return';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';

    // Reference types
    const REF_PURCHASE_ORDER = 'purchase_order';
    const REF_SALES_ORDER = 'sales_order';
    const REF_ADJUSTMENT = 'adjustment';
    const REF_INVENTORY_COUNT = 'inventory_count';
    const REF_RETURN = 'return';
    const REF_TRANSFER = 'transfer';

    /**
     * Get the product that this log is for
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Get the user who made this change
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Scope for filtering logs by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for filtering logs by transaction type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope for filtering logs by reference
     */
    public function scopeWithReference($query, $refType, $refId = null)
    {
        $query->where('reference_type', $refType);
        
        if ($refId !== null) {
            $query->where('reference_id', $refId);
        }
        
        return $query;
    }

    /**
     * Scope for filtering logs by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get properly formatted date for display
     */
    public function getFormattedDateAttribute()
    {
        return $this->created_at->format('M d, Y h:i A');
    }

    /**
     * Get the formatted quantity change (with + or - sign)
     */
    public function getQuantityChangeAttribute()
    {
        $isPositive = in_array($this->transaction_type, [
            self::TYPE_PURCHASE, 
            self::TYPE_ADJUSTMENT_IN,
            self::TYPE_RETURN,
            self::TYPE_TRANSFER_IN
        ]);
        
        return $isPositive ? "+{$this->quantity}" : "-{$this->quantity}";
    }
}