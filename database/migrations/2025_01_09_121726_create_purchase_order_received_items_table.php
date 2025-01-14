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
        Schema::create('purchase_order_received_items', function (Blueprint $table) {
            $table->id('received_item_id');
            $table->unsignedBigInteger('po_id');
            $table->foreignId('product_id'); 
            $table->unsignedBigInteger('attribute_id')->nullable();
            $table->decimal('received_quantity', 15, 2);
            $table->decimal('cost_price', 15, 2)->nullable();
            $table->text('remarks')->nullable();
            
            // Pricing columns
            $table->decimal('walk_in_price', 15, 2)->nullable();
            $table->decimal('term_price', 15, 2)->nullable();
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->decimal('regular_price', 15, 2)->nullable();
            
            $table->timestamps();
            $table->softDeletes();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_received_items');
    }
};
