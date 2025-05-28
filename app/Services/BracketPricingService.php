<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceBracket;
use App\Models\BracketItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class BracketPricingService
{
    /**
     * Create a price bracket with bracket items for a product
     *
     * @param int $productId
     * @param array $bracketData
     * @return ProductPriceBracket
     * @throws Exception
     */
    public function createBracketWithItems(int $productId, array $bracketData): ProductPriceBracket
    {
        $product = Product::findOrFail($productId);

        try {
            DB::beginTransaction();

            // Create the main bracket
            $bracket = ProductPriceBracket::create([
                'product_id' => $productId,
                'is_selected' => $bracketData['is_selected'] ?? false,
                'effective_from' => $bracketData['effective_from'],
                'effective_to' => $bracketData['effective_to'] ?? null,
                'created_by' => Auth::id()
            ]);

            // Create bracket items
            if (!empty($bracketData['bracket_items'])) {
                foreach ($bracketData['bracket_items'] as $itemData) {
                    $this->validateBracketItemData($itemData);
                    
                    BracketItem::create([
                        'bracket_id' => $bracket->id,
                        'min_quantity' => $itemData['min_quantity'],
                        'max_quantity' => $itemData['max_quantity'] ?? null,
                        'price' => $itemData['price'],
                        'price_type' => $itemData['price_type'],
                        'is_active' => $itemData['is_active'] ?? true
                    ]);
                }
            }

            // If this bracket is selected, deactivate others and enable bracket pricing
            if ($bracket->is_selected) {
                $this->activateBracket($bracket);
            }

            DB::commit();
            return $bracket->load('bracketItems');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update a bracket and its items
     *
     * @param int $bracketId
     * @param array $bracketData
     * @return ProductPriceBracket
     * @throws Exception
     */
    public function updateBracketWithItems(int $bracketId, array $bracketData): ProductPriceBracket
    {
        $bracket = ProductPriceBracket::findOrFail($bracketId);

        try {
            DB::beginTransaction();

            // Update bracket details
            $bracket->update([
                'is_selected' => $bracketData['is_selected'] ?? $bracket->is_selected,
                'effective_from' => $bracketData['effective_from'] ?? $bracket->effective_from,
                'effective_to' => $bracketData['effective_to'] ?? $bracket->effective_to,
            ]);

            // Handle bracket items if provided
            if (isset($bracketData['bracket_items'])) {
                $this->updateBracketItems($bracket, $bracketData['bracket_items']);
            }

            // If this bracket is now selected, activate it
            if ($bracket->is_selected) {
                $this->activateBracket($bracket);
            }

            DB::commit();
            return $bracket->load('bracketItems');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update bracket items for a bracket
     *
     * @param ProductPriceBracket $bracket
     * @param array $itemsData
     * @return void
     * @throws Exception
     */
    protected function updateBracketItems(ProductPriceBracket $bracket, array $itemsData): void
    {
        $existingItemIds = [];

        foreach ($itemsData as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $item = BracketItem::where('bracket_id', $bracket->id)
                                  ->where('id', $itemData['id'])
                                  ->firstOrFail();
                
                $item->update([
                    'min_quantity' => $itemData['min_quantity'] ?? $item->min_quantity,
                    'max_quantity' => $itemData['max_quantity'] ?? $item->max_quantity,
                    'price' => $itemData['price'] ?? $item->price,
                    'price_type' => $itemData['price_type'] ?? $item->price_type,
                    'is_active' => $itemData['is_active'] ?? $item->is_active,
                ]);

                $existingItemIds[] = $item->id;
            } else {
                // Create new item
                $this->validateBracketItemData($itemData);
                
                $item = BracketItem::create([
                    'bracket_id' => $bracket->id,
                    'min_quantity' => $itemData['min_quantity'],
                    'max_quantity' => $itemData['max_quantity'] ?? null,
                    'price' => $itemData['price'],
                    'price_type' => $itemData['price_type'],
                    'is_active' => $itemData['is_active'] ?? true
                ]);

                $existingItemIds[] = $item->id;
            }
        }

        // Delete items that weren't included in the update
        if (!empty($existingItemIds)) {
            BracketItem::where('bracket_id', $bracket->id)
                       ->whereNotIn('id', $existingItemIds)
                       ->delete();
        }
    }

    /**
     * Activate a bracket (deactivate others for the same product)
     *
     * @param ProductPriceBracket $bracket
     * @return void
     */
    public function activateBracket(ProductPriceBracket $bracket): void
    {
        // Deactivate other brackets for the same product
        ProductPriceBracket::where('product_id', $bracket->product_id)
                          ->where('id', '!=', $bracket->id)
                          ->update(['is_selected' => false]);

        // Ensure this bracket is selected
        $bracket->update(['is_selected' => true]);

        // Enable bracket pricing for this product
        $bracket->product->update(['use_bracket_pricing' => true]);
    }

    /**
     * Deactivate bracket pricing for a product
     *
     * @param int $productId
     * @return bool
     */
    public function deactivateBracketPricing(int $productId): bool
    {
        $product = Product::findOrFail($productId);

        try {
            DB::beginTransaction();

            // Deselect all brackets for this product
            ProductPriceBracket::where('product_id', $productId)
                              ->update(['is_selected' => false]);

            // Disable bracket pricing
            $product->update(['use_bracket_pricing' => false]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate price for quantity using the selected bracket
     *
     * @param int $productId
     * @param int $quantity
     * @param string $priceType
     * @return float|null
     */
    public function calculatePriceForQuantity(int $productId, int $quantity, string $priceType = 'regular'): ?float
    {
        $product = Product::findOrFail($productId);
        
        if (!$product->use_bracket_pricing) {
            return $product->getTraditionalPrice($priceType);
        }

        return ProductPriceBracket::getPriceForQuantity($productId, $quantity, $priceType);
    }

    /**
     * Get pricing breakdown for different quantities
     *
     * @param int $productId
     * @param string $priceType
     * @param array $quantities
     * @return array
     */
    public function getPricingBreakdown(int $productId, string $priceType = 'regular', array $quantities = [1, 5, 10, 25, 50, 100]): array
    {
        $product = Product::findOrFail($productId);
        
        if (!$product->use_bracket_pricing) {
            $traditionalPrice = $product->getTraditionalPrice($priceType);
            $breakdown = [];
            
            foreach ($quantities as $quantity) {
                $breakdown[] = [
                    'quantity' => $quantity,
                    'price' => $traditionalPrice,
                    'total' => $traditionalPrice * $quantity,
                    'type' => 'traditional'
                ];
            }
            
            return [
                'product_id' => $productId,
                'price_type' => $priceType,
                'use_bracket_pricing' => false,
                'breakdown' => $breakdown
            ];
        }

        $activeBracket = ProductPriceBracket::where('product_id', $productId)
                                           ->active()
                                           ->first();

        if (!$activeBracket) {
            return [
                'product_id' => $productId,
                'price_type' => $priceType,
                'use_bracket_pricing' => true,
                'breakdown' => [],
                'error' => 'No active bracket found'
            ];
        }

        $breakdown = [];
        foreach ($quantities as $quantity) {
            $price = $this->calculatePriceForQuantity($productId, $quantity, $priceType);
            $breakdown[] = [
                'quantity' => $quantity,
                'price' => $price,
                'total' => $price ? $price * $quantity : null,
                'unit_savings' => $quantity > 1 ? 
                    ($this->calculatePriceForQuantity($productId, 1, $priceType) - $price) : 0,
                'total_savings' => $quantity > 1 ? 
                    (($this->calculatePriceForQuantity($productId, 1, $priceType) * $quantity) - ($price * $quantity)) : 0,
                'type' => 'bracket'
            ];
        }

        return [
            'product_id' => $productId,
            'price_type' => $priceType,
            'use_bracket_pricing' => true,
            'active_bracket_id' => $activeBracket->id,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Delete a bracket and its items
     *
     * @param int $bracketId
     * @return bool
     * @throws Exception
     */
    public function deleteBracket(int $bracketId): bool
    {
        $bracket = ProductPriceBracket::findOrFail($bracketId);

        try {
            DB::beginTransaction();

            // Delete all bracket items
            $bracket->bracketItems()->delete();
            
            // Delete the bracket
            $wasSelected = $bracket->is_selected;
            $productId = $bracket->product_id;
            $bracket->delete();

            // If this was the selected bracket, check if we should disable bracket pricing
            if ($wasSelected) {
                $remainingBrackets = ProductPriceBracket::where('product_id', $productId)->count();
                if ($remainingBrackets === 0) {
                    Product::where('id', $productId)->update(['use_bracket_pricing' => false]);
                }
            }

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get all brackets for a product
     *
     * @param int $productId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductBrackets(int $productId)
    {
        return ProductPriceBracket::where('product_id', $productId)
                                 ->with(['bracketItems' => function($query) {
                                     $query->orderBy('price_type')->orderBy('min_quantity');
                                 }, 'createdBy'])
                                 ->orderBy('created_at', 'desc')
                                 ->get();
    }

    /**
     * Get the active bracket for a product
     *
     * @param int $productId
     * @return ProductPriceBracket|null
     */
    public function getActiveBracket(int $productId): ?ProductPriceBracket
    {
        return ProductPriceBracket::where('product_id', $productId)
                                 ->active()
                                 ->with('bracketItems')
                                 ->first();
    }

    /**
     * Validate bracket item data
     *
     * @param array $itemData
     * @throws Exception
     */
    protected function validateBracketItemData(array $itemData): void
    {
        if (!isset($itemData['min_quantity']) || $itemData['min_quantity'] < 1) {
            throw new Exception('Minimum quantity must be at least 1');
        }

        if (isset($itemData['max_quantity']) && 
            $itemData['max_quantity'] <= $itemData['min_quantity']) {
            throw new Exception('Maximum quantity must be greater than minimum quantity');
        }

        if (!isset($itemData['price']) || $itemData['price'] < 0) {
            throw new Exception('Price must be a positive number');
        }

        if (!isset($itemData['price_type']) || 
            !in_array($itemData['price_type'], ['regular', 'wholesale', 'walk_in'])) {
            throw new Exception('Invalid price type');
        }
    }

    /**
     * Get optimal pricing suggestions based on cost and margin
     *
     * @param int $productId
     * @param float $targetMargin
     * @param array $quantities
     * @return array
     */
    public function getOptimalPricingSuggestions(int $productId, float $targetMargin = 0.3, array $quantities = [1, 10, 25, 50]): array
    {
        $product = Product::findOrFail($productId);
        
        // Get the latest cost price from received items
        $latestReceivedItem = \App\Models\PurchaseOrderReceivedItem::where('product_id', $productId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestReceivedItem) {
            throw new Exception('No cost information available for this product');
        }

        $costPrice = $latestReceivedItem->cost_price;
        $suggestions = [];

        foreach ($quantities as $index => $quantity) {
            // Calculate decreasing margin for higher quantities
            $adjustedMargin = $targetMargin * (1 - ($index * 0.02)); // 2% reduction per tier
            $adjustedMargin = max($adjustedMargin, 0.1); // Minimum 10% margin
            
            $suggestedPrice = $costPrice / (1 - $adjustedMargin);
            
            $suggestions[] = [
                'min_quantity' => $quantity,
                'max_quantity' => isset($quantities[$index + 1]) ? $quantities[$index + 1] - 1 : null,
                'suggested_price' => round($suggestedPrice, 2),
                'margin_percentage' => round($adjustedMargin * 100, 1),
                'profit_per_unit' => round($suggestedPrice - $costPrice, 2)
            ];
        }

        return [
            'product_id' => $productId,
            'cost_price' => $costPrice,
            'target_margin' => $targetMargin,
            'suggestions' => $suggestions
        ];
    }

    /**
     * Clone bracket to create a new version
     *
     * @param int $bracketId
     * @param array $newBracketData
     * @return ProductPriceBracket
     * @throws Exception
     */
    public function cloneBracket(int $bracketId, array $newBracketData = []): ProductPriceBracket
    {
        $originalBracket = ProductPriceBracket::with('bracketItems')->findOrFail($bracketId);

        try {
            DB::beginTransaction();

            // Create new bracket
            $newBracket = ProductPriceBracket::create([
                'product_id' => $originalBracket->product_id,
                'is_selected' => $newBracketData['is_selected'] ?? false,
                'effective_from' => $newBracketData['effective_from'] ?? now(),
                'effective_to' => $newBracketData['effective_to'] ?? null,
                'created_by' => Auth::id()
            ]);

            // Clone bracket items
            foreach ($originalBracket->bracketItems as $item) {
                BracketItem::create([
                    'bracket_id' => $newBracket->id,
                    'min_quantity' => $item->min_quantity,
                    'max_quantity' => $item->max_quantity,
                    'price' => $item->price,
                    'price_type' => $item->price_type,
                    'is_active' => $item->is_active
                ]);
            }

            // If this bracket is selected, activate it
            if ($newBracket->is_selected) {
                $this->activateBracket($newBracket);
            }

            DB::commit();
            return $newBracket->load('bracketItems');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Import brackets from CSV data
     *
     * @param int $productId
     * @param array $csvData
     * @return array
     * @throws Exception
     */
    public function importBracketsFromCsv(int $productId, array $csvData): array
    {
        $product = Product::findOrFail($productId);
        $importedItems = [];
        $errors = [];

        try {
            DB::beginTransaction();

            // Create a new bracket for the import
            $bracket = ProductPriceBracket::create([
                'product_id' => $productId,
                'is_selected' => false,
                'effective_from' => now(),
                'effective_to' => null,
                'created_by' => Auth::id()
            ]);

            foreach ($csvData as $index => $row) {
                try {
                    $this->validateCsvRow($row, $index);
                    
                    $item = BracketItem::create([
                        'bracket_id' => $bracket->id,
                        'min_quantity' => (int) $row['min_quantity'],
                        'max_quantity' => !empty($row['max_quantity']) ? (int) $row['max_quantity'] : null,
                        'price' => (float) $row['price'],
                        'price_type' => $row['price_type'],
                        'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : true
                    ]);

                    $importedItems[] = $item;
                } catch (Exception $e) {
                    $errors[] = "Row {$index}: " . $e->getMessage();
                }
            }

            if (!empty($errors)) {
                DB::rollBack();
                throw new Exception('Import failed with errors: ' . implode(', ', $errors));
            }

            DB::commit();
            return [
                'bracket_id' => $bracket->id,
                'imported_count' => count($importedItems),
                'bracket_items' => $importedItems,
                'errors' => $errors
            ];
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate CSV row data
     *
     * @param array $row
     * @param int $index
     * @throws Exception
     */
    protected function validateCsvRow(array $row, int $index): void
    {
        $required = ['min_quantity', 'price', 'price_type'];
        
        foreach ($required as $field) {
            if (empty($row[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if (!is_numeric($row['min_quantity']) || (int) $row['min_quantity'] < 1) {
            throw new Exception('Min quantity must be a positive integer');
        }

        if (!empty($row['max_quantity']) && 
            (!is_numeric($row['max_quantity']) || (int) $row['max_quantity'] <= (int) $row['min_quantity'])) {
            throw new Exception('Max quantity must be greater than min quantity');
        }

        if (!is_numeric($row['price']) || (float) $row['price'] < 0) {
            throw new Exception('Price must be a positive number');
        }

        if (!in_array($row['price_type'], ['regular', 'wholesale', 'walk_in'])) {
            throw new Exception('Invalid price type');
        }
    }
}