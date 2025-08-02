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
     * Get all brackets for a product with enhanced details
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

            $bracketsData = $brackets->map(function($bracket) {
                // Group bracket items by tiers for easier frontend handling
                $tiersMap = [];
                
                foreach ($bracket->bracketItems as $item) {
                    $tierKey = $item->min_quantity . '-' . ($item->max_quantity ?: 'inf');
                    
                    if (!isset($tiersMap[$tierKey])) {
                        $tiersMap[$tierKey] = [
                            'min_quantity' => $item->min_quantity,
                            'max_quantity' => $item->max_quantity,
                            'quantity_range' => $item->quantity_range,
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

                // Calculate statistics
                $totalOptions = $bracket->bracketItems->count();
                $activeOptions = $bracket->bracketItems->where('is_active', true)->count();
                $priceTypes = $bracket->bracketItems->pluck('price_type')->unique()->values();
                $prices = $bracket->bracketItems->pluck('price')->map(function($price) {
                    return (float) $price;
                });

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
                    'statistics' => [
                        'total_options' => $totalOptions,
                        'active_options' => $activeOptions,
                        'total_tiers' => count($tiersMap),
                        'price_types' => $priceTypes,
                        'price_range' => [
                            'min' => $prices->min(),
                            'max' => $prices->max(),
                            'average' => $prices->average()
                        ]
                    ]
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

            $options = $this->bracketPricingService->getAllPriceOptionsForQuantity($productId, $quantity, $priceType);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_type' => $priceType,
                    'price_options' => $options,
                    'options_count' => count($options),
                    'has_bracket_pricing' => !empty($options),
                    'best_price' => !empty($options) ? min(array_column($options, 'price')) : null
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

            $bestPrice = $this->bracketPricingService->calculatePriceForQuantity($productId, $quantity, $priceType);
            $allOptions = $this->bracketPricingService->getAllPriceOptionsForQuantity($productId, $quantity, $priceType);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'price_type' => $priceType,
                    'best_price' => $bestPrice,
                    'total_amount' => $bestPrice ? $bestPrice * $quantity : null,
                    'all_options' => $allOptions,
                    'options_count' => count($allOptions),
                    'has_pricing' => $bestPrice !== null
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
     * Get enhanced pricing breakdown with multiple options per quantity
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getPricingBreakdown(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'price_type' => 'nullable|in:regular,wholesale,walk_in',
                'quantities' => 'nullable|array',
                'quantities.*' => 'integer|min:1'
            ]);

            $priceType = $validated['price_type'] ?? 'regular';
            $quantities = $validated['quantities'] ?? [1, 5, 10, 25, 50, 100];

            $breakdown = $this->bracketPricingService->getPricingBreakdown($productId, $priceType, $quantities);

            return response()->json([
                'status' => 'success',
                'data' => $breakdown
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving pricing breakdown',
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
            $success = $this->bracketPricingService->activateSpecificBracket($bracketId);

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
            $success = $this->bracketPricingService->deactivateBracketPricing($productId);

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

    /**
     * Clone a bracket
     *
     * @param Request $request
     * @param int $bracketId
     * @return JsonResponse
     */
    public function clone(Request $request, int $bracketId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'is_selected' => 'boolean',
                'effective_from' => 'nullable|date',
                'effective_to' => 'nullable|date|after:effective_from'
            ]);

            $newBracket = $this->bracketPricingService->cloneBracket($bracketId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $newBracket->load('bracketItems'),
                'message' => 'Bracket cloned successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cloning bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimal pricing suggestions with multiple options per tier
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getOptimalPricingSuggestions(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'target_margin' => 'nullable|numeric|min:0|max:1',
                'quantities' => 'nullable|array',
                'quantities.*' => 'integer|min:1'
            ]);

            $targetMargin = $validated['target_margin'] ?? 0.3;
            $quantities = $validated['quantities'] ?? [1, 10, 25, 50];

            $suggestions = $this->bracketPricingService->getOptimalPricingSuggestions($productId, $targetMargin, $quantities);

            return response()->json([
                'status' => 'success',
                'data' => $suggestions
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving pricing suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

  
