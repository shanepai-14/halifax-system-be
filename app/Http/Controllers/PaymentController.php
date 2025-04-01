<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        
    }

    /**
     * Store a newly created payment for a sale
     *
     * @param Request $request
     * @param int $saleId
     * @return JsonResponse
     */
    public function store(Request $request, int $saleId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_method' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'nullable|date',
                'reference_number' => 'nullable|string',
                'received_by' => 'required|numeric',
                'remarks' => 'nullable|string'
            ]);

            $payment = $this->paymentService->createPayment($saleId, $validated);

            return response()->json([
                'status' => 'success',
                'data' => $payment,
                'message' => 'Payment recorded successfully'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error recording payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display payment history for a sale
     *
     * @param int $saleId
     * @return JsonResponse
     */
    public function history(int $saleId): JsonResponse
    {
        try {
            $payments = $this->paymentService->getSalePayments($saleId);

            return response()->json([
                'status' => 'success',
                'data' => $payments,
                'message' => 'Payment history retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Void a payment
     *
     * @param Request $request
     * @param int $paymentId
     * @return JsonResponse
     */
    public function void(Request $request, int $paymentId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'required|string'
            ]);

            $payment = $this->paymentService->voidPayment($paymentId, $validated['reason']);

            return response()->json([
                'status' => 'success',
                'data' => $payment->sale,
                'message' => 'Payment voided successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error voiding payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment receipt details
     *
     * @param int $paymentId
     * @return JsonResponse
     */
    public function receipt(int $paymentId): JsonResponse
    {
        try {
            $receipt = $this->paymentService->getPaymentReceipt($paymentId);

            return response()->json([
                'status' => 'success',
                'data' => $receipt,
                'message' => 'Payment receipt retrieved successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving payment receipt',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}