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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('customer_type')->default('regular');
            $table->string('payment_method')->default('cash');
            $table->dateTime('order_date');
            $table->dateTime('delivery_date')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('cogs', 10, 2)->default(0);
            $table->decimal('profit', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('amount_received', 10, 2)->default(0);
            $table->decimal('change', 10, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->boolean('is_delivered')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('distribution_price', 10, 2);
            $table->decimal('sold_price', 10, 2);
            $table->integer('quantity');
            $table->decimal('total_distribution_price', 10, 2);
            $table->decimal('total_sold_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->boolean('is_discount_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('credit_memo_number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->dateTime('return_date');
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending');
            $table->string('refund_method')->default('none');
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2);
            $table->string('return_reason')->nullable();
            $table->string('condition')->default('good');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 10, 2);
            $table->dateTime('payment_date');
            $table->string('reference_number')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};