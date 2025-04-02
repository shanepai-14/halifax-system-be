<?php

namespace App\Services;

use App\Models\PettyCashFund;
use App\Models\PettyCashTransaction;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Exception;

class PettyCashService
{
    /**
     * Get total available petty cash fund balance
     */
    public function getAvailableBalance(): float
    {
        $totalFunds = PettyCashFund::where('status', PettyCashFund::STATUS_APPROVED)->sum('amount');
        $totalIssued = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_ISSUED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_issued');
        $totalReturned = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_SETTLED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_returned');
        $totalSpent = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_SETTLED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_spent');
        
        return $totalFunds - ($totalIssued - $totalReturned - $totalSpent);
    }

    /**
     * Create a new petty cash fund
     */
    public function createPettyCashFund(array $data): PettyCashFund
    {
        try {
            DB::beginTransaction();
            
            // Set current user as creator if not provided
            if (empty($data['created_by'])) {
                $data['created_by'] = Auth::id();
            }
            
            $fund = PettyCashFund::create($data);
            
            DB::commit();
            return $fund;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create petty cash fund: ' . $e->getMessage());
        }
    }

    /**
     * Approve a petty cash fund
     */
    public function approvePettyCashFund(int $id): PettyCashFund
    {
        try {
            DB::beginTransaction();
            
            $fund = PettyCashFund::findOrFail($id);
            
            // Check if fund is already approved
            if ($fund->status === PettyCashFund::STATUS_APPROVED) {
                throw new Exception('Fund is already approved');
            }
            
            // Update fund status
            $fund->update([
                'status' => PettyCashFund::STATUS_APPROVED,
                'approved_by' => Auth::id()
            ]);
            
            DB::commit();
            return $fund->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to approve petty cash fund: ' . $e->getMessage());
        }
    }

