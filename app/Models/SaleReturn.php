<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAttachments;

class SaleReturn extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $fillable = [
        'sale_id',
        'credit_memo_number',
        'user_id',
        'customer_id',
        'total_amount',
        'return_date',
        'remarks',
        'status',
        'refund_method',
        'refund_amount'
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2'
    ];

    // Define status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_COMPLETED = 'completed';
    
    // Define refund method constants
    const REFUND_CASH = 'cash';
    const REFUND_STORE_CREDIT = 'store_credit';
    const REFUND_REPLACEMENT = 'replacement';
    const REFUND_CHEQUE = 'cheque';
    const REFUND_BANK_TRANSFER = 'bank_transfer';
    const REFUND_NONE = 'none';

    /**
     * Get the sale associated with this return
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the user who processed this return
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the customer associated with this return
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the items being returned
     */
    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * Generate a unique credit memo number
     */
    public static function generateCreditMemoNumber()
    {
        $prefix = 'CM-';
        $year = date('Y');
        $month = date('m');
        
        $latestCreditMemo = self::where('credit_memo_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('credit_memo_number', 'desc')
            ->first();
        
        if ($latestCreditMemo) {
            $sequence = (int) substr($latestCreditMemo->credit_memo_number, -5);
            $sequence++;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%s%s%s%05d", $prefix, $year, $month, $sequence);
    }

    /**
     * Calculate total return quantity
     */
    public function getTotalReturnedQuantityAttribute()
    {
        return $this->returns()->join('sale_return_items', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->sum('sale_return_items.quantity');
    }

    /**
     * Calculate total tax amount
     */
    public function getTotalTaxAttribute()
    {
        return $this->items()->sum('tax_amount');
    }

    /**
     * Calculate total discount amount
     */
    public function getTotalDiscountAttribute()
    {
        return $this->items()->sum('discount_amount');
    }

    /**
     * Calculate if this return is for all items in the sale
     */
    public function getIsFullReturnAttribute()
    {
        return $this->total_quantity >= $this->sale->total_quantity;
    }

    /**
     * Complete the return process
     */
    public function complete()
    {
        $this->status = self::STATUS_COMPLETED;
        $this->save();
        
        
        return $this;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate credit memo number
        static::creating(function ($return) {
            if (empty($return->credit_memo_number)) {
                $return->credit_memo_number = self::generateCreditMemoNumber();
            }
        });
    }
}