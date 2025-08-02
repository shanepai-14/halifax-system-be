<?php

namespace App\Http\Controllers;

use App\Services\BracketPricingService;
use App\Models\Product;
use App\Models\ProductPriceBracket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class BracketPricingController extends Controller
{
    protected $bracketPricingService;

    public function __construct(BracketPricingService $bracketPricingService)
    {
        $this->bracketPricingService = $bracketPricingService;
    }

    /**
     * Create a new bracket with multiple prices per tier
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_selected' => 'boolean',
                'effective_from' => 'required|date',
                'effective_to' => 'nullable|date|after:effective_from',
                'bracket_items' => 'required|array|min:1',
                'bracket_items.*.min_quantity' => 'required|integer|min:1',
                'bracket_items.*.max_quantity' => 'nullable|integer|gt:bracket_items.*.min_quantity',
                'bracket_items.*.price' => 'required|numeric|min:0',
                'bracket_items.*.price_type' => 'required|in:regular,wholesale,walk_in',
                'bracket_items.*.label' => 'nullable|string|max:255',
                'bracket_items.*.is_active' => 'boolean',
                'bracket_items.*.sort_order' => 'nullable|integer|min:0'
            ]);

            $bracket = $this->bracketPricingService->createBracketWithItems($productId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $bracket->load('bracketItems'),
                'message' => 'Bracket created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a bracket and its items
     *
     * @param Request $request
     * @param int $bracketId
     * @return JsonResponse
     */
    public function update(Request $request, int $bracketId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_selected' => 'boolean',
                'effective_from' => 'nullable|date',
                'effective_to' => 'nullable|date|after:effective_from',
                'bracket_items' => 'array',
                'bracket_items.*.id' => 'nullable|integer|exists:bracket_items,id',
                'bracket_items.*.min_quantity' => 'required|integer|min:1',
                'bracket_items.*.max_quantity' => 'nullable|integer|gt:bracket_items.*.min_quantity',
                'bracket_items.*.price' => 'required|numeric|min:0',
                'bracket_items.*.price_type' => 'required|in:regular,wholesale,walk_in',
                'bracket_items.*.label' => 'nullable|string|max:255',
                'bracket_items.*.is_active' => 'boolean',
                'bracket_items.*.sort_order' => 'nullable|integer|min:0'
            ]);

            $bracket = $this->bracketPricingService->updateBracketWithItems($bracketId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $bracket->load('bracketItems'),
                'message' => 'Bracket updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all brackets for a product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function index(int $productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);
            
            $brackets = ProductPriceBracket::where('product_id', $productId)
                ->with(['bracketItems' => function($query) {
                    $query->ordered();
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            // Group bracket items by tiers for easier frontend handling
            $bracketsData = $brackets->map(function($bracket) {
                $tiersMap = [];
                
                // Group items by quantity range
                foreach ($bracket->bracketItems as $item) {
                    $tierKey = $item->min_quantity . '-' . ($item->max_quantity ?: 'inf');
                    
                    if (!isset($tiersMap[$tierKey])) {
                        $tiersMap[$tierKey] = [
                            'min_quantity' => $item->min_quantity,
                            'max_quantity' => $item->max_quantity,
                            'price_entries' => []
                        ];
                    }
                    
                    $tiersMap[$tierKey]['price_entries'][] = [
                        'id' => $item->id,
                        'price' => $item->price,
                        'price_type' => $item->price_type,
                        'label' => $item->label,
                        'is_active' => $item->is_active,
                        'sort_order' => $item->sort_order
                    ];
                }

                return [
                    'id' => $bracket->id,
                    'is_selected' => $bracket->is_selected,
                    'effective_from' => $bracket->effective_from,
                    'effective_to' => $bracket->effective_to,
                    'created_at' => $bracket->created_at,
                    'bracket_items' => $bracket->bracketItems->map(function($item) {
                        return [
                            'id' => $item->id,
                            'min_quantity' => $item->min_quantity,
                            'max_quantity' => $item->max_quantity,
                            'price' => $item->price,
                            'price_type' => $item->price_type,
                            'label' => $item->label,
                            'is_active' => $item->is_active,
                            'sort_order' => $item->sort_order,
                            'quantity_range' => $item->quantity_range
                        ];
                    }),
                    'tiers' => array_values($tiersMap),
                    'total_price_options' => $bracket->bracketItems->count(),
                    'active_price_options' => $bracket->bracketItems->where('is_active', true)->count()
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'product_name' => $product->product_name,
                    'use_bracket_pricing' => $product->use_bracket_pricing,
                    'brackets' => $bracketsData,
                    'active_bracket' => $bracketsData->where('is_selected', true)->first()
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving brackets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get multiple price options for a specific quantity
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getPriceOptionsForQuantity(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
                'price_type' => 'nullable|in:regular,wholesale,walk_in'
            ]);

            $quantity = $validated['quantity'];
            $priceType = $validated['price_type'] ?? null;

            $activeBracket = ProductPriceBracket::where('product_id', $productId)
                ->where('is_selected', true)
                ->with('bracketItems')
                ->first();

            if (!$activeBracket) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price_options' => [],
                        'has_bracket_pricing' => false
                    ]
                ]);
            }

            $query = $activeBracket->bracketItems()
                ->where('is_active', true)
                ->forQuantity($quantity);

            if ($priceType) {
                $query->where('price_type', $priceType);
            }

            $priceOptions = $query->ordered()->get();

            $options = $priceOptions->map(function($item) use ($quantity) {
                return [
                    'id' => $item->id,
                    'price' => $item->price,
                    'total_price' => $item->price * $quantity,
                    'price_type' => $item->price_type,
                    'label' => $item->label,
                    'quantity_range' => $item->quantity_range,
                    'sort_order' => $item->sort_order
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_type' => $priceType,
                    'price_options' => $options,
                    'options_count' => $options->count(),
                    'has_bracket_pricing' => true,
                    'best_price' => $options->min('price'),
                    'price_range' => [
                        'min' => $options->min('price'),
                        'max' => $options->max('price')
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving price options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate best price for quantity from multiple options
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function calculateBestPrice(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
                'price_type' => 'nullable|in:regular,wholesale,walk_in'
            ]);

            $quantity = $validated['quantity'];
            $priceType = $validated['price_type'] ?? 'regular';

            $activeBracket = ProductPriceBracket::where('product_id', $productId)
                ->where('is_selected', true)
                ->first();

            if (!$activeBracket) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price_type' => $priceType,
                        'best_price' => null,
                        'has_pricing' => false
                    ]
                ]);
            }

            $bestPriceItem = $activeBracket->bracketItems()
                ->where('is_active', true)
                ->where('price_type', $priceType)
                ->forQuantity($quantity)
                ->orderBy('price', 'asc')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_type' => $priceType,
                    'best_price' => $bestPriceItem ? $bestPriceItem->price : null,
                    'total_amount' => $bestPriceItem ? $bestPriceItem->price * $quantity : null,
                    'price_option' => $bestPriceItem ? [
                        'id' => $bestPriceItem->id,
                        'label' => $bestPriceItem->label,
                        'quantity_range' => $bestPriceItem->quantity_range
                    ] : null,
                    'has_pricing' => $bestPriceItem !== null
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error calculating best price',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bracket
     *
     * @param int $bracketId
     * @return JsonResponse
     */
    public function destroy(int $bracketId): JsonResponse
    {
        try {
            $bracket = ProductPriceBracket::findOrFail($bracketId);
            
            // If this was the selected bracket, disable bracket pricing on the product
            if ($bracket->is_selected) {
                $bracket->product()->update(['use_bracket_pricing' => false]);
            }
            
            $bracket->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Bracket deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a bracket
     *
     * @param int $bracketId
     * @return JsonResponse
     */
    public function activate(int $bracketId): JsonResponse
    {
        try {
            $bracket = ProductPriceBracket::findOrFail($bracketId);
            
            // Deactivate other brackets for the same product
            ProductPriceBracket::where('product_id', $bracket->product_id)
                ->where('id', '!=', $bracket->id)
                ->update(['is_selected' => false]);

            // Activate this bracket
            $bracket->update(['is_selected' => true]);
            
            // Enable bracket pricing on the product
            $bracket->product()->update(['use_bracket_pricing' => true]);

            return response()->json([
                'status' => 'success',
                'message' => 'Bracket activated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error activating bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate bracket pricing for a product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function deactivateBracketPricing(int $productId): JsonResponse
    {
        try {
            $product = Product::findOrFail($productId);
            
            // Deactivate all brackets for this product
            ProductPriceBracket::where('product_id', $productId)
                ->update(['is_selected' => false]);
            
            // Disable bracket pricing on the product
            $product->update(['use_bracket_pricing' => false]);

            return response()->json([
                'status' => 'success',
                'message' => 'Bracket pricing deactivated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deactivating bracket pricing',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}