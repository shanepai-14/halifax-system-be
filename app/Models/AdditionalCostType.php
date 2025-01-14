<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalCostType extends Model
{
    use HasFactory;

    protected $primaryKey = 'cost_type_id';

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
        'is_deduction'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deduction' => 'boolean'
    ];

    public function purchaseOrderCosts()
    {
        return $this->hasMany(PurchaseOrderAdditionalCost::class, 'cost_type_id', 'cost_type_id');
    }
}
