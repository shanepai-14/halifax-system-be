<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SalesSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_type',
        'period_date',
        'year',
        'month',
        'day',
        'total_revenue',
        'total_cogs',
        'total_profit',
        'total_sales_count',
        'completed_sales_count',
        'cancelled_sales_count',
        'returned_sales_count',
        'payment_methods_breakdown',
        'customer_types_breakdown',
        'avg_sale_value',
        'avg_profit_margin',
        'last_updated_at',
        'last_sale_id'
    ];

    protected $casts = [
        'period_date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'total_profit' => 'decimal:2',
        'avg_sale_value' => 'decimal:2',
        'avg_profit_margin' => 'decimal:2',
        'payment_methods_breakdown' => 'array',
        'customer_types_breakdown' => 'array',
        'last_updated_at' => 'datetime'
    ];

    // Period type constants
    const PERIOD_DAILY = 'daily';
    const PERIOD_MONTHLY = 'monthly';
    const PERIOD_YEARLY = 'yearly';

    /**
     * Scope to filter by period type
     */
    public function scopeByPeriodType($query, $periodType)
    {
        return $query->where('period_type', $periodType);
    }

    /**
     * Scope to filter by year
     */
    public function scopeByYear($query, $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter by month
     */
    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('period_date', [$startDate, $endDate]);
    }

    /**
     * Get monthly summaries for a year
     */
    public static function getMonthlyForYear($year)
    {
        return self::byPeriodType(self::PERIOD_MONTHLY)
            ->byYear($year)
            ->orderBy('month')
            ->get();
    }

    /**
     * Get yearly summaries for a range
     */
    public static function getYearlyRange($startYear, $endYear)
    {
        return self::byPeriodType(self::PERIOD_YEARLY)
            ->whereBetween('year', [$startYear, $endYear])
            ->orderBy('year')
            ->get();
    }

    /**
     * Get daily summaries for a month
     */
    public static function getDailyForMonth($year, $month)
    {
        return self::byPeriodType(self::PERIOD_DAILY)
            ->byYear($year)
            ->byMonth($month)
            ->orderBy('day')
            ->get();
    }

    /**
     * Get chart data for monthly trends
     */
    public static function getMonthlyChartData($year)
    {
        $monthlySummaries = self::getMonthlyForYear($year);
        
        $chartData = [
            'labels' => [],
            'revenue' => [],
            'cogs' => [],
            'profit' => []
        ];

        foreach (range(1, 12) as $month) {
            $summary = $monthlySummaries->firstWhere('month', $month);
            
            $chartData['labels'][] = Carbon::create($year, $month, 1)->format('M');
            $chartData['revenue'][] = $summary ? (float) $summary->total_revenue : 0;
            $chartData['cogs'][] = $summary ? (float) $summary->total_cogs : 0;
            $chartData['profit'][] = $summary ? (float) $summary->total_profit : 0;
        }

        return $chartData;
    }

    /**
     * Get chart data for yearly trends
     */
    public static function getYearlyChartData($startYear, $endYear)
    {
        $yearlySummaries = self::getYearlyRange($startYear, $endYear);
        
        $chartData = [
            'labels' => [],
            'revenue' => [],
            'cogs' => [],
            'profit' => []
        ];

        foreach (range($startYear, $endYear) as $year) {
            $summary = $yearlySummaries->firstWhere('year', $year);
            
            $chartData['labels'][] = $year;
            $chartData['revenue'][] = $summary ? (float) $summary->total_revenue : 0;
            $chartData['cogs'][] = $summary ? (float) $summary->total_cogs : 0;
            $chartData['profit'][] = $summary ? (float) $summary->total_profit : 0;
        }

        return $chartData;
    }

    /**
     * Get period identifier string
     */
    public function getPeriodIdentifierAttribute()
    {
        switch ($this->period_type) {
            case self::PERIOD_DAILY:
                return $this->period_date->format('Y-m-d');
            case self::PERIOD_MONTHLY:
                return $this->period_date->format('Y-m');
            case self::PERIOD_YEARLY:
                return $this->period_date->format('Y');
            default:
                return $this->period_date->format('Y-m-d');
        }
    }

    /**
     * Calculate profit margin percentage
     */
    public function getProfitMarginPercentageAttribute()
    {
        if ($this->total_revenue == 0) {
            return 0;
        }
        
        return round(($this->total_profit / $this->total_revenue) * 100, 2);
    }
}