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
            $table->decimal('sold_quantity', 10, 2)->after('received_quantity')->default(0);
            $table->boolean('fully_consumed')->after('sold_quantity')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_received_items', function (Blueprint $table) {
            $table->dropColumn('sold_quantity');
            $table->dropColumn('fully_consumed');
        });
    }
};
