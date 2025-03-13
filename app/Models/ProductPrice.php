<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'regular_price',
        'wholesale_price',
        'walk_in_price',
        'cost_price',
        'is_active',
        'effective_from',
        'effective_to',
        'created_by'
    ];

    protected $casts = [
        'regular_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'walk_in_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime'
    ];

    /**
     * Relationship with Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created this price
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get only active prices
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(function($q) {
                         $q->whereNull('effective_to')
                           ->orWhere('effective_to', '>=', now());
                     })
                     ->where('effective_from', '<=', now());
    }
}

