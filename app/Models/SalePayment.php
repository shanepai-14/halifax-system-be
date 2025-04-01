<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_id',
        'user_id',
        'payment_method',
        'amount',
        'change',
        'payment_date',
        'reference_number',
        'status',
        'received_by',
        'void_reason',
        'voided_at',
        'voided_by',
        'remarks'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'voided_at' => 'datetime'
    ];

    // Payment status constants
    const STATUS_COMPLETED = 'completed';
    const STATUS_VOIDED = 'voided';

    // Payment method constants
    const METHOD_CASH = 'cash';
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_DEBIT_CARD = 'debit_card';
    const METHOD_CHEQUE = 'cheque';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_ONLINE = 'online';
    const METHOD_MOBILE_PAYMENT = 'mobile_payment';
    const METHOD_STORE_CREDIT = 'store_credit';

    /**
     * Get the sale associated with this payment
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    /**
     * Get the user who created this payment
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who received this payment
     */
    public function receivedBy()
    {
        return $this->belongsTo(Customer::class, 'received_by');
    }

    /**
     * Get the user who voided this payment
     */
    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Check if the payment is voided
     */
    public function isVoided()
    {
        return $this->status === self::STATUS_VOIDED;
    }

    /**
     * Generate a receipt number
     */
    public static function generateReceiptNumber()
    {
        $prefix = 'RCT-';
        $year = date('Y');
        $month = date('m');
        
        $latestReceipt = self::where('reference_number', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('reference_number', 'desc')
            ->first();
        
        if ($latestReceipt) {
            $sequence = (int) substr($latestReceipt->reference_number, -5);
            $sequence++;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%s%s%s%05d", $prefix, $year, $month, $sequence);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Generate receipt number if not provided
        static::creating(function ($payment) {
            // Set default status to completed
            if (empty($payment->status)) {
                $payment->status = self::STATUS_COMPLETED;
            }
            
            // Generate reference number if not provided and payment method is cash
            if (empty($payment->reference_number) && $payment->payment_method === self::METHOD_CASH) {
                $payment->reference_number = self::generateReceiptNumber();
            }
        });
    }
}