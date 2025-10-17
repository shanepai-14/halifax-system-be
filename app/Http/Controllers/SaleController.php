<?php

namespace App\Http\Controllers;

use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Exception;

class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    /**
     * Display a listing of sales with optional filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'customer_type' => $request->customer_type,
                'payment_method' => $request->payment_method,
                'is_delivered' => $request->is_delivered,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $sales = $this->saleService->getAllSales(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $sales,
                'message' => 'Sales retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving sales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created sale
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|exists:customers,id',
                'customer' => 'nullable|array',
                'payment_method' => 'required|string',
                'order_date' => 'required|date',
                'delivery_date' => 'nullable|date',
                'address' => 'nullable|string',
                'city' => 'nullable|string',
                'phone' => 'nullable|string',
                'remarks' => 'nullable|string',
                'term_days' => 'nullable|numeric|min:0',
                'amount_received' => 'nullable|numeric|min:0',
                'change' => 'nullable|numeric|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'cutting_charges' => 'nullable|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.sold_price' => 'required|numeric|min:0',
                'items.*.price_type' => 'required|string',
                'items.*.distribution_price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0|max:100',
                'items.*.composition' => 'nullable|string',
                'items.*.is_discount_approved' => 'nullable|boolean',
                'items.*.approved_by' => 'nullable|exists:users,id'
            ]);

            $sale = $this->saleService->createSale($validated);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified sale
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $sale = $this->saleService->getSaleById($id);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sale not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Display a sale by invoice number
     *
     * @param string $invoiceNumber
     * @return JsonResponse
     */
    public function getByInvoiceNumber(string $invoiceNumber): JsonResponse
    {
        try {
            $sale = $this->saleService->getSaleByInvoiceNumber($invoiceNumber);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sale not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'customer_id' => 'nullable|exists:customers,id',
                'customer' => 'nullable|array',
                'customer.id' => 'nullable|exists:customers,id',
                'customer.customer_name' => 'nullable|string|max:255',
                'customer.contact_number' => 'nullable|string|max:20',
                'customer.email' => 'nullable|email|max:255',
                'customer.address' => 'nullable|string|max:500',
                'customer.city' => 'nullable|string|max:100',
                'payment_method' => 'required|string|in:cash,cod,cheque,online,term',
                'delivery_date' => 'required|date',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'term_days' => 'nullable|integer|min:0',
                'delivery_fee' => 'nullable|numeric|min:0',
                'cutting_charges' => 'nullable|numeric|min:0',
                'items' => 'required|array|min:1',
                'items.*.id' => 'nullable|exists:sale_items,id', // For existing items
                'items.*.product_id' => 'required|exists:products,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.distribution_price' => 'required|numeric|min:0',
                'items.*.sold_price' => 'required|numeric|min:0',
                'items.*.discount' => 'nullable|numeric|min:0|max:100',
                'items.*.price_type' => 'nullable|string|in:regular,wholesale,walkin',
                'items.*.composition' => 'nullable|string',
                'removed_items' => 'nullable|array',
                'removed_items.*' => 'exists:sale_items,id'
            ]);

            $sale = $this->saleService->updateSaleWithItems($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the payment for a sale
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updatePayment(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'nullable|date',
                'reference_number' => 'nullable|string',
                'remarks' => 'nullable|string'
            ]);

            $sale = $this->saleService->updateSalePayment($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale payment updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating sale payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a sale
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string'
            ]);

            $sale = $this->saleService->cancelSale($id, $validated['reason'] ?? '');

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale cancelled successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cancelling sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a sale as delivered
     *
     * @param int $id
     * @return JsonResponse
     */
    public function markAsDelivered(int $id): JsonResponse
    {
        try {
            $sale = $this->saleService->markAsDelivered($id);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Sale marked as delivered successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error marking sale as delivered',
                'error' => $e->getMessage()
            ], 500);
        }
    }

        public function updateDeliveryDate(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'delivery_date' => 'required|date|after_or_equal:today'
            ]);

            $sale = $this->saleService->updateDeliveryDate($id, $request->delivery_date);

            return response()->json([
                'status' => 'success',
                'data' => $sale,
                'message' => 'Delivery date updated successfully'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating delivery date',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $filters = [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to
            ];

            $stats = $this->saleService->getSalesStats($filters);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Sales statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving sales statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

        public function getCustomerPurchaseHistory(int $customerId, Request $request): JsonResponse
        {
            try {
                $page = $request->input('page', 1);
                $perPage = $request->input('per_page', 50);
                
                $purchaseHistory = $this->saleService->getCustomerPurchaseHistory($customerId, $page, $perPage);

                return response()->json([
                    'status' => 'success',
                    'data' => $purchaseHistory,
                    'message' => 'Customer purchase history retrieved successfully'
                ]);
            } catch (Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error retrieving customer purchase history',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        public function saveDeliveryReport(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
            'invoice_number' => 'required|string',
            'sale_id' => 'required|integer|exists:sales,id',
            'filename' => 'required|string'
        ]);

        try {
            // Create directory structure: delivery_reports/YYYY/MM/
            $directory = 'delivery_reports/';
            
            // Generate unique filename to prevent overwrites
            $filename = pathinfo($request->filename, PATHINFO_FILENAME);
            $extension = pathinfo($request->filename, PATHINFO_EXTENSION) ?: 'txt';
            $finalFilename = $filename . '_' . time() . '.' . $extension;
            
            // Full path for the file
            $filePath = $directory . '/' . $finalFilename;
            
            // Save the text content to storage
            Storage::disk('local')->put($filePath, $request->content);
            

            
            return response()->json([
                'success' => true,
                'message' => 'Delivery report saved successfully',
                'file_path' => $filePath,
                'file_name' => $finalFilename
            ], 200);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save delivery report',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}