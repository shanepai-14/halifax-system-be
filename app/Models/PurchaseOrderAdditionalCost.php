<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderAdditionalCost extends Model
{
    use HasFactory;

    protected $primaryKey = 'po_cost_id';

    protected $fillable = [
        'rr_id',
        'cost_type_id',
        'amount',
        'remarks'
    ];

    protected $casts = [
        'amount' => 'decimal:2'
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(ReceivingReport::class, 'rr_id', 'rr_id');
    }

    public function costType()
    {
        return $this->belongsTo(AdditionalCostType::class, 'cost_type_id', 'cost_type_id');
    }
}
