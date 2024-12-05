<?php

namespace App\Services;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SupplierService
{
    /**
     * Get all suppliers with optional filtering
     */
    public function getAllSuppliers(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = Supplier::query();

        // Apply filters
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('supplier_name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Sort by created date if not specified
        $query->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new supplier
     */
    public function createSupplier(array $data): Supplier
    {
        try {
            DB::beginTransaction();
            
            $supplier = Supplier::create($data);
            
            DB::commit();
            return $supplier;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create supplier: ' . $e->getMessage());
        }
    }

    /**
     * Get supplier by ID
     */
    public function getSupplierById(int $id): Supplier
    {
        $supplier = Supplier::find($id);
        
        if (!$supplier) {
            throw new ModelNotFoundException("Supplier with ID {$id} not found");
        }

        return $supplier;
    }

    /**
     * Update supplier
     */
    public function updateSupplier(int $id, array $data): Supplier
    {
        try {
            DB::beginTransaction();

            $supplier = $this->getSupplierById($id);
            $supplier->update($data);

            DB::commit();
            return $supplier;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to update supplier: ' . $e->getMessage());
        }
    }

    /**
     * Delete supplier
     */
    public function deleteSupplier(int $id): bool 
    {
        try {
            DB::beginTransaction();
            
            $supplier = $this->getSupplierById($id);
            $supplier->delete(); // This will trigger soft delete
            
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to delete supplier: ' . $e->getMessage());
        }
    }

    /**
     * Get trashed suppliers
     */
    public function getTrashedSuppliers(): Collection
    {
        return Supplier::onlyTrashed()->get();
    }

    /**
     * Restore trashed supplier
     */
    public function restoreSupplier(int $id): Supplier
    {
        try {
            DB::beginTransaction();

            $supplier = Supplier::onlyTrashed()->findOrFail($id);
            $supplier->restore();

            DB::commit();
            return $supplier;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to restore supplier: ' . $e->getMessage());
        }
    }

    /**
     * Get supplier statistics
     */
    public function getSupplierStats(): array
    {
        return [
            'total_suppliers' => Supplier::count(),
            'active_suppliers' => Supplier::has('purchaseOrders')->count(),
            'inactive_suppliers' => Supplier::doesntHave('purchaseOrders')->count(),
            'trashed_suppliers' => Supplier::onlyTrashed()->count()
        ];
    }

    /**
     * Get suppliers with their purchase orders
     */
    public function getSuppliersWithPurchaseOrders(): Collection
    {
        return Supplier::with(['purchaseOrders' => function ($query) {
            $query->latest()->take(5);
        }])->get();
    }

    /**
     * Force delete supplier
     */
    public function forceDeleteSupplier(int $id): bool
    {
        try {
            DB::beginTransaction();

            $supplier = Supplier::withTrashed()->findOrFail($id);
            $supplier->forceDelete();

            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to force delete supplier: ' . $e->getMessage());
        }
    }
}