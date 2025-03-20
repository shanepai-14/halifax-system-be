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
        'payment_method',
        'amount',
        'payment_date',
        'reference_number',
        'received_by',
        'remarks'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime'
    ];

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
        return $this->belongsTo(Sale::class);
    }

    /**
     * Get the user who received this payment
     */
    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Generate a receipt number
     */
    public static function generateReceiptNumber()
    {
        $prefix = 'RCPT-';
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
            if (empty($payment->reference_number)) {
                $payment->reference_number = self::generateReceiptNumber();
            }
            
            // Set payment date to now if not provided
            if (empty($payment->payment_date)) {
                $payment->payment_date = now();
            }
        });
        
        // Update sale status after payment
        static::created(function ($payment) {
            if ($payment->sale) {
                $payment->sale->updateStatus();
            }
        });
        
        // Update sale status after payment is updated
        static::updated(function ($payment) {
            if ($payment->sale) {
                $payment->sale->updateStatus();
            }
        });
        
        // Update sale status after payment is deleted
        static::deleted(function ($payment) {
            if ($payment->sale) {
                $payment->sale->updateStatus();
            }
        });
    }
}