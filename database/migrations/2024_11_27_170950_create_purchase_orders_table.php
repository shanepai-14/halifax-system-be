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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id('po_id');
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained('suppliers', 'supplier_id');
            $table->timestamp('po_date');
            $table->decimal('total_amount', 10, 2);
            $table->enum('status', ['pending', 'partially_received', 'completed', 'cancelled']);
            $table->text('invoice')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
