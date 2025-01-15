<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAttachments;

class PurchaseOrder extends Model
{
    use HasFactory, HasAttachments;

    protected $primaryKey = 'po_id';

    protected $fillable = [
        'supplier_id',
        'po_number', 
        'batch_number',
        'po_date',
        'total_amount',
        'status',
        'invoice',
        'remarks',
        'attachment'
    ];

    protected $casts = [
        'po_date' => 'datetime',
        'total_amount' => 'decimal:2'
    ];

    // Relationship with supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id', 'supplier_id');
    }

    // Relationship with items
    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'po_id', 'po_id');
    }

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    // Helper method to update status based on received quantities
    public function updateStatus()
    {
        $items = $this->items;
        $allReceived = true;
        $anyReceived = false;

        foreach ($items as $item) {
            if ($item->received_quantity < $item->requested_quantity) {
                $allReceived = false;
            }
            if ($item->received_quantity > 0) {
                $anyReceived = true;
            }
        }

        if ($allReceived) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($anyReceived) {
            $this->status = self::STATUS_PARTIALLY_RECEIVED;
        }

        $this->save();
    }
    public function additionalCosts()
    {
        return $this->hasMany(PurchaseOrderAdditionalCost::class, 'po_id', 'po_id');
    }

    public function calculateTotalWithCosts()
    {
        $subtotal = $this->items->sum(function($item) {
            return $item->price * $item->requested_quantity;
        });

        $additionalCosts = $this->additionalCosts->sum(function($cost) {
            return $cost->costType->is_deduction ? -$cost->amount : $cost->amount;
        });

        return $subtotal + $additionalCosts;
    }

    public function received_items()
    {
        return $this->hasMany(PurchaseOrderReceivedItem::class, 'po_id', 'po_id');
    }


}