    /**
     * Get all petty cash funds with optional filtering
     */
    public function getAllPettyCashFunds(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = PettyCashFund::with(['creator', 'approver']);
        
        // Apply date range filter if provided
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        
        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Sort by date if not specified
        $query->orderBy($filters['sort_by'] ?? 'date', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Create a new petty cash transaction (issuance to employee)
     */
    public function createPettyCashTransaction(array $data): PettyCashTransaction
    {
        try {
            DB::beginTransaction();
            
            // Verify available balance
            $availableBalance = $this->getAvailableBalance();
            if ($availableBalance < $data['amount_issued']) {
                throw new Exception('Insufficient petty cash fund balance. Available: ' . $availableBalance);
            }
            
            // Set current user as issuer if not provided
            if (empty($data['issued_by'])) {
                $data['issued_by'] = Auth::id();
            }
            
            // Set default values for new transaction
            $data['amount_spent'] = $data['amount_spent'] ?? 0;
            $data['amount_returned'] = $data['amount_returned'] ?? 0;
            
            $transaction = PettyCashTransaction::create($data);
            
            // Handle receipt attachment if provided
            if (!empty($data['receipt_attachment']) && $data['receipt_attachment']->isValid()) {
                $path = $this->storeReceiptAttachment($data['receipt_attachment'], $transaction->transaction_reference);
                $transaction->update(['receipt_attachment' => $path]);
            }
            
            DB::commit();
            return $transaction;
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to create petty cash transaction: ' . $e->getMessage());
        }
    }

    /**
     * Settle a petty cash transaction
     */
    public function settlePettyCashTransaction(int $id, array $data): PettyCashTransaction
    {
        try {
            DB::beginTransaction();
            
            $transaction = PettyCashTransaction::findOrFail($id);
            
            // Check if transaction can be settled
            if ($transaction->status !== PettyCashTransaction::STATUS_ISSUED) {
                throw new Exception('Transaction cannot be settled because it is not in issued status');
            }
            
            // Update transaction with settlement data
            $transaction->update([
                'amount_spent' => $data['amount_spent'],
                'amount_returned' => $data['amount_returned'],
                'remarks' => $data['remarks'] ?? $transaction->remarks,
                'status' => PettyCashTransaction::STATUS_SETTLED
            ]);
            
            // Verify remaining amount
            $remainingAmount = $transaction->amount_issued - $transaction->amount_spent - $transaction->amount_returned;
            if ($remainingAmount != 0) {
                throw new Exception('Settlement amount does not match. Remaining: ' . $remainingAmount);
            }
            
            // Handle receipt attachment if provided
            if (!empty($data['receipt_attachment']) && $data['receipt_attachment']->isValid()) {
                // Delete old receipt if exists
                if ($transaction->receipt_attachment) {
                    Storage::disk('public')->delete($transaction->receipt_attachment);
                }
                
                $path = $this->storeReceiptAttachment($data['receipt_attachment'], $transaction->transaction_reference);
                $transaction->update(['receipt_attachment' => $path]);
            }
            
            DB::commit();
            return $transaction->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to settle petty cash transaction: ' . $e->getMessage());
        }
    }

    /**
     * Approve a settled petty cash transaction
     */
    public function approvePettyCashTransaction(int $id): PettyCashTransaction
    {
        try {
            DB::beginTransaction();
            
            $transaction = PettyCashTransaction::findOrFail($id);
            
            // Check if transaction can be approved
            if ($transaction->status !== PettyCashTransaction::STATUS_SETTLED) {
                throw new Exception('Transaction cannot be approved because it is not settled');
            }
            
            // Update transaction status
            $transaction->update([
                'status' => PettyCashTransaction::STATUS_APPROVED,
                'approved_by' => Auth::id()
            ]);
            
            DB::commit();
            return $transaction->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to approve petty cash transaction: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a petty cash transaction
     */
    public function cancelPettyCashTransaction(int $id, string $reason = null): PettyCashTransaction
    {
        try {
            DB::beginTransaction();
            
            $transaction = PettyCashTransaction::findOrFail($id);
            
            // Check if transaction can be cancelled
            if (!in_array($transaction->status, [
                PettyCashTransaction::STATUS_ISSUED,
                PettyCashTransaction::STATUS_SETTLED
            ])) {
                throw new Exception('Transaction cannot be cancelled because it is in ' . $transaction->status . ' status');
            }
            
            // Update transaction status
            $transaction->update([
                'status' => PettyCashTransaction::STATUS_CANCELLED,
                'remarks' => $reason ? ($transaction->remarks . ' | Cancelled: ' . $reason) : $transaction->remarks
            ]);
            
            DB::commit();
            return $transaction->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception('Failed to cancel petty cash transaction: ' . $e->getMessage());
        }
    }

    /**
     * Get all petty cash transactions with optional filtering
     */
    public function getAllPettyCashTransactions(array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = PettyCashTransaction::with(['employee', 'issuer', 'approver']);
        
        // Apply employee filter if provided
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        
        // Apply date range filter if provided
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        
        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply search filter if provided
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('transaction_reference', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($employeeQuery) use ($search) {
                      $employeeQuery->where('full_name', 'like', "%{$search}%")
                                    ->orWhere('employee_code', 'like', "%{$search}%");
                  });
            });
        }
        
        // Sort by date if not specified
        $query->orderBy($filters['sort_by'] ?? 'date', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get transactions by employee
     */
    public function getTransactionsByEmployee(int $employeeId, array $filters = [], int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = PettyCashTransaction::with(['issuer', 'approver'])
            ->where('employee_id', $employeeId);
        
        // Apply date range filter if provided
        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
        
        // Apply status filter if provided
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Sort by date if not specified
        $query->orderBy($filters['sort_by'] ?? 'date', $filters['sort_order'] ?? 'desc');

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    /**
     * Get petty cash statistics
     */
    public function getPettyCashStats(): array
    {
        $totalFunds = PettyCashFund::where('status', PettyCashFund::STATUS_APPROVED)->sum('amount');
        $pendingFunds = PettyCashFund::where('status', PettyCashFund::STATUS_PENDING)->sum('amount');
        $totalIssued = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_ISSUED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_issued');
        $totalSpent = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_SETTLED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_spent');
        $totalReturned = PettyCashTransaction::whereIn('status', [
            PettyCashTransaction::STATUS_SETTLED,
            PettyCashTransaction::STATUS_APPROVED
        ])->sum('amount_returned');
        $availableBalance = $totalFunds - ($totalIssued - $totalReturned - $totalSpent);
        
        return [
            'total_funds' => $totalFunds,
            'pending_funds' => $pendingFunds,
            'total_issued' => $totalIssued,
            'total_spent' => $totalSpent,
            'total_returned' => $totalReturned,
            'available_balance' => $availableBalance,
            'total_transactions' => PettyCashTransaction::count(),
            'pending_transactions' => PettyCashTransaction::where('status', PettyCashTransaction::STATUS_ISSUED)->count(),
            'settled_transactions' => PettyCashTransaction::where('status', PettyCashTransaction::STATUS_SETTLED)->count(),
            'approved_transactions' => PettyCashTransaction::where('status', PettyCashTransaction::STATUS_APPROVED)->count(),
            'cancelled_transactions' => PettyCashTransaction::where('status', PettyCashTransaction::STATUS_CANCELLED)->count(),
        ];
    }

    /**
     * Store receipt attachment
     */
    protected function storeReceiptAttachment($file, string $reference): string
    {
        $fileName = $reference . '_' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('petty-cash/receipts', $fileName, 'public');
        return $path;
    }
}