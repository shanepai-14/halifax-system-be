<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAttachments;

class Sale extends Model
{
    use HasFactory, SoftDeletes, HasAttachments;

    protected $fillable = [
        'invoice_number',
        'customer_id',
        'user_id',
        'status',
        'customer_type',
        'payment_method',
        'order_date',
        'delivery_date',
        'address',
        'city',
        'phone',
        'cogs',
        'profit',
        'total',
        'amount_received',
        'change',
        'remarks',
        'is_delivered',
        'term_days',
        'delivery_fee',
        'cutting_charges'
    ];

    protected $casts = [
        'cogs' => 'decimal:2',
        'profit' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_received' => 'decimal:2',
        'change' => 'decimal:2',
        'is_delivered' => 'boolean'
    ];

    // Define status constants
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RETURNED = 'returned';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_UNPAID = 'unpaid';
    const STATUS_PARTIALLY_RETURNED = 'partially_returned';

    // Customer type constants
    const TYPE_REGULAR = 'regular';
    const TYPE_WALK_IN = 'walkin';
    const TYPE_WHOLESALE = 'wholesale';

    // Payment method constants
    const PAYMENT_CASH = 'cash';
    const PAYMENT_COD = 'cod';
    const PAYMENT_CHEQUE = 'cheque';
    const PAYMENT_ONLINE = 'online';
    const PAYMENT_TERM = 'term';

    /**
     * Get the customer associated with the sale
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Get the user who created the sale
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the items associated with the sale
     */
    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the returns associated with the sale
     */
    public function returns()
    {
        return $this->hasMany(SaleReturn::class);
    }

    /**
     * Get the payments associated with the sale
     */
    public function payments()
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * Calculate the amount due
     */
    public function getAmountDueAttribute()
    {
        return $this->total - $this->payments()->sum('amount');
    }

    /**
     * Calculate if the sale is fully paid
     */
    public function getIsPaidAttribute()
    {
        return $this->amount_due <= 0;
    }


 public static function generateInvoiceNumber()
{
    $prefix = 'DR-';

    // Find the latest invoice number starting with DR-
    $latestInvoice = self::where('invoice_number', 'like', "{$prefix}%")
        ->orderBy('invoice_number', 'desc')
        ->first();

    if ($latestInvoice) {
        // Get the numeric part after the prefix
        $lastNumber = (int) str_replace($prefix, '', $latestInvoice->invoice_number);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    // Return formatted DR number with leading zeros (e.g. DR-00001)
    return sprintf("%s%05d", $prefix, $nextNumber);
}


}