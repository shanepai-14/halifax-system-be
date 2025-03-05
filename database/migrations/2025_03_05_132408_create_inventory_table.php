<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create inventory table
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('avg_cost_price', 10, 2)->default(0);
            $table->timestamp('last_received_at')->nullable();
            $table->boolean('recount_needed')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('product_id');
        });

        // Create inventory logs table
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_type'); // purchase, sales, adjustment_in, adjustment_out, etc.
            $table->string('reference_type'); // purchase_order, sales_order, adjustment, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_before', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Create inventory adjustments table
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_type'); // addition, reduction, damage, loss, return, correction
            $table->decimal('quantity', 10, 2);
            $table->decimal('quantity_before', 10, 2);
            $table->decimal('quantity_after', 10, 2);
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'created_at']);
        });

        // Create inventory counts table
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status'); // draft, in_progress, finalized, cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        // Create inventory count items table
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('count_id')->constrained('inventory_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('system_quantity', 10, 2);
            $table->decimal('counted_quantity', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['count_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('inventory_adjustments');
        Schema::dropIfExists('inventory_logs');
        Schema::dropIfExists('inventories');
    }
};