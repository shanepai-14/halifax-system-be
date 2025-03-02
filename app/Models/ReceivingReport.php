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
        'attachment'
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
}
