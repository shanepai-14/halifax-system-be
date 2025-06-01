<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SalesSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class SalesSummaryService
{
    /**
     * Update summaries when a sale is created, updated, or deleted
     *
     * @param Sale $sale
     * @param string $action (created, updated, deleted)
     * @return void
     */
    public function updateSummariesForSale(Sale $sale, string $action = 'created')
    {
        try {
            DB::beginTransaction();

            $orderDate = Carbon::parse($sale->order_date);
            
            // Update daily summary
            $this->updateDailySummary($orderDate, $action === 'deleted' ? null : $sale);
            
            // Update monthly summary
            $this->updateMonthlySummary($orderDate, $action === 'deleted' ? null : $sale);
            
            // Update yearly summary
            $this->updateYearlySummary($orderDate, $action === 'deleted' ? null : $sale);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update sales summaries', [
                'sale_id' => $sale->id,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update daily summary for a specific date
     */
    protected function updateDailySummary(Carbon $date, ?Sale $sale = null)
    {
        $this->updateSummaryForPeriod(
            SalesSummary::PERIOD_DAILY,
            $date->startOfDay(),
            $date->year,
            $date->month,
            $date->day,
            $sale
        );
    }

    /**
     * Update monthly summary for a specific month
     */
    protected function updateMonthlySummary(Carbon $date, ?Sale $sale = null)
    {
        $this->updateSummaryForPeriod(
            SalesSummary::PERIOD_MONTHLY,
            $date->startOfMonth(),
            $date->year,
            $date->month,
            null,
            $sale
        );
    }

    /**
     * Update yearly summary for a specific year
     */
    protected function updateYearlySummary(Carbon $date, ?Sale $sale = null)
    {
        $this->updateSummaryForPeriod(
            SalesSummary::PERIOD_YEARLY,
            $date->startOfYear(),
            $date->year,
            null,
            null,
            $sale
        );
    }

    /**
     * Update summary for a specific period
     */
    protected function updateSummaryForPeriod(
        string $periodType,
        Carbon $periodDate,
        int $year,
        ?int $month,
        ?int $day,
        ?Sale $sale = null
    ) {
        // Build the date filter based on period type
        $dateFilter = $this->buildDateFilter($periodType, $year, $month, $day);
        
        // Get aggregated data from sales table
        $aggregatedData = Sale::select([
                DB::raw('COUNT(*) as total_sales_count'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_sales_count'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_sales_count'),
                DB::raw('SUM(CASE WHEN status = "returned" THEN 1 ELSE 0 END) as returned_sales_count'),
                DB::raw('SUM(CASE WHEN status != "cancelled" THEN total ELSE 0 END) as total_revenue'),
                DB::raw('SUM(CASE WHEN status != "cancelled" THEN cogs ELSE 0 END) as total_cogs'),
                DB::raw('SUM(CASE WHEN status != "cancelled" THEN profit ELSE 0 END) as total_profit'),
                DB::raw('AVG(CASE WHEN status != "cancelled" THEN total ELSE NULL END) as avg_sale_value')
            ])
            ->where($dateFilter)
            ->first();

        // Get payment methods breakdown
        $paymentMethodsBreakdown = Sale::select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->where($dateFilter)
            ->where('status', '!=', 'cancelled')
            ->groupBy('payment_method')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_method => [
                    'count' => $item->count,
                    'total' => $item->total
                ]];
            })->toArray();

        // Get customer types breakdown
        $customerTypesBreakdown = Sale::select('customer_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->where($dateFilter)
            ->where('status', '!=', 'cancelled')
            ->groupBy('customer_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->customer_type => [
                    'count' => $item->count,
                    'total' => $item->total
                ]];
            })->toArray();

        // Calculate profit margin
        $avgProfitMargin = $aggregatedData->total_revenue > 0 
            ? ($aggregatedData->total_profit / $aggregatedData->total_revenue) * 100 
            : 0;

        // Update or create summary record
        SalesSummary::updateOrCreate(
            [
                'period_type' => $periodType,
                'period_date' => $periodDate
            ],
            [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'total_revenue' => $aggregatedData->total_revenue ?? 0,
                'total_cogs' => $aggregatedData->total_cogs ?? 0,
                'total_profit' => $aggregatedData->total_profit ?? 0,
                'total_sales_count' => $aggregatedData->total_sales_count ?? 0,
                'completed_sales_count' => $aggregatedData->completed_sales_count ?? 0,
                'cancelled_sales_count' => $aggregatedData->cancelled_sales_count ?? 0,
                'returned_sales_count' => $aggregatedData->returned_sales_count ?? 0,
                'payment_methods_breakdown' => $paymentMethodsBreakdown,
                'customer_types_breakdown' => $customerTypesBreakdown,
                'avg_sale_value' => $aggregatedData->avg_sale_value ?? 0,
                'avg_profit_margin' => $avgProfitMargin,
                'last_updated_at' => now(),
                'last_sale_id' => $sale?->id
            ]
        );
    }

    /**
     * Build date filter for different period types
     */
    protected function buildDateFilter(string $periodType, int $year, ?int $month, ?int $day): callable
    {
        return function ($query) use ($periodType, $year, $month, $day) {
            switch ($periodType) {
                case SalesSummary::PERIOD_DAILY:
                    $query->whereDate('order_date', Carbon::create($year, $month, $day)->toDateString());
                    break;
                    
                case SalesSummary::PERIOD_MONTHLY:
                    $query->whereYear('order_date', $year)
                          ->whereMonth('order_date', $month);
                    break;
                    
                case SalesSummary::PERIOD_YEARLY:
                    $query->whereYear('order_date', $year);
                    break;
            }
        };
    }

    /**
     * Rebuild all summaries (useful for initial setup or data correction)
     */
    public function rebuildAllSummaries()
    {
        try {
            DB::beginTransaction();

            // Clear existing summaries
            // SalesSummary::truncate();

            // Get date range of all sales
            $dateRange = Sale::selectRaw('MIN(order_date) as min_date, MAX(order_date) as max_date')->first();
            
            if (!$dateRange->min_date) {
                Log::info('No sales found, skipping summary rebuild');
                DB::commit();
                return;
            }

            $startDate = Carbon::parse($dateRange->min_date);
            $endDate = Carbon::parse($dateRange->max_date);

            // Rebuild yearly summaries
            for ($year = $startDate->year; $year <= $endDate->year; $year++) {
                $this->updateYearlySummary(Carbon::create($year, 1, 1));
            }

            // Rebuild monthly summaries
            $currentMonth = $startDate->copy()->startOfMonth();
            while ($currentMonth <= $endDate->endOfMonth()) {
                $this->updateMonthlySummary($currentMonth);
                $currentMonth->addMonth();
            }

            // Rebuild daily summaries
            $currentDay = $startDate->copy()->startOfDay();
            while ($currentDay <= $endDate->endOfDay()) {
                $this->updateDailySummary($currentDay);
                $currentDay->addDay();
            }

            DB::commit();
            Log::info('Sales summaries rebuilt successfully', [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString()
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to rebuild sales summaries', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get chart data for a specific period
     */
    public function getChartData(string $periodType, array $filters = [])
    {
        switch ($periodType) {
            case SalesSummary::PERIOD_MONTHLY:
                $year = $filters['year'] ?? now()->year;
                return SalesSummary::getMonthlyChartData($year);
                
            case SalesSummary::PERIOD_YEARLY:
                $startYear = $filters['start_year'] ?? now()->year - 4;
                $endYear = $filters['end_year'] ?? now()->year;
                return SalesSummary::getYearlyChartData($startYear, $endYear);
                
            case SalesSummary::PERIOD_DAILY:
                $year = $filters['year'] ?? now()->year;
                $month = $filters['month'] ?? now()->month;
                return $this->getDailyChartData($year, $month);
                
            default:
                throw new Exception("Unsupported period type: {$periodType}");
        }
    }

    /**
     * Get daily chart data for a specific month
     */
    protected function getDailyChartData(int $year, int $month)
    {
        $dailySummaries = SalesSummary::getDailyForMonth($year, $month);
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        
        $chartData = [
            'labels' => [],
            'revenue' => [],
            'cogs' => [],
            'profit' => []
        ];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $summary = $dailySummaries->firstWhere('day', $day);
            
            $chartData['labels'][] = $day;
            $chartData['revenue'][] = $summary ? (float) $summary->total_revenue : 0;
            $chartData['cogs'][] = $summary ? (float) $summary->total_cogs : 0;
            $chartData['profit'][] = $summary ? (float) $summary->total_profit : 0;
        }

        return $chartData;
    }
}