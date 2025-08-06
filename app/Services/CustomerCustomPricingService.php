<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCustomPrice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class CustomerCustomPricingService
{
    /**
     * Set custom prices for a valued customer
     */
    public function setCustomPricesForCustomer(int $customerId, array $pricesData): array
    {
        $customer = Customer::findOrFail($customerId);
        
        if (!$customer->is_valued_customer) {
            throw new Exception('Customer must be marked as valued customer first');
        }

        try {
            DB::beginTransaction();

            $createdPrices = [];

            foreach ($pricesData as $priceData) {
                $this->validatePriceData($priceData);

                // Deactivate existing overlapping prices
                $this->deactivateOverlappingPrices(
                    $customerId,
                    $priceData['product_id'],
                    $priceData['min_quantity'],
                    $priceData['max_quantity'] ?? null
                );

                // Create new custom price
                $customPrice = CustomerCustomPrice::create([
                    'customer_id' => $customerId,
                    'product_id' => $priceData['product_id'],
                    'min_quantity' => $priceData['min_quantity'],
                    'max_quantity' => $priceData['max_quantity'] ?? null,
                    'price' => $priceData['price'],
                    'label' => $this->generateDefaultLabel($priceData),
                    'is_active' => true,
                    'effective_from' => $priceData['effective_from'] ?? now(),
                    'effective_to' => $priceData['effective_to'] ?? null,
                    'notes' => $priceData['notes'] ?? null,
                    'created_by' => Auth::id()
                ]);

                $createdPrices[] = $customPrice;
            }

            DB::commit();
            return $createdPrices;

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get custom pricing for customer and product
     */
    public function getCustomPricingForProduct(int $customerId, int $productId): array
    {
        $customer = Customer::findOrFail($customerId);
        
        if (!$customer->is_valued_customer) {
            return [];
        }

        $customPrices = CustomerCustomPrice::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->active()
            ->orderBy('min_quantity')
            ->get();

        return $customPrices->map(function ($prices) {
            return $prices->map(function ($price) {
                return [
                    'id' => $price->id,
                    'min_quantity' => $price->min_quantity,
                    'max_quantity' => $price->max_quantity,
                    'quantity_range' => $price->quantity_range,
                    'price' => $price->price,
                    'label' => $price->label,
                    'effective_from' => $price->effective_from,
                    'effective_to' => $price->effective_to,
                    'notes' => $price->notes
                ];
            });
        })->toArray();
    }


    // Private helper methods
    private function validatePriceData(array $priceData): void
    {
        $required = ['product_id', 'min_quantity', 'price', ];
        
        foreach ($required as $field) {
            if (!isset($priceData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }

        if ($priceData['min_quantity'] < 1) {
            throw new Exception('Minimum quantity must be at least 1');
        }

        if (isset($priceData['max_quantity']) && 
            $priceData['max_quantity'] <= $priceData['min_quantity']) {
            throw new Exception('Maximum quantity must be greater than minimum quantity');
        }

        if ($priceData['price'] < 0) {
            throw new Exception('Price must be positive');
        }


    }

    private function deactivateOverlappingPrices(
        int $customerId, 
        int $productId, 
        int $minQuantity, 
        ?int $maxQuantity
    ): void {
        $query = CustomerCustomPrice::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->where('is_active', true);

        // Check for overlapping ranges
        $query->where(function ($q) use ($minQuantity, $maxQuantity) {
            if ($maxQuantity) {
                // New range has max, check for overlaps
                $q->where(function ($subQ) use ($minQuantity, $maxQuantity) {
                    $subQ->whereBetween('min_quantity', [$minQuantity, $maxQuantity])
                         ->orWhereBetween('max_quantity', [$minQuantity, $maxQuantity])
                         ->orWhere(function ($rangeQ) use ($minQuantity, $maxQuantity) {
                             $rangeQ->where('min_quantity', '<=', $minQuantity)
                                    ->where(function ($maxQ) use ($maxQuantity) {
                                        $maxQ->whereNull('max_quantity')
                                             ->orWhere('max_quantity', '>=', $maxQuantity);
                                    });
                         });
                });
            } else {
                // New range is open-ended, deactivate all overlapping
                $q->where('min_quantity', '>=', $minQuantity);
            }
        });

        $query->update(['is_active' => false]);
    }

  public function generateDefaultLabel(array $priceData): string
    {
        if (!empty($priceData['max_quantity'])) {
            $rangeLabel = "{$priceData['min_quantity']}-{$priceData['max_quantity']}";
        } else {
            $rangeLabel = "{$priceData['min_quantity']}+";
        }

        return "({$rangeLabel})";
    }



}
