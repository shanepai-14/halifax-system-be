<?php

namespace App\Http\Controllers;

use App\Services\SupplierService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class SupplierController extends Controller
{
    protected $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    /**
     * Display a listing of suppliers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->search,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $suppliers = $this->supplierService->getAllSuppliers(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $suppliers,
                'message' => 'Suppliers retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created supplier
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_name' => 'required|string|max:100',
                'contact_person' => 'required|string|max:100',
                'phone' => 'required|string|max:15',
                'email' => 'required|email|max:100',
                'address' => 'required|string'
            ]);

            $supplier = $this->supplierService->createSupplier($validated);

            return response()->json([
                'status' => 'success',
                'data' => $supplier,
                'message' => 'Supplier created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified supplier
     */
    public function show(string $id): JsonResponse
    {
        try {
            $supplier = $this->supplierService->getSupplierById($id);

            return response()->json([
                'status' => 'success',
                'data' => $supplier,
                'message' => 'Supplier retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Supplier not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified supplier
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'supplier_name' => 'sometimes|required|string|max:100',
                'contact_person' => 'sometimes|required|string|max:100',
                'phone' => 'sometimes|required|string|max:15',
                'email' => 'sometimes|required|email|max:100',
                'address' => 'sometimes|required|string'
            ]);

            $supplier = $this->supplierService->updateSupplier($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $supplier,
                'message' => 'Supplier updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified supplier
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->supplierService->deleteSupplier($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Supplier deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting supplier',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->supplierService->getSupplierStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Supplier statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving supplier statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
     public function purchaseHistory(string $id): JsonResponse
    {
        try {
            $history = $this->supplierService->getSupplierPurchaseHistory($id);

            return response()->json([
                'status' => 'success',
                'data' => $history,
                'message' => 'Supplier purchase history retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving supplier purchase history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}