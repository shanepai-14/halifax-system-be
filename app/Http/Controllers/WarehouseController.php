<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class WarehouseController extends Controller
{
    /**
     * Get all warehouses with pagination and filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Warehouse::query();

            // Apply search filter
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%");
                });
            }

            // Apply active filter
            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Apply sorting
            $sortBy = $request->input('sort_by', 'name');
            $sortDirection = $request->input('sort_direction', 'asc');
            $query->orderBy($sortBy, $sortDirection);

            $warehouses = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $warehouses,
                'message' => 'Warehouses retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new warehouse
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'code' => 'required|string|max:10|unique:warehouses,code',
                'name' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'contact_person' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:255',
                'description' => 'nullable|string|max:1000'
            ], [
                'code.required' => 'Warehouse code is required',
                'code.unique' => 'This warehouse code already exists',
                'code.max' => 'Warehouse code cannot exceed 10 characters',
                'name.required' => 'Warehouse name is required',
                'name.max' => 'Warehouse name cannot exceed 255 characters',
                'contact_email.email' => 'Please provide a valid email address'
            ]);

            $warehouse = Warehouse::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $warehouse
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single warehouse with details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Add additional computed properties
            $warehouse->total_transfers = $warehouse->total_transfers;
            $warehouse->completed_transfers = $warehouse->completed_transfers;
            $warehouse->pending_transfers = $warehouse->pending_transfers;

            return response()->json([
                'success' => true,
                'data' => $warehouse,
                'message' => 'Warehouse retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update warehouse
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $validatedData = $request->validate([
                'code' => 'sometimes|required|string|max:10|unique:warehouses,code,' . $id,
                'name' => 'sometimes|required|string|max:255',
                'location' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:500',
                'contact_person' => 'nullable|string|max:255',
                'contact_phone' => 'nullable|string|max:20',
                'contact_email' => 'nullable|email|max:255',
                'is_active' => 'sometimes|boolean',
                'description' => 'nullable|string|max:1000'
            ], [
                'code.unique' => 'This warehouse code already exists',
                'contact_email.email' => 'Please provide a valid email address'
            ]);

            $warehouse->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Warehouse updated successfully',
                'data' => $warehouse->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete warehouse (soft delete)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            // Check if warehouse has active transfers
            $activeTransfers = Transfer::where('to_warehouse_id', $id)
                ->where('status', 'in_transit')
                ->count();

            if ($activeTransfers > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete warehouse with {$activeTransfers} active transfers"
                ], 400);
            }

            $warehouse->delete();

            return response()->json([
                'success' => true,
                'message' => 'Warehouse deleted successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete warehouse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouse transfers
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function transfers(Request $request, int $id): JsonResponse
    {
        try {
            $warehouse = Warehouse::findOrFail($id);

            $transfers = Transfer::with(['creator', 'items.product'])
                ->where('to_warehouse_id', $id)
                ->when($request->input('status'), function ($query, $status) {
                    return $query->where('status', $status);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => [
                    'warehouse' => $warehouse,
                    'transfers' => $transfers
                ],
                'message' => 'Warehouse transfers retrieved successfully'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouse transfers',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}