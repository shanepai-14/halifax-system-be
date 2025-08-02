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
     * Create a price bracket with multiple prices per tier
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

            // Create bracket items (multiple prices per tier are supported)
            if (!empty($bracketData['bracket_items'])) {
                foreach ($bracketData['bracket_items'] as $index => $itemData) {
                    $this->validateBracketItemData($itemData);
                    
                    BracketItem::create([
                        'bracket_id' => $bracket->id,
                        'min_quantity' => $itemData['min_quantity'],
                        'max_quantity' => $itemData['max_quantity'] ?? null,
                        'price' => $itemData['price'],
                        'price_type' => $itemData['price_type'],
                        'label' => $itemData['label'] ?? $this->generateDefaultLabel($itemData['price_type']),
                        'is_active' => $itemData['is_active'] ?? true,
                        'sort_order' => $itemData['sort_order'] ?? $index
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

            // Handle bracket items
            if (isset($bracketData['bracket_items'])) {
                $existingItemIds = collect($bracketData['bracket_items'])
                    ->pluck('id')
                    ->filter()
                    ->toArray();

                // Delete items not in the update list
                $bracket->bracketItems()
                        ->whereNotIn('id', $existingItemIds)
                        ->delete();

                // Update or create items
                foreach ($bracketData['bracket_items'] as $index => $itemData) {
                    $this->validateBracketItemData($itemData);

                    $itemAttributes = [
                        'bracket_id' => $bracket->id,
                        'min_quantity' => $itemData['min_quantity'],
                        'max_quantity' => $itemData['max_quantity'] ?? null,
                        'price' => $itemData['price'],
                        'price_type' => $itemData['price_type'],
                        'label' => $itemData['label'] ?? $this->generateDefaultLabel($itemData['price_type']),
                        'is_active' => $itemData['is_active'] ?? true,
                        'sort_order' => $itemData['sort_order'] ?? $index
                    ];

                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing item
                        BracketItem::where('id', $itemData['id'])->update($itemAttributes);
                    } else {
                        // Create new item
                        BracketItem::create($itemAttributes);
                    }
                }
            }

            // Handle bracket activation
            if ($bracket->is_selected) {
                $this->activateBracket($bracket);
            }

            DB::commit();
            return $bracket->fresh()->load('bracketItems');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculate price for specific quantity and price type (returns best price from multiple options)
     *
     * @param int $productId
     * @param int $quantity
     * @param string $priceType
     * @return float|null
     */
    public function calculatePriceForQuantity(int $productId, int $quantity, string $priceType = 'regular'): ?float
    {
        $activeBracket = ProductPriceBracket::where('product_id', $productId)
            ->where('is_selected', true)
            ->first();

        if (!$activeBracket) {
            return null;
        }

        // Get the best (lowest) price from all applicable options
        $bestPriceItem = $activeBracket->bracketItems()
            ->where('is_active', true)
            ->where('price_type', $priceType)
            ->where('min_quantity', '<=', $quantity)
            ->where(function($q) use ($quantity) {
                $q->whereNull('max_quantity')
                  ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderBy('price', 'asc')
            ->first();

        return $bestPriceItem ? $bestPriceItem->price : null;
    }

    /**
     * Get all available price options for a specific quantity and price type
     *
     * @param int $productId
     * @param int $quantity
     * @param string $priceType
     * @return array
     */
    public function getAllPriceOptionsForQuantity(int $productId, int $quantity, string $priceType = 'regular'): array
    {
        $activeBracket = ProductPriceBracket::where('product_id', $productId)
            ->where('is_selected', true)
            ->first();

        if (!$activeBracket) {
            return [];
        }

        $priceOptions = $activeBracket->bracketItems()
            ->where('is_active', true)
            ->where('price_type', $priceType)
            ->where('min_quantity', '<=', $quantity)
            ->where(function($q) use ($quantity) {
                $q->whereNull('max_quantity')
                  ->orWhere('max_quantity', '>=', $quantity);
            })
            ->orderBy('sort_order', 'asc')
            ->orderBy('price', 'asc')
            ->get();

        return $priceOptions->map(function($item) use ($quantity) {
            return [
                'id' => $item->id,
                'label' => $item->label,
                'price' => $item->price,
                'total_price' => $item->price * $quantity,
                'quantity_range' => $item->quantity_range,
                'sort_order' => $item->sort_order
            ];
        })->toArray();
    }

    /**
     * Get pricing breakdown for different quantities with multiple options
     *
     * @param int $productId
     * @param string $priceType
     * @param array $quantities
     * @return array
     */
    public function getPricingBreakdown(int $productId, string $priceType = 'regular', array $quantities = [1, 5, 10, 25, 50, 100]): array
    {
        $breakdown = [];
        
        foreach ($quantities as $quantity) {
            $options = $this->getAllPriceOptionsForQuantity($productId, $quantity, $priceType);
            $bestPrice = $this->calculatePriceForQuantity($productId, $quantity, $priceType);
            
            $breakdown[] = [
                'quantity' => $quantity,
                'price_options' => $options,
                'options_count' => count($options),
                'best_price' => $bestPrice,
                'best_total' => $bestPrice ? $bestPrice * $quantity : null,
                'has_multiple_options' => count($options) > 1
            ];
        }

        return [
            'product_id' => $productId,
            'price_type' => $priceType,
            'breakdown' => $breakdown
        ];
    }

    /**
     * Validate bracket item data
     *
     * @param array $itemData
     * @throws Exception
     */
    private function validateBracketItemData(array $itemData): void
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
     * Generate default label for price type
     *
     * @param string $priceType
     * @return string
     */
    private function generateDefaultLabel(string $priceType): string
    {
        return match($priceType) {
            'regular' => 'Regular Price',
            'wholesale' => 'Wholesale Price',
            'walk_in' => 'Walk-in Price',
            default => 'Price Option'
        };
    }

    /**
     * Activate bracket and update product settings
     *
     * @param ProductPriceBracket $bracket
     * @return void
     */
    private function activateBracket(ProductPriceBracket $bracket): void
    {
        // Deactivate other brackets for the same product
        ProductPriceBracket::where('product_id', $bracket->product_id)
                          ->where('id', '!=', $bracket->id)
                          ->update(['is_selected' => false]);

        // Enable bracket pricing on the product
        $bracket->product()->update(['use_bracket_pricing' => true]);
    }

    /**
     * Clone a bracket
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

            // Clone all bracket items
            foreach ($originalBracket->bracketItems as $originalItem) {
                BracketItem::create([
                    'bracket_id' => $newBracket->id,
                    'min_quantity' => $originalItem->min_quantity,
                    'max_quantity' => $originalItem->max_quantity,
                    'price' => $originalItem->price,
                    'price_type' => $originalItem->price_type,
                    'label' => $originalItem->label,
                    'is_active' => $originalItem->is_active,
                    'sort_order' => $originalItem->sort_order
                ]);
            }

            DB::commit();
            return $newBracket->load('bracketItems');
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get optimal pricing suggestions
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

        $costPrice = $latestReceivedItem->distribution_price;
        $suggestions = [];

        foreach ($quantities as $index => $quantity) {
            // Calculate decreasing margin for higher quantities
            $adjustedMargin = $targetMargin * (1 - ($index * 0.02)); // 2% reduction per tier
            $adjustedMargin = max($adjustedMargin, 0.1); // Minimum 10% margin
            
            $basePrice = $costPrice / (1 - $adjustedMargin);
            
            // Generate multiple price options for this tier
            $priceOptions = [
                [
                    'label' => 'Standard Price',
                    'price' => round($basePrice, 2),
                    'price_type' => 'regular'
                ],
                [
                    'label' => 'Wholesale Price',
                    'price' => round($basePrice * 0.9, 2),
                    'price_type' => 'wholesale'
                ],
                [
                    'label' => 'Walk-in Price',
                    'price' => round($basePrice * 1.05, 2),
                    'price_type' => 'walk_in'
                ]
            ];
            
            $suggestions[] = [
                'min_quantity' => $quantity,
                'max_quantity' => isset($quantities[$index + 1]) ? $quantities[$index + 1] - 1 : null,
                'price_options' => $priceOptions,
                'margin_percentage' => round($adjustedMargin * 100, 1),
                'profit_per_unit' => round($basePrice - $costPrice, 2)
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
     * Deactivate bracket pricing for a product
     *
     * @param int $productId
     * @return bool
     */
    public function deactivateBracketPricing(int $productId): bool
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);
            
            // Deactivate all brackets for this product
            ProductPriceBracket::where('product_id', $productId)
                ->update(['is_selected' => false]);
            
            // Disable bracket pricing on the product
            $product->update(['use_bracket_pricing' => false]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Activate a specific bracket
     *
     * @param int $bracketId
     * @return bool
     */
    public function activateSpecificBracket(int $bracketId): bool
    {
        try {
            DB::beginTransaction();

            $bracket = ProductPriceBracket::findOrFail($bracketId);
            
            // Deactivate other brackets for the same product
            ProductPriceBracket::where('product_id', $bracket->product_id)
                ->where('id', '!=', $bracket->id)
                ->update(['is_selected' => false]);

            // Activate this bracket
            $bracket->update(['is_selected' => true]);
            
            // Enable bracket pricing on the product
            $bracket->product()->update(['use_bracket_pricing' => true]);

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}