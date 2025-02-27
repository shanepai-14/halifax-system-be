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
        Schema::create('purchase_order_additional_costs', function (Blueprint $table) {
            $table->id('po_cost_id');
            $table->unsignedBigInteger('rr_id');
            $table->unsignedBigInteger('cost_type_id');
            $table->decimal('amount', 15, 2);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('rr_id')
                ->references('rr_id')
                ->on('receiving_reports')
                ->onDelete('cascade');

            $table->foreign('cost_type_id')
                ->references('cost_type_id')
                ->on('additional_cost_types')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_additional_costs');
    }
};
