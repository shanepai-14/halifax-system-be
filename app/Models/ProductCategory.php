<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'prefix',
        'description'
    ];

    protected static function boot()
    {
        parent::boot();
        
        // Auto-generate prefix if not provided
        static::creating(function ($category) {
            if (empty($category->prefix)) {
                $category->prefix = static::generatePrefix($category->name);
            } else {
                $category->prefix = strtoupper($category->prefix);
            }
        });

        static::updating(function ($category) {
            if ($category->isDirty('prefix')) {
                $category->prefix = strtoupper($category->prefix);
            }
        });
    }

    protected static function generatePrefix(string $name): string
    {
        // Generate prefix from name (first 3 letters)
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        
        // If prefix exists, add number until unique
        $original = $prefix;
        $counter = 1;
        
        while (static::where('prefix', $prefix)->exists()) {
            $prefix = substr($original, 0, 2) . $counter++;
        }
        
        return $prefix;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}