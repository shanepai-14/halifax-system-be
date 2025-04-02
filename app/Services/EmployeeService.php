<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Exception;

class EmployeeService
{
    /**
     * Get all employees with optional filtering
     */
    public function getAllEmployees(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Employee::query();
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('employee_code', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('department', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone_number', 'like', "%{$search}%");
            });
        }

        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply department filter if provided
        if (!empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }

        // Sort by created date if not specified
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new employee
     */
    public function createEmployee(array $data): Employee
    {
        try {
            DB::beginTransaction();
            
            $employee = Employee::create($data);
            
            DB::commit();
            return $employee;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create employee: ' . $e->getMessage());
        }
    }

    /**
     * Get employee by ID
     */
    public function getEmployeeById(int $id): Employee
    {
        $employee = Employee::find($id);
        
        if (!$employee) {
            throw new Exception("Employee with ID {$id} not found");
        }

        return $employee;
    }

    /**
     * Update employee
     */
    public function updateEmployee(int $id, array $data): Employee
    {
        try {
            DB::beginTransaction();

            $employee = $this->getEmployeeById($id);
            $employee->update($data);

            DB::commit();
            return $employee;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update employee: ' . $e->getMessage());
        }
    }

    /**
     * Delete employee
     */
    public function deleteEmployee(int $id): bool 
    {
        try {
            DB::beginTransaction();
            
            $employee = $this->getEmployeeById($id);
            $result = $employee->delete(); // This will trigger soft delete
            
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete employee: ' . $e->getMessage());
        }
    }

    /**
     * Get trashed employees
     */
    public function getTrashedEmployees(): Collection
    {
        return Employee::onlyTrashed()->get();
    }

    /**
     * Restore trashed employee
     */
    public function restoreEmployee(int $id): Employee
    {
        try {
            DB::beginTransaction();

            $employee = Employee::onlyTrashed()->findOrFail($id);
            $employee->restore();

            DB::commit();
            return $employee;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to restore employee: ' . $e->getMessage());
        }
    }

    /**
     * Get employee statistics
     */
    public function getEmployeeStats(): array
    {
        return [
            'total_employees' => Employee::count(),
            'active_employees' => Employee::where('status', Employee::STATUS_ACTIVE)->count(),
            'inactive_employees' => Employee::where('status', Employee::STATUS_INACTIVE)->count(),
            'departments' => Employee::select('department')
                                     ->distinct()
                                     ->pluck('department')
                                     ->toArray(),
            'trashed_employees' => Employee::onlyTrashed()->count()
        ];
    }
}