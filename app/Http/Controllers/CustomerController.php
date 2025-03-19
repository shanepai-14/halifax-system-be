<?php

namespace App\Http\Controllers;

use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class CustomerController extends Controller
{
    protected $customerService;

    public function __construct(CustomerService $customerService)
    {
        $this->customerService = $customerService;
    }

    /**
     * Display a listing of customers
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->search,
                'city' => $request->city,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $customers = $this->customerService->getAllCustomers(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $customers,
                'message' => 'Customers retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_name' => 'required|string|max:100',
                'contact_number' => 'nullable|string|max:15',
                'email' => 'nullable|email|max:100',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:50'
            ]);

            $customer = $this->customerService->createCustomer($validated);

            return response()->json([
                'status' => 'success',
                'data' => $customer,
                'message' => 'Customer created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(int $id): JsonResponse
    {
        try {
            $customer = $this->customerService->getCustomerById($id);

            return response()->json([
                'status' => 'success',
                'data' => $customer,
                'message' => 'Customer retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_name' => 'sometimes|required|string|max:100',
                'contact_number' => 'nullable|string|max:15',
                'email' => 'nullable|email|max:100',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:50'
            ]);

            $customer = $this->customerService->updateCustomer($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $customer,
                'message' => 'Customer updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->customerService->deleteCustomer($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->customerService->getCustomerStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Customer statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving customer statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of trashed customers
     */
    public function trashed(): JsonResponse
    {
        try {
            $trashedCustomers = $this->customerService->getTrashedCustomers();

            return response()->json([
                'status' => 'success',
                'data' => $trashedCustomers,
                'message' => 'Trashed customers retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving trashed customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a trashed customer
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $customer = $this->customerService->restoreCustomer($id);

            return response()->json([
                'status' => 'success',
                'data' => $customer,
                'message' => 'Customer restored successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error restoring customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}