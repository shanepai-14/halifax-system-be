<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerService
{
    /**
     * Get all customers with optional filtering
     */
    public function getAllCustomers(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Customer::query();
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('contact_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Apply city filter if provided
        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        // Sort by created date if not specified
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new customer
     */
    public function createCustomer(array $data): Customer
    {
        try {
            DB::beginTransaction();
            
            $customer = Customer::create($data);
            
            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer by ID
     */
    public function getCustomerById(int $id): Customer
    {
        $customer = Customer::find($id);
        
        if (!$customer) {
            throw new ModelNotFoundException("Customer with ID {$id} not found");
        }

        return $customer;
    }

    /**
     * Update customer
     */
    public function updateCustomer(int $id, array $data): Customer
    {
        try {
            DB::beginTransaction();

            $customer = $this->getCustomerById($id);
            $customer->update($data);

            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update customer: ' . $e->getMessage());
        }
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(int $id): bool 
    {
        try {
            DB::beginTransaction();
            
            $customer = $this->getCustomerById($id);
            $result = $customer->delete(); // This will trigger soft delete
            
            DB::commit();
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete customer: ' . $e->getMessage());
        }
    }

    /**
     * Get trashed customers
     */
    public function getTrashedCustomers(): Collection
    {
        return Customer::onlyTrashed()->get();
    }

    /**
     * Restore trashed customer
     */
    public function restoreCustomer(int $id): Customer
    {
        try {
            DB::beginTransaction();

            $customer = Customer::onlyTrashed()->findOrFail($id);
            $customer->restore();

            DB::commit();
            return $customer;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to restore customer: ' . $e->getMessage());
        }
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(): array
    {
        return [
            'total_customers' => Customer::count(),
            'new_customers_this_month' => Customer::whereMonth('created_at', date('m'))
                                            ->whereYear('created_at', date('Y'))
                                            ->count(),
            'cities' => Customer::select('city')
                                    ->whereNotNull('city')
                                    ->groupBy('city')
                                    ->pluck('city')
                                    ->count(),
            'trashed_customers' => Customer::onlyTrashed()->count()
        ];
    }
}