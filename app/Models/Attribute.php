<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Attribute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attribute_name',
        'unit_of_measurement'
    ];

    // Relationship with Products through product_attributes
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_attributes')
                    ->withPivot('value')
                    ->withTimestamps();
    }
}