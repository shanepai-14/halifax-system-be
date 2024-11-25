<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Services\AttributeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class AttributeController extends Controller
{
    protected $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function index(): JsonResponse
    {
        try {
            $attributes = $this->attributeService->getAllAttributes();
            
            return response()->json([
                'status' => 'success',
                'data' => $attributes
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
                'attribute_name' => 'required|string|max:100',
                'unit_of_measurement' => 'required|string|max:50'
            ]);

            $attribute = $this->attributeService->createAttribute($validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Attribute created successfully',
                'data' => $attribute
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Attribute $attribute): JsonResponse
    {
        try {
            $attribute = $this->attributeService->getAttributeWithProducts($attribute);
            
            return response()->json([
                'status' => 'success',
                'data' => $attribute
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Attribute $attribute): JsonResponse
    {
        try {
            $validated = $request->validate([
                'attribute_name' => 'sometimes|required|string|max:100',
                'unit_of_measurement' => 'sometimes|required|string|max:50'
            ]);

            $this->attributeService->updateAttribute($attribute, $validated);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Attribute updated successfully',
                'data' => $attribute->fresh()
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Attribute $attribute): JsonResponse
    {
        try {
            $this->attributeService->deleteAttribute($attribute);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Attribute deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Cannot delete attribute that is in use by products' ? 422 : 500);
        }
    }
}