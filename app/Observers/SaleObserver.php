<?php

namespace App\Observers;

use App\Models\Sale;
use App\Services\SalesSummaryService;
use Illuminate\Support\Facades\Log;

class SaleObserver
{
    protected $summaryService;

    // Fields that affect financial summaries
    protected const FINANCIAL_FIELDS = [
        'total', 'cogs', 'profit', 'status', 'order_date', 
        'payment_method', 'customer_type'
    ];

    public function __construct(SalesSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    /**
     * Handle the Sale "created" event.
     */
    public function created(Sale $sale)
    {
        try {
            $this->summaryService->updateSummariesForSale($sale, 'created');
            Log::info('Sales summaries updated for new sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update summaries for created sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Sale "updated" event.
     */
    public function updated(Sale $sale)
    {
        // Check if any financial fields were changed using wasChanged()
        $hasFinancialChanges = $sale->wasChanged(self::FINANCIAL_FIELDS);

        if ($hasFinancialChanges) {
            try {
                $changedFields = array_intersect_key(
                    $sale->getChanges(), 
                    array_flip(self::FINANCIAL_FIELDS)
                );

                // Special handling for order_date changes
                if ($sale->wasChanged('order_date')) {
                    $this->handleOrderDateChange($sale);
                }

                // Special handling for status changes
                if ($sale->wasChanged('status')) {
                    $this->handleStatusChange($sale);
                }

                // Update summaries for current state
                $this->summaryService->updateSummariesForSale($sale, 'updated');
                
                Log::info('Sales summaries updated for modified sale', [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'changed_fields' => array_keys($changedFields),
                    'old_values' => $this->getOriginalValues($sale, array_keys($changedFields)),
                    'new_values' => $changedFields
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to update summaries for updated sale', [
                    'sale_id' => $sale->id,
                    'invoice_number' => $sale->invoice_number,
                    'changed_fields' => array_keys($sale->getChanges()),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Handle the Sale "deleted" event.
     */
    public function deleted(Sale $sale)
    {
        try {
            $this->summaryService->updateSummariesForSale($sale, 'deleted');
            Log::info('Sales summaries updated for deleted sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update summaries for deleted sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Sale "restored" event.
     */
    public function restored(Sale $sale)
    {
        try {
            $this->summaryService->updateSummariesForSale($sale, 'created');
            Log::info('Sales summaries updated for restored sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update summaries for restored sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the Sale "force deleted" event.
     */
    public function forceDeleted(Sale $sale)
    {
        try {
            $this->summaryService->updateSummariesForSale($sale, 'deleted');
            Log::info('Sales summaries updated for force deleted sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update summaries for force deleted sale', [
                'sale_id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle order date changes - need to update both old and new date summaries
     */
    protected function handleOrderDateChange(Sale $sale): void
    {
        $originalDate = $sale->getOriginal('order_date');
        if ($originalDate && $originalDate !== $sale->order_date) {
            // Create a temporary sale object with original data for cleanup
            $tempSale = $sale->replicate();
            $tempSale->order_date = $originalDate;
            $tempSale->total = $sale->getOriginal('total');
            $tempSale->cogs = $sale->getOriginal('cogs');
            $tempSale->profit = $sale->getOriginal('profit');
            $tempSale->status = $sale->getOriginal('status');
            $tempSale->payment_method = $sale->getOriginal('payment_method');
            $tempSale->customer_type = $sale->getOriginal('customer_type');
            
            $this->summaryService->updateSummariesForSale($tempSale, 'deleted');
            
            Log::info('Updated summaries for old order date', [
                'sale_id' => $sale->id,
                'old_date' => $originalDate,
                'new_date' => $sale->order_date
            ]);
        }
    }

    /**
     * Handle status changes - might need special logic for cancelled/returned sales
     */
    protected function handleStatusChange(Sale $sale): void
    {
        $oldStatus = $sale->getOriginal('status');
        $newStatus = $sale->status;
        
        // Log significant status changes
        if (in_array($oldStatus, [Sale::STATUS_PENDING, Sale::STATUS_COMPLETED]) && 
            $newStatus === Sale::STATUS_CANCELLED) {
            Log::info('Sale cancelled - summaries will be updated', [
                'sale_id' => $sale->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'total_amount' => $sale->total
            ]);
        }
        
        if ($oldStatus === Sale::STATUS_CANCELLED && 
            in_array($newStatus, [Sale::STATUS_PENDING, Sale::STATUS_COMPLETED])) {
            Log::info('Cancelled sale reactivated - summaries will be updated', [
                'sale_id' => $sale->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'total_amount' => $sale->total
            ]);
        }
    }

    /**
     * Get original values for changed fields
     */
    protected function getOriginalValues(Sale $sale, array $fields): array
    {
        $originalValues = [];
        foreach ($fields as $field) {
            $originalValues[$field] = $sale->getOriginal($field);
        }
        return $originalValues;
    }

    /**
     * Check if the change affects financial calculations
     */
    protected function affectsFinancialCalculations(Sale $sale): bool
    {
        // More granular check - only update if financial impact exists
        if ($sale->wasChanged(['total', 'cogs', 'profit'])) {
            return true;
        }

        // Status changes that affect whether sale counts in summaries
        if ($sale->wasChanged('status')) {
            $oldStatus = $sale->getOriginal('status');
            $newStatus = $sale->status;
            
            $excludedStatuses = [Sale::STATUS_CANCELLED];
            $wasExcluded = in_array($oldStatus, $excludedStatuses);
            $isExcluded = in_array($newStatus, $excludedStatuses);
            
            return $wasExcluded !== $isExcluded;
        }

        // Date changes affect which summary period to update
        if ($sale->wasChanged('order_date')) {
            return true;
        }

        // Categorization changes affect breakdown summaries
        if ($sale->wasChanged(['payment_method', 'customer_type'])) {
            return true;
        }

        return false;
    }
}