<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PettyCashFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_reference',
        'date',
        'amount',
        'description',
        'created_by',
        'approved_by',
        'status'
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the user who created this fund
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this fund
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Generate a unique transaction reference
     */
    public static function generateTransactionReference()
    {
        $prefix = 'PCF';
        $year = date('Y');
        $month = date('m');
        
        $lastFund = self::where('transaction_reference', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('transaction_reference', 'desc')
            ->first();
        
        if ($lastFund) {
            $sequence = (int) substr($lastFund->transaction_reference, -4);
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
        static::creating(function ($fund) {
            if (empty($fund->transaction_reference)) {
                $fund->transaction_reference = self::generateTransactionReference();
            }
            
            // Set default status
            if (empty($fund->status)) {
                $fund->status = self::STATUS_PENDING;
            }
        });
    }
}