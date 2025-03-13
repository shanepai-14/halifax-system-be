<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProductPriceController extends Controller
{
    /**
     * Display a listing of all product prices (paginated)
     */
    public function index(Request $request)
    {
        $query = ProductPrice::with('product');
        
        // Apply filters if provided
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        $prices = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 15);
        
        return response()->json([
            'success' => true,
            'data' => $prices
        ]);
    }
    
    /**
     * Display price history for a specific product
     */
    public function priceHistory($productId)
    {
        $product = Product::findOrFail($productId);
        $prices = $product->prices()->orderBy('created_at', 'desc')->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => [
                'product' => $product,
                'prices' => $prices
            ]
        ]);
    }
    
    /**
     * Get the current active price for a product
     */
    public function getCurrentPrice($productId)
    {
        $product = Product::findOrFail($productId);
        $currentPrice = $product->currentPrice;
        
        return response()->json([
            'success' => true,
            'data' => $currentPrice
        ]);
    }
    
    /**
     * Display the specified product price
     */
    public function show($id)
    {
        $price = ProductPrice::with('product')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $price
        ]);
    }
    
    /**
     * Store a new price record
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'regular_price' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'walk_in_price' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean'
        ]);
        
        $isActive = $request->is_active ?? true;
        
        DB::transaction(function() use ($request, $isActive) {
            // If this price is active, deactivate other overlapping prices
            if ($isActive) {
                ProductPrice::where('product_id', $request->product_id)
                    ->where('is_active', true)
                    ->where(function($query) use ($request) {
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>', $request->effective_from);
                    })
                    ->update([
                        'is_active' => false,
                        'effective_to' => $request->effective_from
                    ]);
            }
            
            // Create the new price
            $price = ProductPrice::create([
                'product_id' => $request->product_id,
                'regular_price' => $request->regular_price,
                'wholesale_price' => $request->wholesale_price,
                'walk_in_price' => $request->walk_in_price,
                'is_active' => $isActive,
                'effective_from' => $request->effective_from,
                'effective_to' => $request->effective_to,
                'created_by' => Auth::id()
            ]);
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Product price created successfully',
            'data' => $price ?? null
        ], 201);
    }
    
    /**
     * Update the specified price record
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'regular_price' => 'numeric|min:0',
            'wholesale_price' => 'numeric|min:0',
            'walk_in_price' => 'numeric|min:0',
            'effective_from' => 'date',
            'effective_to' => 'nullable|date|after:effective_from',
            'is_active' => 'boolean'
        ]);
        
        $price = ProductPrice::findOrFail($id);
        
        DB::transaction(function() use ($request, $price) {
            // If is_active is being set to true, deactivate other overlapping prices
            if ($request->has('is_active') && $request->is_active && !$price->is_active) {
                ProductPrice::where('product_id', $price->product_id)
                    ->where('id', '!=', $price->id)
                    ->where('is_active', true)
                    ->where(function($query) use ($request, $price) {
                        $effectiveFrom = $request->effective_from ?? $price->effective_from;
                        $query->whereNull('effective_to')
                            ->orWhere('effective_to', '>', $effectiveFrom);
                    })
                    ->update([
                        'is_active' => false,
                        'effective_to' => $request->effective_from ?? $price->effective_from
                    ]);
            }
            
            // Update the price record
            $price->update($request->all());
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Product price updated successfully',
            'data' => $price
        ]);
    }
    
    /**
     * Set a specific price as active
     */
    public function setActive($id)
    {
        $price = ProductPrice::findOrFail($id);
        
        DB::transaction(function() use ($price) {
            // Deactivate any other active prices for this product
            ProductPrice::where('product_id', $price->product_id)
                ->where('id', '!=', $price->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'effective_to' => now()
                ]);
            
            // Set this price as active
            $price->update([
                'is_active' => true,
                'effective_from' => now(),
                'effective_to' => null
            ]);
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Price set as active successfully',
            'data' => $price
        ]);
    }
    
    /**
     * Soft delete the specified price record
     */
    public function destroy($id)
    {
        $price = ProductPrice::findOrFail($id);
        $price->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Product price deleted successfully'
        ]);
    }
    
    /**
     * Display a listing of trashed prices
     */
    public function trashed()
    {
        $trashedPrices = ProductPrice::onlyTrashed()->paginate(15);
        
        return response()->json([
            'success' => true,
            'data' => $trashedPrices
        ]);
    }
    
    /**
     * Restore a soft-deleted price record
     */
    public function restore($id)
    {
        $price = ProductPrice::onlyTrashed()->findOrFail($id);
        $price->restore();
        
        return response()->json([
            'success' => true,
            'message' => 'Product price restored successfully',
            'data' => $price
        ]);
    }
    
    /**
     * Get price statistics
     */
    public function getStats()
    {
        $stats = [
            'total_count' => ProductPrice::count(),
            'active_count' => ProductPrice::where('is_active', true)->count(),
            'inactive_count' => ProductPrice::where('is_active', false)->count(),
            'trashed_count' => ProductPrice::onlyTrashed()->count(),
            'products_with_prices' => ProductPrice::distinct('product_id')->count('product_id'),
            'products_without_prices' => Product::whereNotIn('id', function($query) {
                $query->select('product_id')->from('product_prices');
            })->count(),
            'average_regular_price' => ProductPrice::where('is_active', true)->avg('regular_price'),
            'average_wholesale_price' => ProductPrice::where('is_active', true)->avg('wholesale_price'),
            'average_walk_in_price' => ProductPrice::where('is_active', true)->avg('walk_in_price'),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
    
    /**
     * Update a product price based on received items
     */
    public function updateFromPurchaseOrder(Request $request, $productId)
    {
        $request->validate([
            'walk_in_price' => 'required|numeric|min:0',
            'wholesale_price' => 'required|numeric|min:0',
            'regular_price' => 'required|numeric|min:0',
        ]);
        
        $product = Product::findOrFail($productId);
        
        DB::transaction(function() use ($request, $product) {
            // Deactivate current prices
            ProductPrice::where('product_id', $product->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'effective_to' => now()
                ]);
            
            // Create the new price
            $price = ProductPrice::create([
                'product_id' => $product->id,
                'regular_price' => $request->regular_price,
                'wholesale_price' => $request->wholesale_price,
                'walk_in_price' => $request->walk_in_price,
                'is_active' => true,
                'effective_from' => now(),
                'created_by' => Auth::id()
            ]);
        });
        
        return response()->json([
            'success' => true, 
            'message' => 'Product prices updated successfully',
            'data' => $price ?? null
        ]);
    }
    
    /**
     * Bulk update prices for multiple products
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'prices' => 'required|array',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.regular_price' => 'required|numeric|min:0',
            'prices.*.wholesale_price' => 'required|numeric|min:0',
            'prices.*.walk_in_price' => 'required|numeric|min:0',
        ]);
        
        $updatedPrices = [];
        
        DB::transaction(function() use ($request, &$updatedPrices) {
            foreach ($request->prices as $priceData) {
                // Deactivate current prices
                ProductPrice::where('product_id', $priceData['product_id'])
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'effective_to' => now()
                    ]);
                
                // Create the new price
                $price = ProductPrice::create([
                    'product_id' => $priceData['product_id'],
                    'regular_price' => $priceData['regular_price'],
                    'wholesale_price' => $priceData['wholesale_price'],
                    'walk_in_price' => $priceData['walk_in_price'],
                    'is_active' => true,
                    'effective_from' => now(),
                    'created_by' => Auth::id()
                ]);
                
                $updatedPrices[] = $price;
            }
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Prices updated successfully',
            'data' => $updatedPrices
        ]);
    }
}