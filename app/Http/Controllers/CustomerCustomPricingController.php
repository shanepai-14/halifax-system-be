<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CustomerCustomPricingService;
use App\Models\Customer;
use App\Models\CustomerCustomPrice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class CustomerCustomPricingController extends Controller
{
    protected $customPricingService;

    public function __construct(CustomerCustomPricingService $customPricingService)
    {
        $this->customPricingService = $customPricingService;
    }

    /**
     * Toggle valued customer status
     */
    public function toggleValuedCustomer(Request $request, int $customerId): JsonResponse
    {
        $request->validate([
            'is_valued_customer' => 'required|boolean',
            'notes' => 'nullable|string'
        ]);

        try {
            $customer = Customer::findOrFail($customerId);

            if ($request->is_valued_customer) {
                $customer->markAsValuedCustomer($request->notes);
            } else {
                $customer->removeValuedCustomerStatus();
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Customer status updated successfully',
                'data' => $customer->fresh()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

 public function getCustomPrices(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);
            
            if (!$customer->is_valued_customer) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Customer is not a valued customer',
                    'data' => []
                ]);
            }

            // Get all custom prices with products, grouped by product
            $customPrices = CustomerCustomPrice::with(['product:id,product_code,product_name'])
                ->where('customer_id', $customerId)
                ->orderBy('product_id')
                ->orderBy('min_quantity')
                ->get()
                ->groupBy('product_id')
                ->map(function ($pricesByProduct, $productId) {
                    $firstPrice = $pricesByProduct->first();
                    return [
                        'product_id' => $productId,
                        'product' => $firstPrice->product ? [
                            'id' => $firstPrice->product->id,
                            'product_code' => $firstPrice->product->product_code,
                            'product_name' => $firstPrice->product->product_name,
                        ] : null,
                        'price_ranges' => $pricesByProduct->map(function ($price) {
                            return [
                                'id' => $price->id,
                                'min_quantity' => $price->min_quantity,
                                'max_quantity' => $price->max_quantity,
                                'quantity_range' => $price->max_quantity 
                                    ? "{$price->min_quantity} - {$price->max_quantity}"
                                    : "{$price->min_quantity}+",
                                'price' => (float) $price->price,
                                'label' => $price->label,
                                'notes' => $price->notes,
                                'is_active' => (bool) $price->is_active,
                                'effective_from' => $price->effective_from,
                                'effective_to' => $price->effective_to,
                                'created_at' => $price->created_at,
                                'updated_at' => $price->updated_at,
                                'created_by' => $price->created_by
                            ];
                        })->values()->toArray(),
                        'total_ranges' => $pricesByProduct->count(),
                        'active_ranges' => $pricesByProduct->where('is_active', true)->count(),
                        'has_inactive_ranges' => $pricesByProduct->where('is_active', false)->count() > 0
                    ];
                })
                ->values(); // Reset array keys

            $totalPrices = CustomerCustomPrice::where('customer_id', $customerId)->count();
            $activePrices = CustomerCustomPrice::where('customer_id', $customerId)
                ->where('is_active', true)->count();

            return response()->json([
                'status' => 'success',
                'data' => $customPrices,
                'meta' => [
                    'total_products' => $customPrices->count(),
                    'total_price_ranges' => $totalPrices,
                    'active_price_ranges' => $activePrices,
                    'customer_id' => $customerId,
                    'customer_name' => $customer->customer_name,
                    'is_valued_customer' => $customer->is_valued_customer
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }


    /**
     * Get custom pricing for a customer and product
     */
    public function getCustomPricing(int $customerId, int $productId): JsonResponse
    {
        try {
            $pricing = $this->customPricingService->getCustomPricingForProduct($customerId, $productId);
            
            return response()->json([
                'status' => 'success',
                'data' => $pricing
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Set custom prices for a valued customer
     */
    public function setCustomPrices(Request $request, int $customerId): JsonResponse
    {
        $request->validate([
            'prices' => 'required|array',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.min_quantity' => 'required|integer|min:1',
            'prices.*.max_quantity' => 'nullable|integer|gt:prices.*.min_quantity',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.label' => 'nullable|string|max:255',
            'prices.*.notes' => 'nullable|string',
            'prices.*.effective_from' => 'nullable|date',
            'prices.*.effective_to' => 'nullable|date|after:prices.*.effective_from'
        ]);

        try {
            $customPrices = $this->customPricingService->setCustomPricesForCustomer(
                $customerId, 
                $request->prices
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Custom prices set successfully',
                'data' => $customPrices
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }


    /**
     * Generate pricing template for customer
     */


    /**
     * Update custom price
     */
    public function updateCustomPrice(Request $request, int $priceId): JsonResponse
    {
        $request->validate([
            'min_quantity' => 'sometimes|integer|min:1',
            'max_quantity' => 'nullable|integer|gt:min_quantity',
            'price' => 'sometimes|numeric|min:0',
            'label' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from'
        ]);

        try {
            $customPrice = CustomerCustomPrice::findOrFail($priceId);
            $customPrice->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Custom price updated successfully',
                'data' => $customPrice->fresh()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete custom price
     */
    public function deleteCustomPrice(int $priceId): JsonResponse
    {
        try {
            $customPrice = CustomerCustomPrice::findOrFail($priceId);
            $customPrice->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Custom price deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
}