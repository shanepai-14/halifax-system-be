<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'location',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationships
    public function transfers()
    {
        return $this->hasMany(Transfer::class, 'to_warehouse_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Methods
    public function getTotalTransfersAttribute()
    {
        return $this->transfers()->count();
    }

    public function getCompletedTransfersAttribute()
    {
        return $this->transfers()->where('status', 'completed')->count();
    }

    public function getPendingTransfersAttribute()
    {
        return $this->transfers()->where('status', 'pending')->count();
    }
}