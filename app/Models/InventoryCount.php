<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'status',
        'created_by',
        'finalized_by',
        'finalized_at'
    ];

    protected $casts = [
        'finalized_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Count status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_FINALIZED = 'finalized';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the count items for this inventory count
     */
    public function items()
    {
        return $this->hasMany(InventoryCountItem::class, 'count_id', 'id');
    }

    /**
     * Get the user who created this count
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the user who finalized this count
     */
    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by', 'id');
    }

    /**
     * Check if this count is still editable
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Get counts with items that have discrepancies
     */
    public function getDiscrepancyItems()
    {
        return $this->items()->whereRaw('counted_quantity != system_quantity')->get();
    }

    /**
     * Get the count of items with discrepancies
     */
    public function getDiscrepancyCountAttribute()
    {
        return $this->items()->whereRaw('counted_quantity != system_quantity')->count();
    }

    /**
     * Get the total count of items
     */
    public function getItemCountAttribute()
    {
        return $this->items()->count();
    }

    /**
     * Get the total value of discrepancies
     */
    public function getDiscrepancyValueAttribute()
    {
        $total = 0;
        $discrepancies = $this->getDiscrepancyItems();
        
        foreach ($discrepancies as $item) {
            $diff = $item->counted_quantity - $item->system_quantity;
            $avgCost = $item->product->inventory->avg_cost_price ?? 0;
            $total += $diff * $avgCost;
        }
        
        return $total;
    }

    /**
     * Mark count as in progress
     */
    public function markInProgress(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }
        
        $this->status = self::STATUS_IN_PROGRESS;
        return $this->save();
    }

    /**
     * Finalize the count and apply discrepancies
     */
    public function finalize(int $userId): bool
    {
        if (!$this->isEditable()) {
            return false;
        }
        
        $this->status = self::STATUS_FINALIZED;
        $this->finalized_by = $userId;
        $this->finalized_at = now();
        
        return $this->save();
    }

    /**
     * Cancel the count
     */
    public function cancel(): bool
    {
        if (!$this->isEditable()) {
            return false;
        }
        
        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }
}