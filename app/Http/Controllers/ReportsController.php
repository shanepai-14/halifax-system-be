<?php

namespace App\Http\Controllers;

use App\Models\SalesSummary;
use App\Services\SalesSummaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Exception;

class ReportsController extends Controller
{
    protected $summaryService;

    public function __construct(SalesSummaryService $summaryService)
    {
        $this->summaryService = $summaryService;
    }

    /**
     * Get sales summary dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);

            // Get current month summary
            $currentMonth = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
                ->byYear($year)
                ->byMonth($month)
                ->first();

            // Get current year summary
            $currentYear = SalesSummary::byPeriodType(SalesSummary::PERIOD_YEARLY)
                ->byYear($year)
                ->first();

            // Get last 12 months for trend
            $last12Months = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
                ->where('period_date', '>=', now()->subMonths(11)->startOfMonth())
                ->orderBy('period_date')
                ->get();

            // Get yesterday and today summaries for daily comparison
            $yesterday = SalesSummary::byPeriodType(SalesSummary::PERIOD_DAILY)
                ->where('period_date', now()->subDay()->toDateString())
                ->first();

            $today = SalesSummary::byPeriodType(SalesSummary::PERIOD_DAILY)
                ->where('period_date', now()->toDateString())
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'current_month' => $currentMonth,
                    'current_year' => $currentYear,
                    'last_12_months' => $last12Months,
                    'yesterday' => $yesterday,
                    'today' => $today,
                    'trends' => [
                        'monthly_revenue_trend' => $last12Months->pluck('total_revenue'),
                        'monthly_profit_trend' => $last12Months->pluck('total_profit'),
                        'monthly_labels' => $last12Months->map(function ($summary) {
                            return Carbon::parse($summary->period_date)->format('M Y');
                        })
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chart data for profit reports
     */
    public function getChartData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'year' => 'nullable|integer|min:2020|max:2030',
                'month' => 'nullable|integer|min:1|max:12',
                'start_year' => 'nullable|integer|min:2020|max:2030',
                'end_year' => 'nullable|integer|min:2020|max:2030'
            ]);

            $chartData = $this->summaryService->getChartData(
                $validated['period_type'],
                $validated
            );

            return response()->json([
                'status' => 'success',
                'data' => $chartData
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving chart data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get monthly summaries for a specific year
     */
    public function getMonthlyReport(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $monthlySummaries = SalesSummary::getMonthlyForYear($year);

            return response()->json([
                'status' => 'success',
                'data' => $monthlySummaries
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving monthly report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get yearly summaries for a range
     */
    public function getYearlyReport(Request $request): JsonResponse
    {
        try {
            $startYear = $request->input('start_year', now()->year - 4);
            $endYear = $request->input('end_year', now()->year);
            
            $yearlySummaries = SalesSummary::getYearlyRange($startYear, $endYear);

            return response()->json([
                'status' => 'success',
                'data' => $yearlySummaries
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving yearly report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily summaries for a specific month
     */
    public function getDailyReport(Request $request): JsonResponse
    {
        try {
            $year = $request->input('year', now()->year);
            $month = $request->input('month', now()->month);
            
            $dailySummaries = SalesSummary::getDailyForMonth($year, $month);

            return response()->json([
                'status' => 'success',
                'data' => $dailySummaries
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving daily report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment methods breakdown
     */
    public function getPaymentMethodsBreakdown(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|min:1|max:12',
                'day' => 'nullable|integer|min:1|max:31'
            ]);

            $query = SalesSummary::byPeriodType($validated['period_type']);

            if (isset($validated['year'])) {
                $query->byYear($validated['year']);
            }

            if (isset($validated['month'])) {
                $query->byMonth($validated['month']);
            }

            if (isset($validated['day'])) {
                $query->where('day', $validated['day']);
            }

            $summaries = $query->get();
            
            // Aggregate payment methods data
            $paymentMethodsTotal = [];
            foreach ($summaries as $summary) {
                if ($summary->payment_methods_breakdown) {
                    foreach ($summary->payment_methods_breakdown as $method => $data) {
                        if (!isset($paymentMethodsTotal[$method])) {
                            $paymentMethodsTotal[$method] = ['count' => 0, 'total' => 0];
                        }
                        $paymentMethodsTotal[$method]['count'] += $data['count'];
                        $paymentMethodsTotal[$method]['total'] += $data['total'];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $paymentMethodsTotal
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving payment methods breakdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer types breakdown
     */
    public function getCustomerTypesBreakdown(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|min:1|max:12'
            ]);

            $query = SalesSummary::byPeriodType($validated['period_type']);

            if (isset($validated['year'])) {
                $query->byYear($validated['year']);
            }

            if (isset($validated['month'])) {
                $query->byMonth($validated['month']);
            }

            $summaries = $query->get();
            
            // Aggregate customer types data
            $customerTypesTotal = [];
            foreach ($summaries as $summary) {
                if ($summary->customer_types_breakdown) {
                    foreach ($summary->customer_types_breakdown as $type => $data) {
                        if (!isset($customerTypesTotal[$type])) {
                            $customerTypesTotal[$type] = ['count' => 0, 'total' => 0];
                        }
                        $customerTypesTotal[$type]['count'] += $data['count'];
                        $customerTypesTotal[$type]['total'] += $data['total'];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $customerTypesTotal
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving customer types breakdown',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Rebuild all summaries (admin function)
     */
    public function rebuildSummaries(): JsonResponse
    {
        try {
            $this->summaryService->rebuildAllSummaries();

            return response()->json([
                'status' => 'success',
                'message' => 'Sales summaries rebuilt successfully'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error rebuilding summaries',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get profit trends comparison
     */
    public function getProfitTrends(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'compare_type' => 'required|in:month_over_month,year_over_year,quarter_over_quarter',
                'year' => 'nullable|integer',
                'periods' => 'nullable|integer|min:1|max:24'
            ]);

            $year = $validated['year'] ?? now()->year;
            $periods = $validated['periods'] ?? 12;

            switch ($validated['compare_type']) {
                case 'month_over_month':
                    $data = $this->getMonthOverMonthTrends($year, $periods);
                    break;
                    
                case 'year_over_year':
                    $data = $this->getYearOverYearTrends($year, $periods);
                    break;
                    
                case 'quarter_over_quarter':
                    $data = $this->getQuarterOverQuarterTrends($year, $periods);
                    break;
                    
                default:
                    throw new Exception('Invalid compare_type');
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving profit trends',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get month-over-month trends
     */
    protected function getMonthOverMonthTrends(int $year, int $periods): array
    {
        $startDate = Carbon::create($year, 1, 1)->subMonths($periods - 1);
        $endDate = Carbon::create($year, 12, 31);

        $summaries = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
            ->whereBetween('period_date', [$startDate, $endDate])
            ->orderBy('period_date')
            ->get();

        $trends = [];
        $previousSummary = null;

        foreach ($summaries as $summary) {
            $trend = [
                'period' => $summary->period_date->format('M Y'),
                'revenue' => (float) $summary->total_revenue,
                'profit' => (float) $summary->total_profit,
                'cogs' => (float) $summary->total_cogs,
                'sales_count' => $summary->total_sales_count
            ];

            if ($previousSummary) {
                $trend['revenue_growth'] = $previousSummary->total_revenue > 0 
                    ? (($summary->total_revenue - $previousSummary->total_revenue) / $previousSummary->total_revenue) * 100 
                    : 0;
                    
                $trend['profit_growth'] = $previousSummary->total_profit > 0 
                    ? (($summary->total_profit - $previousSummary->total_profit) / $previousSummary->total_profit) * 100 
                    : 0;
            }

            $trends[] = $trend;
            $previousSummary = $summary;
        }

        return $trends;
    }

    /**
     * Get year-over-year trends
     */
    protected function getYearOverYearTrends(int $currentYear, int $periods): array
    {
        $startYear = $currentYear - $periods + 1;
        
        $summaries = SalesSummary::byPeriodType(SalesSummary::PERIOD_YEARLY)
            ->whereBetween('year', [$startYear, $currentYear])
            ->orderBy('year')
            ->get();

        $trends = [];
        $previousSummary = null;

        foreach ($summaries as $summary) {
            $trend = [
                'period' => $summary->year,
                'revenue' => (float) $summary->total_revenue,
                'profit' => (float) $summary->total_profit,
                'cogs' => (float) $summary->total_cogs,
                'sales_count' => $summary->total_sales_count
            ];

            if ($previousSummary) {
                $trend['revenue_growth'] = $previousSummary->total_revenue > 0 
                    ? (($summary->total_revenue - $previousSummary->total_revenue) / $previousSummary->total_revenue) * 100 
                    : 0;
                    
                $trend['profit_growth'] = $previousSummary->total_profit > 0 
                    ? (($summary->total_profit - $previousSummary->total_profit) / $previousSummary->total_profit) * 100 
                    : 0;
            }

            $trends[] = $trend;
            $previousSummary = $summary;
        }

        return $trends;
    }

    /**
     * Get quarter-over-quarter trends
     */
    protected function getQuarterOverQuarterTrends(int $year, int $periods): array
    {
        // Implementation for quarterly trends would aggregate monthly data into quarters
        // This is a simplified version - you might want to create separate quarterly summaries
        $monthlySummaries = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
            ->byYear($year)
            ->orderBy('month')
            ->get();

        $quarters = [
            'Q1' => [1, 2, 3],
            'Q2' => [4, 5, 6],
            'Q3' => [7, 8, 9],
            'Q4' => [10, 11, 12]
        ];

        $trends = [];
        foreach ($quarters as $quarter => $months) {
            $quarterSummaries = $monthlySummaries->whereIn('month', $months);
            
            $trends[] = [
                'period' => "$quarter $year",
                'revenue' => $quarterSummaries->sum('total_revenue'),
                'profit' => $quarterSummaries->sum('total_profit'),
                'cogs' => $quarterSummaries->sum('total_cogs'),
                'sales_count' => $quarterSummaries->sum('total_sales_count')
            ];
        }

        return $trends;
    }

    /**
     * Get sales performance metrics
     */
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|min:1|max:12',
                'compare_previous' => 'nullable|boolean'
            ]);

            $query = SalesSummary::byPeriodType($validated['period_type']);

            if (isset($validated['year'])) {
                $query->byYear($validated['year']);
            }

            if (isset($validated['month'])) {
                $query->byMonth($validated['month']);
            }

            $currentPeriod = $query->first();
            $metrics = [
                'current_period' => $currentPeriod,
                'comparison' => null
            ];

            // Add comparison with previous period if requested
            if ($validated['compare_previous'] ?? false) {
                $metrics['comparison'] = $this->getPreviousPeriodComparison(
                    $validated['period_type'],
                    $validated['year'] ?? now()->year,
                    $validated['month'] ?? null
                );
            }

            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comparison with previous period
     */
    protected function getPreviousPeriodComparison(string $periodType, int $year, ?int $month = null): ?array
    {
        $previousQuery = SalesSummary::byPeriodType($periodType);

        switch ($periodType) {
            case SalesSummary::PERIOD_YEARLY:
                $previousQuery->byYear($year - 1);
                break;
                
            case SalesSummary::PERIOD_MONTHLY:
                if ($month === 1) {
                    $previousQuery->byYear($year - 1)->byMonth(12);
                } else {
                    $previousQuery->byYear($year)->byMonth($month - 1);
                }
                break;
                
            case SalesSummary::PERIOD_DAILY:
                // For daily, we'd need the specific day - simplified for now
                return null;
        }

        $previousPeriod = $previousQuery->first();
        
        if (!$previousPeriod) {
            return null;
        }

        return [
            'previous_period' => $previousPeriod,
            'revenue_change' => $this->calculatePercentageChange(
                $previousPeriod->total_revenue, 
                request()->input('current_revenue', 0)
            ),
            'profit_change' => $this->calculatePercentageChange(
                $previousPeriod->total_profit, 
                request()->input('current_profit', 0)
            )
        ];
    }

    /**
     * Calculate percentage change between two values
     */
    protected function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }
        
        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Export summary data to Excel/CSV
     */
    public function exportSummaryData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'year' => 'nullable|integer',
                'month' => 'nullable|integer|min:1|max:12',
                'format' => 'nullable|in:csv,excel',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date'
            ]);

            $query = SalesSummary::byPeriodType($validated['period_type']);

            // Apply filters
            if (isset($validated['year'])) {
                $query->byYear($validated['year']);
            }

            if (isset($validated['month'])) {
                $query->byMonth($validated['month']);
            }

            if (isset($validated['start_date']) && isset($validated['end_date'])) {
                $query->byDateRange($validated['start_date'], $validated['end_date']);
            }

            $summaries = $query->orderBy('period_date')->get();

            // Format data for export
            $exportData = $summaries->map(function ($summary) {
                return [
                    'Period' => $summary->period_identifier,
                    'Total Revenue' => number_format($summary->total_revenue, 2),
                    'Total COGS' => number_format($summary->total_cogs, 2),
                    'Total Profit' => number_format($summary->total_profit, 2),
                    'Profit Margin %' => number_format($summary->profit_margin_percentage, 2),
                    'Sales Count' => $summary->total_sales_count,
                    'Completed Sales' => $summary->completed_sales_count,
                    'Cancelled Sales' => $summary->cancelled_sales_count,
                    'Average Sale Value' => number_format($summary->avg_sale_value, 2),
                    'Last Updated' => $summary->last_updated_at ? $summary->last_updated_at->format('Y-m-d H:i:s') : ''
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $exportData,
                'meta' => [
                    'total_records' => $exportData->count(),
                    'period_type' => $validated['period_type'],
                    'export_format' => $validated['format'] ?? 'json'
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error exporting summary data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top performing periods
     */
    public function getTopPerformingPeriods(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:daily,monthly,yearly',
                'metric' => 'required|in:revenue,profit,sales_count,profit_margin',
                'limit' => 'nullable|integer|min:1|max:50',
                'year' => 'nullable|integer',
                'order' => 'nullable|in:asc,desc'
            ]);

            $query = SalesSummary::byPeriodType($validated['period_type']);

            if (isset($validated['year'])) {
                $query->byYear($validated['year']);
            }

            // Order by selected metric
            $orderBy = match($validated['metric']) {
                'revenue' => 'total_revenue',
                'profit' => 'total_profit',
                'sales_count' => 'total_sales_count',
                'profit_margin' => 'avg_profit_margin',
                default => 'total_revenue'
            };

            $order = $validated['order'] ?? 'desc';
            $limit = $validated['limit'] ?? 10;

            $topPeriods = $query->orderBy($orderBy, $order)
                               ->limit($limit)
                               ->get();

            return response()->json([
                'status' => 'success',
                'data' => $topPeriods,
                'meta' => [
                    'metric' => $validated['metric'],
                    'order' => $order,
                    'limit' => $limit
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving top performing periods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics for a date range
     */
    public function getSummaryStatistics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'period_type' => 'nullable|in:daily,monthly,yearly'
            ]);

            $periodType = $validated['period_type'] ?? SalesSummary::PERIOD_DAILY;
            
            $summaries = SalesSummary::byPeriodType($periodType)
                ->byDateRange($validated['start_date'], $validated['end_date'])
                ->get();

            if ($summaries->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'total_revenue' => 0,
                        'total_cogs' => 0,
                        'total_profit' => 0,
                        'avg_profit_margin' => 0,
                        'total_sales' => 0,
                        'periods_count' => 0,
                        'best_performing_period' => null,
                        'worst_performing_period' => null
                    ]
                ]);
            }

            $totalRevenue = $summaries->sum('total_revenue');
            $totalCogs = $summaries->sum('total_cogs');
            $totalProfit = $summaries->sum('total_profit');
            $totalSales = $summaries->sum('total_sales_count');

            $avgProfitMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

            $bestPeriod = $summaries->sortByDesc('total_profit')->first();
            $worstPeriod = $summaries->sortBy('total_profit')->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'total_revenue' => $totalRevenue,
                    'total_cogs' => $totalCogs,
                    'total_profit' => $totalProfit,
                    'avg_profit_margin' => round($avgProfitMargin, 2),
                    'total_sales' => $totalSales,
                    'periods_count' => $summaries->count(),
                    'avg_revenue_per_period' => round($totalRevenue / $summaries->count(), 2),
                    'avg_profit_per_period' => round($totalProfit / $summaries->count(), 2),
                    'best_performing_period' => [
                        'period' => $bestPeriod->period_identifier,
                        'profit' => $bestPeriod->total_profit,
                        'revenue' => $bestPeriod->total_revenue
                    ],
                    'worst_performing_period' => [
                        'period' => $worstPeriod->period_identifier,
                        'profit' => $worstPeriod->total_profit,
                        'revenue' => $worstPeriod->total_revenue
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error calculating summary statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get forecast data based on historical trends
     */
    public function getForecastData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'period_type' => 'required|in:monthly,yearly',
                'periods_ahead' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer'
            ]);

            $periodsAhead = $validated['periods_ahead'] ?? 3;
            $year = $validated['year'] ?? now()->year;

            // Get historical data for trend analysis
            $historicalData = SalesSummary::byPeriodType($validated['period_type'])
                ->where('period_date', '<=', now())
                ->orderBy('period_date', 'desc')
                ->limit(12) // Use last 12 periods for trend
                ->get()
                ->reverse();

            if ($historicalData->count() < 3) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient historical data for forecasting (minimum 3 periods required)'
                ], 400);
            }

            // Simple linear trend calculation
            $forecast = $this->calculateLinearForecast($historicalData, $periodsAhead, $validated['period_type']);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'historical_data' => $historicalData,
                    'forecast' => $forecast,
                    'meta' => [
                        'periods_used_for_trend' => $historicalData->count(),
                        'periods_forecasted' => $periodsAhead,
                        'forecast_method' => 'linear_trend'
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error generating forecast data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate linear forecast based on historical data
     */
    protected function calculateLinearForecast($historicalData, int $periodsAhead, string $periodType): array
    {
        $dataPoints = $historicalData->count();
        $forecast = [];

        // Calculate trend for revenue, profit, and sales count
        $metrics = ['total_revenue', 'total_profit', 'total_sales_count'];
        $trends = [];

        foreach ($metrics as $metric) {
            $values = $historicalData->pluck($metric)->toArray();
            $trends[$metric] = $this->calculateLinearTrend($values);
        }

        // Generate forecast periods
        $lastPeriod = $historicalData->last();
        $lastDate = Carbon::parse($lastPeriod->period_date);

        for ($i = 1; $i <= $periodsAhead; $i++) {
            $forecastDate = $periodType === 'monthly' 
                ? $lastDate->copy()->addMonths($i)
                : $lastDate->copy()->addYears($i);

            $forecastRevenue = max(0, $trends['total_revenue']['intercept'] + ($trends['total_revenue']['slope'] * ($dataPoints + $i)));
            $forecastProfit = max(0, $trends['total_profit']['intercept'] + ($trends['total_profit']['slope'] * ($dataPoints + $i)));
            $forecastSales = max(0, $trends['total_sales_count']['intercept'] + ($trends['total_sales_count']['slope'] * ($dataPoints + $i)));

            $forecast[] = [
                'period_date' => $forecastDate->format('Y-m-d'),
                'period_identifier' => $periodType === 'monthly' 
                    ? $forecastDate->format('Y-m') 
                    : $forecastDate->format('Y'),
                'forecasted_revenue' => round($forecastRevenue, 2),
                'forecasted_profit' => round($forecastProfit, 2),
                'forecasted_sales_count' => round($forecastSales),
                'forecasted_profit_margin' => $forecastRevenue > 0 ? round(($forecastProfit / $forecastRevenue) * 100, 2) : 0,
                'confidence_level' => max(0.5, 1 - (0.1 * $i)) // Decreasing confidence over time
            ];
        }

        return $forecast;
    }

    /**
     * Calculate linear trend (slope and intercept) for a dataset
     */
    protected function calculateLinearTrend(array $values): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $values[0] ?? 0];
        }

        $sumX = $sumY = $sumXY = $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1; // Period number
            $y = $values[$i];
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [
            'slope' => $slope,
            'intercept' => $intercept
        ];
    }

    /**
     * Get advanced analytics dashboard
     */
    public function getAdvancedAnalytics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'year' => 'nullable|integer',
                'include_forecasts' => 'nullable|boolean',
                'include_comparisons' => 'nullable|boolean'
            ]);

            $year = $validated['year'] ?? now()->year;
            $includeForecast = $validated['include_forecasts'] ?? false;
            $includeComparisons = $validated['include_comparisons'] ?? false;

            // Get current year data
            $currentYearData = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
                ->byYear($year)
                ->orderBy('month')
                ->get();

            // Get previous year for comparison
            $previousYearData = [];
            if ($includeComparisons) {
                $previousYearData = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
                    ->byYear($year - 1)
                    ->orderBy('month')
                    ->get();
            }

            // Generate forecasts
            $forecasts = [];
            if ($includeForecast && $currentYearData->count() >= 3) {
                $forecastRequest = new Request(['period_type' => 'monthly', 'periods_ahead' => 3]);
                $forecastResponse = $this->getForecastData($forecastRequest);
                $forecasts = $forecastResponse->getData()->data->forecast ?? [];
            }

            // Calculate key metrics
            $analytics = [
                'current_year' => [
                    'data' => $currentYearData,
                    'totals' => [
                        'revenue' => $currentYearData->sum('total_revenue'),
                        'profit' => $currentYearData->sum('total_profit'),
                        'cogs' => $currentYearData->sum('total_cogs'),
                        'sales_count' => $currentYearData->sum('total_sales_count'),
                        'avg_margin' => $this->calculateAverageMargin($currentYearData)
                    ]
                ],
                'previous_year' => [
                    'data' => $previousYearData,
                    'totals' => $includeComparisons ? [
                        'revenue' => collect($previousYearData)->sum('total_revenue'),
                        'profit' => collect($previousYearData)->sum('total_profit'),
                        'cogs' => collect($previousYearData)->sum('total_cogs'),
                        'sales_count' => collect($previousYearData)->sum('total_sales_count'),
                        'avg_margin' => $this->calculateAverageMargin(collect($previousYearData))
                    ] : null
                ],
                'forecasts' => $forecasts,
                'insights' => $this->generateInsights($currentYearData, $previousYearData),
                'seasonality' => $this->analyzeSeasonality($currentYearData)
            ];

            return response()->json([
                'status' => 'success',
                'data' => $analytics
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error retrieving advanced analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate average profit margin for a collection of summaries
     */
    protected function calculateAverageMargin($summaries): float
    {
        if (is_array($summaries)) {
            $summaries = collect($summaries);
        }

        $totalRevenue = $summaries->sum('total_revenue');
        $totalProfit = $summaries->sum('total_profit');

        return $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 2) : 0;
    }

    /**
     * Generate business insights from data
     */
    protected function generateInsights($currentData, $previousData): array
    {
        $insights = [];

        // Revenue growth insight
        if (!empty($previousData) && count($previousData) > 0) {
            $currentRevenue = $currentData->sum('total_revenue');
            $previousRevenue = collect($previousData)->sum('total_revenue');
            
            if ($previousRevenue > 0) {
                $revenueGrowth = (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
                $insights[] = [
                    'type' => 'revenue_growth',
                    'message' => $revenueGrowth > 0 
                        ? "Revenue has grown by " . round($revenueGrowth, 1) . "% compared to last year"
                        : "Revenue has declined by " . round(abs($revenueGrowth), 1) . "% compared to last year",
                    'value' => round($revenueGrowth, 2),
                    'trend' => $revenueGrowth > 0 ? 'positive' : 'negative'
                ];
            }
        }

        // Best performing month
        $bestMonth = $currentData->sortByDesc('total_profit')->first();
        if ($bestMonth) {
            $insights[] = [
                'type' => 'best_month',
                'message' => "Best performing month was " . Carbon::create(null, $bestMonth->month)->format('F') . 
                           " with ₱" . number_format($bestMonth->total_profit, 2) . " profit",
                'month' => $bestMonth->month,
                'profit' => $bestMonth->total_profit
            ];
        }

        // Consistency insight
        $profitVariance = $this->calculateVariance($currentData->pluck('total_profit')->toArray());
        $insights[] = [
            'type' => 'consistency',
            'message' => $profitVariance < 1000000 
                ? "Profit margins are relatively consistent month-to-month"
                : "Profit shows significant variation across months",
            'variance' => $profitVariance,
            'consistency_level' => $profitVariance < 1000000 ? 'high' : 'low'
        ];

        return $insights;
    }

    /**
     * Analyze seasonality patterns
     */
    protected function analyzeSeasonality($currentData): array
    {
        if ($currentData->count() < 6) {
            return ['message' => 'Insufficient data for seasonality analysis'];
        }

        $monthlyAverage = $currentData->avg('total_revenue');
        $seasonality = [];

        foreach ($currentData as $summary) {
            $seasonalIndex = $monthlyAverage > 0 ? ($summary->total_revenue / $monthlyAverage) : 1;
            
            $seasonality[] = [
                'month' => $summary->month,
                'month_name' => Carbon::create(null, $summary->month)->format('F'),
                'seasonal_index' => round($seasonalIndex, 2),
                'performance' => $seasonalIndex > 1.1 ? 'high' : ($seasonalIndex < 0.9 ? 'low' : 'average')
            ];
        }

        // Identify peak and low seasons
        $peakMonth = collect($seasonality)->sortByDesc('seasonal_index')->first();
        $lowMonth = collect($seasonality)->sortBy('seasonal_index')->first();

        return [
            'monthly_patterns' => $seasonality,
            'peak_season' => $peakMonth,
            'low_season' => $lowMonth,
            'seasonality_strength' => $this->calculateSeasonalityStrength($seasonality)
        ];
    }

    /**
     * Calculate seasonality strength
     */
    protected function calculateSeasonalityStrength($seasonality): string
    {
        $indices = collect($seasonality)->pluck('seasonal_index');
        $variance = $this->calculateVariance($indices->toArray());
        
        if ($variance > 0.5) {
            return 'high';
        } elseif ($variance > 0.2) {
            return 'moderate';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate variance of an array
     */
    protected function calculateVariance(array $values): float
    {
        if (count($values) < 2) return 0;
        
        $mean = array_sum($values) / count($values);
        $squaredDifferences = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values);
        
        return array_sum($squaredDifferences) / (count($values) - 1);
    }

    /**
     * Get health check for summaries data
     */
    public function getHealthCheck(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'issues' => [],
                'summary_counts' => [
                    'daily' => SalesSummary::byPeriodType(SalesSummary::PERIOD_DAILY)->count(),
                    'monthly' => SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)->count(),
                    'yearly' => SalesSummary::byPeriodType(SalesSummary::PERIOD_YEARLY)->count()
                ],
                'last_updated' => SalesSummary::max('last_updated_at'),
                'data_coverage' => []
            ];

            // Check for missing summaries
            $currentYear = now()->year;
            $currentMonth = now()->month;

            // Check current year monthly coverage
            $monthlyCount = SalesSummary::byPeriodType(SalesSummary::PERIOD_MONTHLY)
                ->byYear($currentYear)
                ->count();

            if ($monthlyCount < $currentMonth) {
                $health['issues'][] = "Missing monthly summaries for current year (expected: {$currentMonth}, found: {$monthlyCount})";
                $health['status'] = 'warning';
            }

            // Check for stale data (last update older than 24 hours)
            $lastUpdate = Carbon::parse($health['last_updated']);
            if ($lastUpdate->diffInHours(now()) > 24) {
                $health['issues'][] = "Summary data appears stale (last updated: {$lastUpdate->diffForHumans()})";
                $health['status'] = 'warning';
            }

            // Calculate data coverage percentage
            $expectedMonths = ($currentYear - 2020) * 12 + $currentMonth; // Assuming system started in 2020
            $actualMonths = $health['summary_counts']['monthly'];
            $coverage = $expectedMonths > 0 ? round(($actualMonths / $expectedMonths) * 100, 1) : 100;
            
            $health['data_coverage'] = [
                'percentage' => $coverage,
                'expected_months' => $expectedMonths,
                'actual_months' => $actualMonths
            ];

            if ($coverage < 80) {
                $health['issues'][] = "Low data coverage: {$coverage}% of expected monthly summaries";
                $health['status'] = 'error';
            }

            return response()->json([
                'status' => 'success',
                'data' => $health
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error performing health check',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}