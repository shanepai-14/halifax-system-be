<?php

namespace App\Http\Controllers;

use App\Services\BracketPricingService;
use App\Models\ProductPriceBracket;
use App\Models\Product;
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
     * Get all brackets for a product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function getProductBrackets(int $productId): JsonResponse
    {
        try {
            $brackets = $this->bracketPricingService->getProductBrackets($productId);

            return response()->json([
                'status' => 'success',
                'data' => $brackets,
                'message' => 'Product brackets retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving product brackets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the active bracket for a product
     *
     * @param int $productId
     * @return JsonResponse
     */
    public function getActiveBracket(int $productId): JsonResponse
    {
        try {
            $bracket = $this->bracketPricingService->getActiveBracket($productId);

            return response()->json([
                'status' => 'success',
                'data' => $bracket,
                'message' => 'Active bracket retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving active bracket',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new bracket with items
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
                'bracket_items.*.is_active' => 'boolean'
            ]);

            $bracket = $this->bracketPricingService->createBracketWithItems($productId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $bracket,
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
                'bracket_items.*.is_active' => 'boolean'
            ]);

            $bracket = $this->bracketPricingService->updateBracketWithItems($bracketId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $bracket,
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
     * Delete a bracket
     *
     * @param int $bracketId
     * @return JsonResponse
     */
    public function destroy(int $bracketId): JsonResponse
    {
        try {
            $this->bracketPricingService->deleteBracket($bracketId);

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
            $this->bracketPricingService->activateBracket($bracket);

            return response()->json([
                'status' => 'success',
                'data' => $bracket->load('bracketItems'),
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
    public function deactivate(int $productId): JsonResponse
    {
        try {
            $this->bracketPricingService->deactivateBracketPricing($productId);

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
     * Get pricing breakdown for different quantities
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getPricingBreakdown(Request $request, int $productId): JsonResponse
    {
        try {
            $priceType = $request->input('price_type', 'regular');
            $quantities = $request->input('quantities', [1, 5, 10, 25, 50, 100]);

            $breakdown = $this->bracketPricingService->getPricingBreakdown($productId, $priceType, $quantities);

            return response()->json([
                'status' => 'success',
                'data' => $breakdown,
                'message' => 'Pricing breakdown retrieved successfully'
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
     * Calculate price for a specific quantity
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function calculatePrice(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
                'price_type' => 'required|in:regular,wholesale,walk_in'
            ]);

            $price = $this->bracketPricingService->calculatePriceForQuantity(
                $productId,
                $validated['quantity'],
                $validated['price_type']
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'product_id' => $productId,
                    'quantity' => $validated['quantity'],
                    'price_type' => $validated['price_type'],
                    'price' => $price,
                    'total' => $price ? $price * $validated['quantity'] : null
                ],
                'message' => 'Price calculated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error calculating price',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get optimal pricing suggestions
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function getOptimalPricingSuggestions(Request $request, int $productId): JsonResponse
    {
        try {
            $targetMargin = $request->input('target_margin', 0.3);
            $quantities = $request->input('quantities', [1, 10, 25, 50]);

            $suggestions = $this->bracketPricingService->getOptimalPricingSuggestions($productId, $targetMargin, $quantities);

            return response()->json([
                'status' => 'success',
                'data' => $suggestions,
                'message' => 'Pricing suggestions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving pricing suggestions',
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
                'data' => $newBracket,
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
     * Import brackets from CSV
     *
     * @param Request $request
     * @param int $productId
     * @return JsonResponse
     */
    public function importFromCsv(Request $request, int $productId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'csv_data' => 'required|array',
                'csv_data.*.min_quantity' => 'required|integer|min:1',
                'csv_data.*.max_quantity' => 'nullable|integer',
                'csv_data.*.price' => 'required|numeric|min:0',
                'csv_data.*.price_type' => 'required|in:regular,wholesale,walk_in',
                'csv_data.*.is_active' => 'boolean'
            ]);

            $result = $this->bracketPricingService->importBracketsFromCsv($productId, $validated['csv_data']);

            return response()->json([
                'status' => 'success',
                'data' => $result,
                'message' => 'Brackets imported successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error importing brackets',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bracket with its items
     *
     * @param int $bracketId
     * @return JsonResponse
     */
    public function show(int $bracketId): JsonResponse
    {
        try {
            $bracket = ProductPriceBracket::with(['bracketItems', 'product', 'createdBy'])
                ->findOrFail($bracketId);

            return response()->json([
                'status' => 'success',
                'data' => $bracket,
                'message' => 'Bracket retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bracket not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
}