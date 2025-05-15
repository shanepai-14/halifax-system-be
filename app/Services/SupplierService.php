<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceivedItem;
use App\Models\ReceivingReport;
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

    public function getSupplierPurchaseHistory(string $supplierId): array
    {
        // Get the supplier
        $supplier = Supplier::findOrFail($supplierId);
        
        // Get purchase orders for this supplier
        $purchaseOrders = PurchaseOrder::where('supplier_id', $supplierId)
            ->orderBy('po_date', 'desc')
            ->get();
            
        // Get receiving reports for these purchase orders
        $receivingReportIds = ReceivingReport::whereIn('po_id', $purchaseOrders->pluck('po_id'))
            ->pluck('rr_id');
            
        // Get all received items for these reports
        $receivedItems = PurchaseOrderReceivedItem::whereIn('rr_id', $receivingReportIds)
            ->with(['product', 'receivingReport.purchaseOrder'])
            ->get()
            ->map(function ($item) {
                // Access the related models and process the data
                $receivingReport = $item->receivingReport;
                $purchaseOrder = $receivingReport ? $receivingReport->purchaseOrder : null;
                
                return [
                    'received_item_id' => $item->received_item_id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->product_name ?? 'Unknown Product',
                    'product_code' => $item->product->product_code ?? 'N/A',
                    'received_quantity' => $item->received_quantity,
                    'cost_price' => $item->cost_price,
                    'walk_in_price' => $item->walk_in_price,
                    'wholesale_price' => $item->wholesale_price,
                    'regular_price' => $item->regular_price,
                    'total_cost' => $item->received_quantity * $item->cost_price,
                    'batch_number' => $receivingReport ? $receivingReport->batch_number : 'N/A',
                    'rr_date' => $receivingReport ? $receivingReport->created_at->format('Y-m-d') : 'N/A',
                    'po_number' => $purchaseOrder ? $purchaseOrder->po_number : 'N/A',
                    'po_date' => $purchaseOrder ? $purchaseOrder->po_date->format('Y-m-d') : 'N/A',
                    'payment_status' => $receivingReport ? ($receivingReport->is_paid ? 'Paid' : 'Unpaid') : 'N/A',
                    'invoice' => $receivingReport ? $receivingReport->invoice : 'N/A'
                ];
            });
        
        // Calculate statistics
        $totalItems = $receivedItems->count();
        $totalQuantity = $receivedItems->sum('received_quantity');
        $totalValue = $receivedItems->sum('total_cost');
        $latestPurchase = $purchaseOrders->sortByDesc('po_date')->first();
        
        // Group items by PO for easier display
        $itemsByPO = $receivedItems->groupBy('po_number');
        
        return [
            'supplier' => $supplier,
            'stats' => [
                'total_orders' => $purchaseOrders->count(),
                'total_items' => $totalItems,
                'total_quantity' => $totalQuantity,
                'total_value' => $totalValue,
                'latest_purchase' => $latestPurchase ? $latestPurchase->po_date->format('Y-m-d') : 'N/A'
            ],
            'purchase_orders' => $purchaseOrders,
            'items' => $receivedItems,
            'items_by_po' => $itemsByPO
        ];
    }
}