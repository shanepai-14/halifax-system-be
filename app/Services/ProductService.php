<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class ProductService
{

    private const PRODUCT_TYPES = [
        'raw' => 'R',      // Raw materials
        'finished' => 'F',  // Finished products
        'custom' => 'C',    // Custom orders
    ];

    public function getProductById(string $id): Product
{
    try {
        return Product::with(['category', 'attributes'])
            ->findOrFail($id);
    } catch (ModelNotFoundException $e) {
        throw new Exception("Product with ID {$id} not found");
    }
}

    protected function generateProductCode(ProductCategory $category, array $data): string
    {
        try {
            // Use category prefix directly from database
            $prefix = $category->prefix;
            
            // Get product type (default to 'F' for finished products)
            $type = $data['product_type'] ;
            $typeCode = self::PRODUCT_TYPES[$type];

            // Get the current year's last 2 digits
            $year = Carbon::now()->format('y');

            // Get the last product number for this category and year
            $lastProduct = Product::where('product_code', 'like', "{$prefix}-{$typeCode}-{$year}%")
                                ->orderBy('product_code', 'desc')
                                ->first();
        
                                Log::alert($prefix. " ". $typeCode. " ". $year);
                                Log::alert($lastProduct);


            if ($lastProduct) {
                $lastNumber = (int) substr($lastProduct->product_code, -4);
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            // Format: XXX-T-YY-NNNN
            return sprintf(
                '%s-%s-%s-%04d',
                $prefix,
                $typeCode,
                $year,
                $newNumber
            );
        } catch (Exception $e) {
            throw new Exception("Error generating product code: " . $e->getMessage());
        }
    }

    public function getAllProducts(): Collection
    {
        return Product::with(['category', 'attributes'])->get();
    }

  public function createProduct(array $data): Product
    {
        try {
            DB::beginTransaction();

            $category = ProductCategory::findOrFail($data['product_category_id']);
            
            // Generate product code
            $productCode = $this->generateProductCode($category, $data);

            // Create product with generated code
            $product = Product::create([
                'product_code' => $productCode,
                'product_name' => $data['product_name'],
                'product_category_id' => $data['product_category_id'],
                'reorder_level' => $data['reorder_level']
            ]);

            // Attach attributes if provided
            if (isset($data['attributes']) && is_array($data['attributes'])) {
                foreach ($data['attributes'] as $attribute) {
                    $product->attributes()->attach($attribute['attribute_id'], [
                        'value' => $attribute['value']
                    ]);
                }
            }

            DB::commit();
            return $product->load(['category', 'attributes']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function searchProducts(array $criteria): Collection
    {
        $query = Product::with(['category', 'attributes']);

        if (isset($criteria['code'])) {
            $query->where('product_code', 'like', "%{$criteria['code']}%");
        }

        if (isset($criteria['category_id'])) {
            $query->where('product_category_id', $criteria['category_id']);
        }

        if (isset($criteria['type'])) {
            $typeCode = self::PRODUCT_TYPES[$criteria['type']] ?? null;
            if ($typeCode) {
                $query->where('product_code', 'like', "%-{$typeCode}-%");
            }
        }

        if (isset($criteria['year'])) {
            $query->where('product_code', 'like', "%-{$criteria['year']}-%");
        }

        return $query->get();
    }

    public function updateProduct(Product $product, array $data): Product
    {
        try {
            DB::beginTransaction();

            // If category is changed, generate new product code
            if (isset($data['product_category_id']) && $data['product_category_id'] != $product->product_category_id) {
                $category = ProductCategory::findOrFail($data['product_category_id']);
                $data['product_code'] = $this->generateProductCode($category, $data);
            }

            $product->update([
                'product_code' => $data['product_code'] ?? $product->product_code,
                'product_name' => $data['product_name'] ?? $product->product_name,
                'product_category_id' => $data['product_category_id'] ?? $product->product_category_id,
                'reorder_level' => $data['reorder_level'] ?? $product->reorder_level
            ]);

            if (isset($data['attributes']) && is_array($data['attributes'])) {
                $attributes = collect($data['attributes'])->mapWithKeys(function ($item) {
                    return [$item['attribute_id'] => ['value' => $item['value']]];
                });
                
                $product->attributes()->sync($attributes);
            }

            DB::commit();
            return $product->load(['category', 'attributes']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteProduct(Product $product): bool
    {
        try {
            DB::beginTransaction();
            
            // Delete related attribute values first
            $product->attributes()->detach();
            
            // Delete the product
            $deleted = $product->delete();
            
            DB::commit();
            return $deleted;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getProductDetails(Product $product): Product
    {
        return $product->load(['category', 'attributes']);
    }

    public function getProductTypes(): array
    {
        return self::PRODUCT_TYPES;
    }

    public function uploadProductImage(string $id, UploadedFile $image): Product
{
    try {
        DB::beginTransaction();

        $product = $this->getProductById($id);
        
        // Delete old image if exists
        if ($product->product_image) {
            Storage::disk('public')->delete($product->product_image);
        }

        // Store with custom filename (product ID + extension)
        $extension = $image->getClientOriginalExtension();
        $fileName = "product_{$id}.{$extension}";
        $path = $image->storeAs('products', $fileName, 'public');
        
        $product->update(['product_image' => $path]);

        DB::commit();
        return $product;
    } catch (Exception $e) {
        DB::rollBack();
        throw new Exception('Failed to upload product image: ' . $e->getMessage());
    }
}
}