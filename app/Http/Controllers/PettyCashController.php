<?php

namespace App\Http\Controllers;

use App\Services\PettyCashService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PettyCashController extends Controller
{
    protected $pettyCashService;

    public function __construct(PettyCashService $pettyCashService)
    {
        $this->pettyCashService = $pettyCashService;
    }

    /**
     * Get petty cash fund balance
     */
    public function getBalance(): JsonResponse
    {
        try {
            $balance = $this->pettyCashService->getAvailableBalance();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'available_balance' => $balance
                ],
                'message' => 'Available balance retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving available balance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of petty cash funds
     */
    public function indexFunds(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $funds = $this->pettyCashService->getAllPettyCashFunds(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $funds,
                'message' => 'Petty cash funds retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving petty cash funds',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created petty cash fund
     */
    public function storeFund(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'transaction_reference' => 'nullable|string|max:20|unique:petty_cash_funds',
                'date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'required|string|max:255',
                'created_by' => 'nullable|exists:users,id',
                'status' => 'nullable|in:pending,approved,rejected'
            ]);

            $fund = $this->pettyCashService->createPettyCashFund($validated);

            return response()->json([
                'status' => 'success',
                'data' => $fund,
                'message' => 'Petty cash fund created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating petty cash fund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a petty cash fund
     */
    public function approveFund(int $id): JsonResponse
    {
        try {
            $fund = $this->pettyCashService->approvePettyCashFund($id);

            return response()->json([
                'status' => 'success',
                'data' => $fund,
                'message' => 'Petty cash fund approved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error approving petty cash fund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of petty cash transactions
     */
    public function indexTransactions(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->search,
                'status' => $request->status,
                'employee_id' => $request->employee_id,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $transactions = $this->pettyCashService->getAllPettyCashTransactions(
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'message' => 'Petty cash transactions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving petty cash transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created petty cash transaction
     */
    public function storeTransaction(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'transaction_reference' => 'nullable|string|max:20|unique:petty_cash_transactions',
                'employee_id' => 'required|exists:employees,id',
                'date' => 'required|date',
                'purpose' => 'required|string|max:100',
                'description' => 'nullable|string',
                'amount_issued' => 'required|numeric|min:0.01',
                'amount_spent' => 'nullable|numeric|min:0',
                'amount_returned' => 'nullable|numeric|min:0',
                'receipt_attachment' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
                'remarks' => 'nullable|string',
                'issued_by' => 'nullable|exists:users,id'
            ]);

            $transaction = $this->pettyCashService->createPettyCashTransaction($validated);

            return response()->json([
                'status' => 'success',
                'data' => $transaction,
                'message' => 'Petty cash transaction created successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating petty cash transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Settle a petty cash transaction
     */
    public function settleTransaction(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'amount_spent' => 'required|numeric|min:0',
                'amount_returned' => 'required|numeric|min:0',
                'receipt_attachment' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
                'remarks' => 'nullable|string'
            ]);

            $transaction = $this->pettyCashService->settlePettyCashTransaction($id, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $transaction,
                'message' => 'Petty cash transaction settled successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error settling petty cash transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a petty cash transaction
     */
    public function approveTransaction(int $id): JsonResponse
    {
        try {
            $transaction = $this->pettyCashService->approvePettyCashTransaction($id);

            return response()->json([
                'status' => 'success',
                'data' => $transaction,
                'message' => 'Petty cash transaction approved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error approving petty cash transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a petty cash transaction
     */
    public function cancelTransaction(Request $request, int $id): JsonResponse
    {
        try {
            $reason = $request->input('reason');
            $transaction = $this->pettyCashService->cancelPettyCashTransaction($id, $reason);

            return response()->json([
                'status' => 'success',
                'data' => $transaction,
                'message' => 'Petty cash transaction cancelled successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error cancelling petty cash transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get transactions by employee
     */
    public function getTransactionsByEmployee(int $employeeId, Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->status,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'sort_by' => $request->sort_by,
                'sort_order' => $request->sort_order
            ];

            $transactions = $this->pettyCashService->getTransactionsByEmployee(
                $employeeId,
                $filters,
                $request->per_page
            );

            return response()->json([
                'status' => 'success',
                'data' => $transactions,
                'message' => 'Employee transactions retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving employee transactions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get petty cash statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->pettyCashService->getPettyCashStats();

            return response()->json([
                'status' => 'success',
                'data' => $stats,
                'message' => 'Petty cash statistics retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving petty cash statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}