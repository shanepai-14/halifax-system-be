<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales_summaries', function (Blueprint $table) {
            $table->id();
            
            // Period identification
            $table->enum('period_type', ['daily', 'monthly', 'yearly']);
            $table->date('period_date'); // Store first day of period (e.g., 2024-01-01 for Jan 2024)
            $table->year('year');
            $table->tinyInteger('month')->nullable(); // NULL for yearly summaries
            $table->tinyInteger('day')->nullable(); // NULL for monthly/yearly summaries
            
            // Financial metrics
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_cogs', 15, 2)->default(0);
            $table->decimal('total_profit', 15, 2)->default(0);
            
            // Transaction counts
            $table->integer('total_sales_count')->default(0);
            $table->integer('completed_sales_count')->default(0);
            $table->integer('cancelled_sales_count')->default(0);
            $table->integer('returned_sales_count')->default(0);
            
            // Payment method breakdown (JSON for flexibility)
            $table->json('payment_methods_breakdown')->nullable();
            
            // Customer type breakdown
            $table->json('customer_types_breakdown')->nullable();
            
            // Average metrics
            $table->decimal('avg_sale_value', 10, 2)->default(0);
            $table->decimal('avg_profit_margin', 10, 2)->default(0);// Percentage
            
            // Metadata
            $table->timestamp('last_updated_at')->nullable();
            $table->integer('last_sale_id')->nullable(); // Track last processed sale
            $table->timestamps();
            
            // Indexes for fast queries
            $table->unique(['period_type', 'period_date']);
            $table->index(['period_type', 'year', 'month']);
            $table->index(['year', 'month']);
            $table->index('period_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales_summaries');
    }
};