<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'reference_type',
        'reference_id',
        'is_read',
        'data',
        'expiry_date'
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        'expiry_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Notification types constants
    const TYPE_INVENTORY_LOW = 'inventory_low';
    const TYPE_INVENTORY_OUT = 'inventory_out';
    const TYPE_PURCHASE_ORDER = 'purchase_order';
    const TYPE_SALE = 'sale';
    const TYPE_SYSTEM = 'system';

    /**
     * Get the user that owns the notification
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include notifications of a certain type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->is_read = true;
        return $this->save();
    }

    /**
     * Get notification icon based on type
     */
    public function getIconAttribute()
    {
        return match($this->type) {
            self::TYPE_INVENTORY_LOW => 'warning',
            self::TYPE_INVENTORY_OUT => 'error',
            self::TYPE_PURCHASE_ORDER => 'shopping-cart',
            self::TYPE_SALE => 'dollar',
            default => 'notification',
        };
    }

    /**
     * Get notification color based on type
     */
    public function getColorAttribute()
    {
        return match($this->type) {
            self::TYPE_INVENTORY_LOW => 'warning',
            self::TYPE_INVENTORY_OUT => 'error',
            self::TYPE_PURCHASE_ORDER => 'primary',
            self::TYPE_SALE => 'success',
            default => 'info',
        };
    }
}