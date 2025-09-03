<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_number',
        'to_warehouse_id',
        'created_by',
        'status',
        'delivery_date',
        'total_value',
        'notes'
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'total_value' => 'decimal:2'
    ];

    // Status constants - simplified workflow
    const STATUS_IN_TRANSIT = 'in_transit';    // Created and inventory adjusted
    const STATUS_COMPLETED = 'completed';      // Delivered/received
    const STATUS_CANCELLED = 'cancelled';      // Cancelled with inventory restored

    // Relationships
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(TransferItem::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByWarehouse($query, $warehouseId)
    {
        return $query->where('to_warehouse_id', $warehouseId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('delivery_date', [$startDate, $endDate]);
    }

    // Methods
    public function canBeUpdated()
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function canBeCancelled()
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function canBeCompleted()
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function getTotalItemsAttribute()
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute()
    {
        return $this->items()->sum('quantity');
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            self::STATUS_IN_TRANSIT => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'error',
            default => 'default'
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_IN_TRANSIT => 'In Transit',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    public static function generateTransferNumber()
    {
        $prefix = 'TR';
        $date = date('Ymd');
        $lastTransfer = self::whereDate('created_at', today())
            ->orderBy('id', 'desc')
            ->first();
        
        $sequence = $lastTransfer ? (intval(substr($lastTransfer->transfer_number, -4)) + 1) : 1;
        
        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}