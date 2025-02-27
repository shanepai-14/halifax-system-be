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
        Schema::table('purchase_order_received_items', function (Blueprint $table) {
            $table->boolean('processed_for_inventory')->default(false);
            $table->timestamp('processed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_received_items', function (Blueprint $table) {
            $table->dropColumn('processed_for_inventory');
            $table->dropColumn('processed_at');
        });
    }
};
