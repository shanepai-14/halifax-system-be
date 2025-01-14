<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrderAdditionalCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class PurchaseOrderAdditionalCostController extends Controller
{
    /**
     * Display a listing of purchase order additional costs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            $costs = PurchaseOrderAdditionalCost::with(['purchaseOrder', 'costType'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $costs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve additional costs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created additional cost.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'po_id' => 'required|exists:purchase_orders,po_id',
                'cost_type_id' => 'required|exists:additional_cost_types,cost_type_id',
                'amount' => 'required|numeric|min:0',
                'remarks' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cost = PurchaseOrderAdditionalCost::create($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Additional cost created successfully',
                'data' => $cost->load(['purchaseOrder', 'costType'])
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create additional cost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified additional cost.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $cost = PurchaseOrderAdditionalCost::with(['purchaseOrder', 'costType'])
                ->find($id);

            if (!$cost) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Additional cost not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $cost
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve additional cost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified additional cost.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $cost = PurchaseOrderAdditionalCost::find($id);

            if (!$cost) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Additional cost not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'po_id' => 'sometimes|required|exists:purchase_orders,po_id',
                'cost_type_id' => 'sometimes|required|exists:additional_cost_types,cost_type_id',
                'amount' => 'sometimes|required|numeric|min:0',
                'remarks' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cost->update($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Additional cost updated successfully',
                'data' => $cost->load(['purchaseOrder', 'costType'])
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update additional cost',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified additional cost.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $cost = PurchaseOrderAdditionalCost::find($id);

            if (!$cost) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Additional cost not found'
                ], 404);
            }

            $cost->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Additional cost deleted successfully'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete additional cost',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}