<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AdditionalCostType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class AdditionalCostTypeController extends Controller
{
    /**
     * Display a listing of the additional cost types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $costTypes = AdditionalCostType::orderBy('name')
                ->withCount('purchaseOrderCosts')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $costTypes
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve cost types',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created additional cost type.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'code' => 'required|string|max:50|unique:additional_cost_types',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'is_deduction' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $costType = AdditionalCostType::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Cost type created successfully',
                'data' => $costType
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create cost type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified additional cost type.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $costType = AdditionalCostType::with('purchaseOrderCosts')
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $costType
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cost type not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified additional cost type.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $costType = AdditionalCostType::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'code' => 'sometimes|required|string|max:50|unique:additional_cost_types,code,' . $id . ',cost_type_id',
                'description' => 'nullable|string|max:255',
                'is_active' => 'boolean',
                'is_deduction' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $costType->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Cost type updated successfully',
                'data' => $costType
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update cost type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified additional cost type.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $costType = AdditionalCostType::findOrFail($id);

            // Check if the cost type is being used
            if ($costType->purchaseOrderCosts()->count() > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete cost type that is being used in purchase orders'
                ], 422);
            }

            $costType->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Cost type deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete cost type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle the active status of the cost type.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleActive($id)
    {
        try {
            $costType = AdditionalCostType::findOrFail($id);
            $costType->update(['is_active' => !$costType->is_active]);

            return response()->json([
                'status' => 'success',
                'message' => 'Cost type status toggled successfully',
                'data' => $costType
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle cost type status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}