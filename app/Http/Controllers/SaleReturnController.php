<?php

namespace App\Http\Controllers;

use App\Services\SaleReturnService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class SaleReturnController extends Controller
{
    protected $saleReturnService;

    public function __construct(SaleReturnService $saleReturnService)
    {
        $this->saleReturnService = $saleReturnService;
    }

    /**
     * Display a listing of returns with optional filtering
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
                'sale_id' => $request->sale_id,
                'refund_method' => $request->refund_method,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $returns = $this->saleReturnService->getAllReturns(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $returns,
                'message' => 'Returns retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving returns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created return
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'sale_id' => 'required|exists:sales,id',
                'credit_memo_number' => 'nullable|string',
                'return_date' => 'nullable|date',
                'remarks' => 'nullable|string',
                'refund_method' => 'nullable|string',
                'refund_amount' => 'nullable|numeric|min:0',
                'auto_approve' => 'nullable|boolean',
                'items' => 'required|array|min:1',
                'items.*.sale_item_id' => 'required|exists:sale_items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.return_reason' => 'nullable|string',
                'items.*.condition' => 'nullable|string'
            ]);

            $saleReturn = $this->saleReturnService->createReturn($validated);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified return
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $saleReturn = $this->saleReturnService->getReturnById($id);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Return not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Display a return by credit memo number
     *
     * @param string $creditMemoNumber
     * @return JsonResponse
     */
    public function getByCreditMemoNumber(string $creditMemoNumber): JsonResponse
    {
        try {
            $saleReturn = $this->saleReturnService->getReturnByCreditMemoNumber($creditMemoNumber);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Return not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified return
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'remarks' => 'nullable|string',
                'refund_method' => 'nullable|string',
                'refund_amount' => 'nullable|numeric|min:0'
            ]);

            $saleReturn = $this->saleReturnService->updateReturn($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return updated successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a return
     *
     * @param int $id
     * @return JsonResponse
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $saleReturn = $this->saleReturnService->approveReturn($id);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return approved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error approving return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a return
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string'
            ]);

            $saleReturn = $this->saleReturnService->rejectReturn($id, $validated['reason'] ?? '');

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return rejected successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error rejecting return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete a return
     *
     * @param int $id
     * @return JsonResponse
     */
    public function complete(int $id): JsonResponse
    {
        try {
            $saleReturn = $this->saleReturnService->completeReturn($id);

            return response()->json([
                'status' => 'success',
                'data' => $saleReturn,
                'message' => 'Return completed successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error completing return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get return statistics
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

            $stats = $this->saleReturnService->getReturnStats($filters);

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Return statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving return statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}