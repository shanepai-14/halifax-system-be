<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Glass', 'prefix' => 'GLS'],
            ['name' => 'Aluminum', 'prefix' => 'ALM'],
            ['name' => 'Breezeway', 'prefix' => 'BRZ'],
            ['name' => 'Jalousies Frame', 'prefix' => 'JLF'],
            ['name' => 'UPVC', 'prefix' => 'UPC'],
            ['name' => 'Services', 'prefix' => 'SRV'],
            ['name' => 'Other', 'prefix' => 'OTH'],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}