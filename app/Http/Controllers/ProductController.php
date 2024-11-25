<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    public function index(): JsonResponse
    {
        try {
            $products = $this->productService->getAllProducts();
            
            return response()->json([
                'status' => 'success',
                'data' => $products
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_name' => 'required|string|max:100',
                'product_category_id' => 'required|exists:product_categories,id',
                'reorder_level' => 'required|integer|min:0',
                'product_type' => 'required|string|max:10',
                'attributes' => 'sometimes|array',
                'attributes.*.attribute_id' => 'required|exists:attributes,id',
                'attributes.*.value' => 'required|numeric'
            ]);

            $product = $this->productService->createProduct($validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Product $product): JsonResponse
    {
        try {
            $product = $this->productService->getProductDetails($product);
            
            return response()->json([
                'status' => 'success',
                'data' => $product
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_name' => 'sometimes|required|string|max:100',
                'product_category_id' => 'sometimes|required|exists:product_categories,id',
                'reorder_level' => 'sometimes|required|integer|min:0',
                'attributes' => 'sometimes|array',
                'attributes.*.attribute_id' => 'required|exists:attributes,id',
                'attributes.*.value' => 'required|numeric'
            ]);

            $updatedProduct = $this->productService->updateProduct($product, $validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product updated successfully',
                'data' => $updatedProduct
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->productService->deleteProduct($product);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Product deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}