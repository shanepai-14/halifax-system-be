<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_reference',
        'employee_id',
        'date',
        'purpose',
        'expense',
        'description',
        'amount_issued',
        'amount_spent',
        'amount_returned',
        'receipt_attachment',
        'remarks',
        'status',
        'balance_before',
        'balance_after',
        'balance_change',
        'issued_by',
        'approved_by'
    ];

    protected $casts = [
        'date' => 'date',
        'amount_issued' => 'decimal:2',
        'amount_spent' => 'decimal:2',
        'amount_returned' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_ISSUED = 'issued';
    const STATUS_SETTLED = 'settled';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the employee associated with this transaction
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who issued this transaction
     */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the user who approved this transaction
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the remaining amount to be returned by the employee
     */
    public function getRemainingAmountAttribute()
    {
        return $this->amount_issued - $this->amount_spent - $this->amount_returned;
    }

    /**
     * Generate a unique transaction reference
     */
    public static function generateTransactionReference()
    {
        $prefix = 'PCT';
        $year = date('Y');
        $month = date('m');
        
        $lastTransaction = self::where('transaction_reference', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('transaction_reference', 'desc')
            ->first();
        
        if ($lastTransaction) {
            $sequence = (int) substr($lastTransaction->transaction_reference, -4);
            $sequence++;
        } else {
            $sequence = 1;
        }
        
        return sprintf("%s%s%s%04d", $prefix, $year, $month, $sequence);
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();
        
        // Generate transaction reference if not provided
        static::creating(function ($transaction) {
            if (empty($transaction->transaction_reference)) {
                $transaction->transaction_reference = self::generateTransactionReference();
            }
            
            // Set default status
            if (empty($transaction->status)) {
                $transaction->status = self::STATUS_ISSUED;
            }
        });
    }
}