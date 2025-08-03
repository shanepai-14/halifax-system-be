<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class CustomerCustomPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'product_id',
        'min_quantity',
        'max_quantity',
        'price',
        'label',
        'is_active',
        'effective_from',
        'effective_to',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'min_quantity' => 'integer',
        'max_quantity' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Price type constants
    const PRICE_TYPE_REGULAR = 'regular';
    const PRICE_TYPE_WHOLESALE = 'wholesale';
    const PRICE_TYPE_WALK_IN = 'walk_in';

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where('effective_from', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('effective_to')
                          ->orWhere('effective_to', '>=', now());
                    });
    }

    public function scopeForQuantity($query, int $quantity)
    {
        return $query->where('min_quantity', '<=', $quantity)
                    ->where(function ($q) use ($quantity) {
                        $q->whereNull('max_quantity')
                          ->orWhere('max_quantity', '>=', $quantity);
                    });
    }

    // Check if quantity falls within this price range
    public function appliesToQuantity(int $quantity): bool
    {
        return $quantity >= $this->min_quantity && 
               ($this->max_quantity === null || $quantity <= $this->max_quantity);
    }

    // Get quantity range display
    public function getQuantityRangeAttribute(): string
    {
        if ($this->max_quantity === null) {
            return "{$this->min_quantity}+";
        }
        
        if ($this->min_quantity === $this->max_quantity) {
            return (string) $this->min_quantity;
        }
        
        return "{$this->min_quantity} - {$this->max_quantity}";
    }
}