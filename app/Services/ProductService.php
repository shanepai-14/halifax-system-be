<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
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

    public function getProductDeliveryReports(int $productId): array
    {
        // Validate product exists
        $product = Product::findOrFail($productId);

        // Get all sales records for this product
        $reports = DB::table('sale_items')
            ->select([
                'sale_items.id as sale_item_id',
                'sale_items.quantity',
                'sale_items.sold_price',
                'sale_items.total_sold_price as total',
                'sales.id as sale_id',
                'sales.invoice_number as dr_number',
                'sales.order_date as date_sold',
                'sales.delivery_date',
                'customers.customer_name',
                'sale_items.distribution_price'
            ])
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->where('sale_items.product_id', $productId)
            ->where('sales.status', '!=', Sale::STATUS_CANCELLED)
            ->orderBy('sales.order_date', 'desc')
            ->orderBy('sales.id', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'dr_number' => $item->dr_number,
                    'date_sold' => $item->date_sold,
                    'customer_name' => $item->customer_name ?? 'Walk-in Customer',
                    'sold_price' => (float) $item->sold_price,
                    'distribution_price' => (float) ($item->distribution_price ?? 0),
                    'quantity' => (int) $item->quantity,
                ];
            });

        return $reports->toArray();
    }

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
        return Product::with(['category', 'attribute', 'inventory'])
            ->get()
            ->map(function ($product) {
                // Add inventory quantity to the product data
                $product->inventory_quantity = $product->inventory ? $product->inventory->quantity : 0;
                return $product;
            });
    }

//   public function createProduct(array $data): Product
//     {
//         try {
//             DB::beginTransaction();

//             $category = ProductCategory::findOrFail($data['product_category_id']);
            
//             // Generate product code
//             $productCode = $this->generateProductCode($category, $data);

//             // Create product with generated code
//             $product = Product::create([
//                 'product_code' => $productCode,
//                 'product_name' => $data['product_name'],
//                 'product_category_id' => $data['product_category_id'],
//                 'reorder_level' => $data['reorder_level']
//             ]);

//             // Attach attributes if provided
//             if (isset($data['attributes']) && is_array($data['attributes'])) {
//                 foreach ($data['attributes'] as $attribute) {
//                     $product->attributes()->attach($attribute['attribute_id'], [
//                         'value' => $attribute['value']
//                     ]);
//                 }
//             }

//             DB::commit();
//             return $product->load(['category', 'attributes']);
//         } catch (Exception $e) {
//             DB::rollBack();
//             throw $e;
//         }
//     }

    
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
                'product_type'=> $data['product_type'],
                'product_category_id' => $data['product_category_id'],
                'attribute_id' => $data['attribute_id'] ?? null, // Single attribute
                'reorder_level' => $data['reorder_level']
            ]);

            DB::commit();
            return $product->load(['category', 'attribute']);
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

    // public function updateProduct(Product $product, array $data): Product
    // {
    //     try {
    //         DB::beginTransaction();

    //         // If category is changed, generate new product code
    //         if (isset($data['product_category_id']) && $data['product_category_id'] != $product->product_category_id) {
    //             $category = ProductCategory::findOrFail($data['product_category_id']);
    //             $data['product_code'] = $this->generateProductCode($category, $data);
    //         }

    //         $product->update([
    //             'product_code' => $data['product_code'] ?? $product->product_code,
    //             'product_name' => $data['product_name'] ?? $product->product_name,
    //             'product_category_id' => $data['product_category_id'] ?? $product->product_category_id,
    //             'reorder_level' => $data['reorder_level'] ?? $product->reorder_level
    //         ]);

    //         if (isset($data['attributes']) && is_array($data['attributes'])) {
    //             $attributes = collect($data['attributes'])->mapWithKeys(function ($item) {
    //                 return [$item['attribute_id'] => ['value' => $item['value']]];
    //             });
                
    //             $product->attributes()->sync($attributes);
    //         }

    //         DB::commit();
    //         return $product->load(['category', 'attributes']);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         throw $e;
    //     }
    // }

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
                'product_type'=> $data['product_type'] ?? $product->product_type,
                'product_category_id' => $data['product_category_id'] ?? $product->product_category_id,
                'attribute_id' => $data['attribute_id'] ?? $product->attribute_id, // Single attribute
                'reorder_level' => $data['reorder_level'] ?? $product->reorder_level
            ]);

            DB::commit();
            return $product->load(['category', 'attribute']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteProduct(Product $product): bool 
    {
        return $product->delete(); // This will soft delete
    }

    public function forceDeleteProduct(Product $product): bool
    {
        return $product->forceDelete(); // This will permanently delete
    }

    public function restoreProduct(Product $product): bool
    {
        return $product->restore(); // To restore a soft deleted product
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
    /**
     * Increment the quantity of a product
     *
     * @param int $productId
     * @param float $quantity
     * @param float $costPrice
     * @return Product|null
     */
    public function incrementProductQuantity(
        int $productId, 
        float $quantity, 
    ): ?Product {
        $product = Product::find($productId);
        
        if (!$product) {
            Log::warning("Product not found during inventory update", [
                'product_id' => $productId
            ]);
            return null;
        }
        
        // Increment quantity
        $currentQuantity = $product->quantity ?? 0;
        $product->quantity = $currentQuantity + $quantity;
        $product->save();
        
        return $product;
    }
    

    
    /**
     * Decrement product quantity (for sales or adjustments)
     *
     * @param int $productId
     * @param float $quantity
     * @return Product|null
     */
    public function decrementProductQuantity(
        int $productId, 
        float $quantity
    ): ?Product {
        $product = Product::find($productId);
        
        if (!$product) {
            Log::warning("Product not found during inventory reduction", [
                'product_id' => $productId
            ]);
            return null;
        }
        
        // Check if we have enough inventory
        $currentQuantity = $product->quantity ?? 0;
        if ($currentQuantity < $quantity) {
            Log::warning("Insufficient inventory for product", [
                'product_id' => $productId,
                'current_quantity' => $currentQuantity,
                'requested_quantity' => $quantity
            ]);
            // You can decide whether to throw an exception or just reduce to zero
            // For now, we'll just reduce to what we have
            $quantity = $currentQuantity;
        }
        
        // Decrement quantity
        $product->quantity = $currentQuantity - $quantity;
        $product->save();
        
        return $product;
    }
    
    /**
     * Check if a product is low on stock
     *
     * @param int $productId
     * @return bool
     */
    public function isLowStock(int $productId): bool
    {
        $product = Product::find($productId);
        
        if (!$product || !isset($product->reorder_level)) {
            return false;
        }
        
        return ($product->quantity ?? 0) <= $product->reorder_level;
    }
    
    /**
     * Get all products that are low on stock
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLowStockProducts()
    {
        return Product::whereRaw('quantity <= reorder_level')
            ->where('reorder_level', '>', 0)
            ->get();
    }
}