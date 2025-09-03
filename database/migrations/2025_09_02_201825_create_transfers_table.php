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
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('to_warehouse_id')->constrained('warehouses');
            $table->foreignId('created_by')->constrained('users');
            $table->enum('status', ['pending', 'in_transit', 'completed', 'cancelled'])
                  ->default('pending');
            $table->date('delivery_date')->nullable();
            $table->decimal('total_value', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'delivery_date']);
            $table->index('to_warehouse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
