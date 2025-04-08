<?php

namespace App\Services;

use App\Models\SalePayment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class PaymentService
{
    /**
     * Create a new payment for a sale
     *
     * @param int $saleId
     * @param array $data
     * @return Payment
     * @throws Exception
     */
    public function createPayment(int $saleId, array $data): SalePayment
    {
        try {
            DB::beginTransaction();
            
            // Get the sale
            $sale = Sale::findOrFail($saleId);
            
            // Check if sale can be paid for
            if ($sale->status === Sale::STATUS_CANCELLED) {
                throw new Exception('Cannot process payment for a cancelled sale');
            }
            
            // Set user ID to current user if not provided
            if (empty($data['user_id'])) {
                $data['user_id'] = Auth::id();
            }

                     // Set payment status based on payment method
          $paymentStatus = SalePayment::STATUS_COMPLETED;
            
                     // If payment method is cheque, set status to pending
           if ($data['payment_method'] === 'cheque') {
                         $paymentStatus = SalePayment::STATUS_PENDING;
            }
            
            $payment = new SalePayment([
                'sale_id' => $saleId,
                'user_id' => $data['user_id'],
                'payment_method' => $data['payment_method'],
                'amount' => $data['amount'],
                'change' => max(0, $data['amount'] - $sale->total),
                'payment_date' => $data['payment_date'] ?? now(),
                'reference_number' => $data['reference_number'] ?? null,
                'received_by' => $data['received_by'],
                'remarks' => $data['remarks'] ?? null,
                'status' => $paymentStatus 
            ]);
            
            $payment->save();
            
            // Update the sale's payment information
            $totalPaid = $sale->payments()
            ->where('status', SalePayment::STATUS_COMPLETED)
            ->sum('amount') ?? 0;

            $status = Sale::STATUS_COMPLETED;

            if( $totalPaid < $sale->total){
                $status = Sale::STATUS_PARTIALLY_PAID;
            }

            $sale->update([
                'amount_received' => $totalPaid,
                'status' => $status,
            ]);
            
            DB::commit();
            
            // Return the updated sale with the new payment
            return $payment->fresh(['sale','receivedBy']);

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get payments for a sale
     *
     * @param int $saleId
     * @return Collection
     */
    public function getSalePayments(int $saleId): Collection
    {
        return SalePayment::with(['user', 'receivedBy', 'voidedBy'])
            ->where('sale_id', $saleId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }
    
    /**
     * Void a payment
     *
     * @param int $paymentId
     * @param string $reason
     * @return Payment
     * @throws Exception
     */
    public function voidPayment(int $paymentId, string $reason): SalePayment
    {
        try {
            DB::beginTransaction();
            
            // Get the payment
            $payment = SalePayment::findOrFail($paymentId);
            
            // Check if payment is already voided
            if ($payment->status === SalePayment::STATUS_VOIDED) {
                throw new Exception('Payment is already voided');
            }
            
            // Void the payment
            $payment->update([
                'status' => SalePayment::STATUS_VOIDED,
                'void_reason' => $reason,
                'voided_at' => now(),
                'voided_by' => Auth::id()
            ]);


            
            // Update the sale's payment information
            $sale = $payment->sale;
            $totalPaid = $sale->payments()->where('status', SalePayment::STATUS_COMPLETED)->sum('amount') ?? 0;

            $status = Sale::STATUS_UNPAID;

            if ($totalPaid >= $sale->total && $sale->total > 0) {
                $status = Sale::STATUS_COMPLETED;
            } elseif ($totalPaid > 0 && $totalPaid < $sale->total) {
                $status = Sale::STATUS_PARTIALLY_PAID;
            }

            $sale->update([
                'amount_received' => $totalPaid,
                'status' => $status,
            ]);
            

        
            DB::commit();
            
            // Return the updated payment with sale
            return $payment->fresh(['sale']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Get payment by ID
     *
     * @param int $paymentId
     * @return Payment
     */
    public function getPaymentById(int $paymentId): SalePayment
    {
        return SalePayment::with(['sale', 'user', 'receivedBy', 'voidedBy'])
            ->findOrFail($paymentId);
    }
    
    /**
     * Get payment receipt details
     *
     * @param int $paymentId
     * @return array
     */
    public function getPaymentReceipt(int $paymentId): array
    {
        $payment = $this->getPaymentById($paymentId);
        $sale = $payment->sale;
        
        return [
            'payment' => $payment,
            'sale' => $sale->load('items.product', 'customer')
        ];
    }

    public function getPaymentStats(array $filters = []): array
    {
        $query = SalePayment::query();
        
        // Apply date filters if provided
        if (!empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }
        
        // Calculate statistics
        return [
            'total_payments' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'completed_amount' => (clone $query)->where('status', SalePayment::STATUS_COMPLETED)->sum('amount'),
            'voided_amount' => (clone $query)->where('status', SalePayment::STATUS_VOIDED)->sum('amount'),
            'cash_payments' => (clone $query)->where('payment_method', SalePayment::METHOD_CASH)->count(),
            'electronic_payments' => (clone $query)->whereIn('payment_method', [
                SalePayment::METHOD_CREDIT_CARD,
                SalePayment::METHOD_DEBIT_CARD,
                SalePayment::METHOD_BANK_TRANSFER,
                SalePayment::METHOD_ONLINE,
                SalePayment::METHOD_MOBILE_PAYMENT
            ])->count()
        ];
    }

    public function getAllPayments(array $filters = [], ?int $perPage = null)
    {
        $query = SalePayment::with(['sale.customer','sale.items', 'receivedBy', 'user', 'voidedBy']);
        
        // Apply filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                ->orWhereHas('sale', function ($sq) use ($search) {
                    $sq->where('invoice_number', 'like', "%{$search}%");
                });
            });
        }

        if (!empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'payment_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginate results
        $perPage = $perPage ?? 10;
        return $query->paginate($perPage);
    }
}