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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id('po_item_id');
            $table->foreignId('po_id')->constrained('purchase_orders', 'po_id')->onDelete('cascade');
            $table->foreignId('product_id');  // Assuming you have a products table
            $table->integer('requested_quantity');
            $table->integer('received_quantity')->default(0);
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->softDeletes(); // Add soft deletes
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};