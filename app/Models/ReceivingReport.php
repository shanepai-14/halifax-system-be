<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAttachments;

class ReceivingReport extends Model
{
    use HasFactory, HasAttachments;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'receiving_reports';
    protected $primaryKey = 'rr_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'po_id',
        'invoice',
        'batch_number',
        'term',
        'is_paid',
        'attachment',
        'items_total',           // New column for storing items total
        'additional_costs_total', // New column for storing additional costs total
        'grand_total'            // New column for storing grand total
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_paid' => 'boolean',
        'items_total' => 'decimal:2',
        'additional_costs_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Boot the model to add auto-generation logic for batch_number.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($report) {
            $date = now()->format('Ymd'); // Get the current date in YYYYMMDD format
            $lastBatch = self::whereDate('created_at', now()->toDateString())
                ->max('batch_number');

            // Determine the new incremented batch number
            $increment = $lastBatch ? (int)substr($lastBatch, -4) + 1 : 1;

            // Format the batch number as YYYYMMDDXXXX (date + 4-digit number)
            $report->batch_number = $date . str_pad($increment, 4, '0', STR_PAD_LEFT);
        });

        // // Auto-calculate totals when creating or updating
        // static::saved(function ($report) {
        //     $report->calculateAndUpdateTotals();
        // });
    }

    /**
     * Get the related purchase order.
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'po_id');
    }

    public function additionalCosts()
    {
        return $this->hasMany(PurchaseOrderAdditionalCost::class, 'rr_id', 'rr_id');
    }

    public function received_items()
    {
        return $this->hasMany(PurchaseOrderReceivedItem::class, 'rr_id', 'rr_id');
    }

    /**
     * Calculate items total (real-time calculation)
     *
     * @return float
     */
    public function calculateItemsTotal(): float
    {
        return $this->received_items->sum(function ($item) {
            return $item->received_quantity * $item->cost_price;
        });
    }

    /**
     * Calculate additional costs total (real-time calculation)
     *
     * @return float
     */
    public function calculateAdditionalCostsTotal(): float
    {
        return $this->additionalCosts->sum(function ($cost) {
            // Handle deductions vs additions based on cost type
            return $cost->costType && $cost->costType->is_deduction 
                ? -$cost->amount 
                : $cost->amount;
        });
    }

    /**
     * Calculate grand total (real-time calculation)
     *
     * @return float
     */
    public function calculateGrandTotal(): float
    {
        return $this->calculateItemsTotal() + $this->calculateAdditionalCostsTotal();
    }

    /**
     * Get items total (uses stored value or calculates if not stored)
     *
     * @return float
     */
    public function getItemsTotal(): float
    {
        return $this->items_total ?? $this->calculateItemsTotal();
    }

    /**
     * Get additional costs total (uses stored value or calculates if not stored)
     *
     * @return float
     */
    public function getAdditionalCostsTotal(): float
    {
        return $this->additional_costs_total ?? $this->calculateAdditionalCostsTotal();
    }

    /**
     * Get grand total (uses stored value or calculates if not stored)
     *
     * @return float
     */
    public function getGrandTotal(): float
    {
        return $this->grand_total ?? $this->calculateGrandTotal();
    }

    /**
     * Calculate and update stored totals
     * This method should be called whenever items or additional costs are modified
     *
     * @return bool
     */
    public function calculateAndUpdateTotals(): bool
    {
        // Skip if we're in the middle of saving to avoid infinite recursion
        if ($this->isDirty()) {
            return false;
        }

        $itemsTotal = $this->calculateItemsTotal();
        $additionalCostsTotal = $this->calculateAdditionalCostsTotal();
        $grandTotal = $itemsTotal + $additionalCostsTotal;

        // Only update if values have changed to avoid unnecessary database calls
        if ($this->items_total != $itemsTotal || 
            $this->additional_costs_total != $additionalCostsTotal || 
            $this->grand_total != $grandTotal) {
            
            return $this->updateQuietly([
                'items_total' => $itemsTotal,
                'additional_costs_total' => $additionalCostsTotal,
                'grand_total' => $grandTotal
            ]);
        }

        return true;
    }

    /**
     * Refresh totals - force recalculation and update
     * Useful when you know the related data has changed
     *
     * @return bool
     */
    public function refreshTotals(): bool
    {
        $this->load(['received_items', 'additionalCosts.costType']);
        
        $itemsTotal = $this->calculateItemsTotal();
        $additionalCostsTotal = $this->calculateAdditionalCostsTotal();
        $grandTotal = $itemsTotal + $additionalCostsTotal;

        return $this->updateQuietly([
            'items_total' => $itemsTotal,
            'additional_costs_total' => $additionalCostsTotal,
            'grand_total' => $grandTotal
        ]);
    }

    /**
     * Get a detailed breakdown of the receiving report totals
     *
     * @return array
     */
    public function getTotalBreakdown(): array
    {
        $itemsTotal = $this->getItemsTotal();
        $additionalCostsTotal = $this->getAdditionalCostsTotal();
        $grandTotal = $this->getGrandTotal();

        return [
            'items_total' => $itemsTotal,
            'additional_costs_total' => $additionalCostsTotal,
            'grand_total' => $grandTotal,
            'items_count' => $this->received_items->count(),
            'additional_costs_count' => $this->additionalCosts->count(),
            'additional_costs_breakdown' => $this->additionalCosts->map(function ($cost) {
                return [
                    'cost_type' => $cost->costType ? $cost->costType->name : 'Unknown',
                    'amount' => $cost->amount,
                    'is_deduction' => $cost->costType ? $cost->costType->is_deduction : false,
                    'effective_amount' => $cost->costType && $cost->costType->is_deduction ? -$cost->amount : $cost->amount,
                    'remarks' => $cost->remarks
                ];
            })
        ];
    }

    /**
     * Accessor for items_total_formatted
     */
    public function getItemsTotalFormattedAttribute(): string
    {
        return number_format($this->getItemsTotal(), 2);
    }

    /**
     * Accessor for additional_costs_total_formatted
     */
    public function getAdditionalCostsTotalFormattedAttribute(): string
    {
        return number_format($this->getAdditionalCostsTotal(), 2);
    }

    /**
     * Accessor for grand_total_formatted
     */
    public function getGrandTotalFormattedAttribute(): string
    {
        return number_format($this->getGrandTotal(), 2);
    }
}