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
        Schema::table('customers', function (Blueprint $table) {
           $table->boolean('is_valued_customer')->default(false)->after('city');
            $table->text('valued_customer_notes')->nullable()->after('is_valued_customer');
            $table->timestamp('valued_since')->nullable()->after('valued_customer_notes');
        });

        Schema::create('customer_custom_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->integer('min_quantity')->default(1);
            $table->integer('max_quantity')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->default(now());
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            
            // Ensure no overlapping quantity ranges for same customer-product-price_type
            $table->unique(['customer_id', 'product_id', 'min_quantity', 'max_quantity'], 
                          'customer_product_price_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_custom_prices');
        Schema::table('customers', function (Blueprint $table) {
             $table->dropColumn(['is_valued_customer', 'valued_customer_notes', 'valued_since']);
        });
    }
};
