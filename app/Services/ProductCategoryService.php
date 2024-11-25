<?php

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductCategoryService
{
    public function getAllCategories(): Collection
    {
        return ProductCategory::all();
    }

    public function createCategory(array $data): ProductCategory
    {
        $validated = $this->validatePrefix($data);
        return ProductCategory::create($validated);
    }
    

    public function updateCategory(ProductCategory $category, array $data): bool
    {
        $validated = $this->validatePrefix($data);
        return $category->update($validated);
    }
    public function deleteCategory(ProductCategory $category): bool
    {
        if ($category->products()->exists()) {
            throw new Exception('Cannot delete category with associated products');
        }

        return $category->delete();
    }

    public function getCategoryWithProducts(ProductCategory $category): ProductCategory
    {
        return $category->load('products');
    }

    protected function validatePrefix(array $data): array
    {
        if (isset($data['prefix'])) {
            // Clean prefix
            $data['prefix'] = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['prefix']), 0, 3));
            
            // Check uniqueness (excluding current category if updating)
            $query = ProductCategory::where('prefix', $data['prefix']);
            if (isset($data['id'])) {
                $query->where('id', '!=', $data['id']);
            }
            
            if ($query->exists()) {
                throw new Exception('Prefix must be unique');
            }
        }
        
        return $data;
    }
}