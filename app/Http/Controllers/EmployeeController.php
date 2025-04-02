<?php

namespace App\Http\Controllers;

use App\Services\EmployeeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class EmployeeController extends Controller
{
    protected $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    /**
     * Display a listing of employees
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'department' => $request->department,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $employees = $this->employeeService->getAllEmployees(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $employees,
                'message' => 'Employees retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created employee
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_code' => 'nullable|string|max:20|unique:employees',
                'full_name' => 'required|string|max:100',
                'position' => 'required|string|max:50',
                'department' => 'required|string|max:50',
                'email' => 'nullable|email|max:100',
                'phone_number' => 'nullable|string|max:20',
                'status' => 'required|in:active,inactive'
            ]);

            $employee = $this->employeeService->createEmployee($validated);

            return response()->json([
                'status' => 'success',
                'data' => $employee,
                'message' => 'Employee created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified employee
     */
    public function show(int $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->getEmployeeById($id);

            return response()->json([
                'status' => 'success',
                'data' => $employee,
                'message' => 'Employee retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Employee not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified employee
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_code' => 'nullable|string|max:20|unique:employees,employee_code,' . $id,
                'full_name' => 'sometimes|required|string|max:100',
                'position' => 'sometimes|required|string|max:50',
                'department' => 'sometimes|required|string|max:50',
                'email' => 'nullable|email|max:100',
                'phone_number' => 'nullable|string|max:20',
                'status' => 'sometimes|required|in:active,inactive'
            ]);

            $employee = $this->employeeService->updateEmployee($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $employee,
                'message' => 'Employee updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employee
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->employeeService->deleteEmployee($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Employee deleted successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error deleting employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get employee statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->employeeService->getEmployeeStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Employee statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving employee statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of trashed employees
     */
    public function trashed(): JsonResponse
    {
        try {
            $trashedEmployees = $this->employeeService->getTrashedEmployees();

            return response()->json([
                'status' => 'success',
                'data' => $trashedEmployees,
                'message' => 'Trashed employees retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving trashed employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a trashed employee
     */
    public function restore(int $id): JsonResponse
    {
        try {
            $employee = $this->employeeService->restoreEmployee($id);

            return response()->json([
                'status' => 'success',
                'data' => $employee,
                'message' => 'Employee restored successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error restoring employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}