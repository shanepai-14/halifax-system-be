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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('attribute_id')->nullable()->after('product_id');
            $table->index('attribute_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropIndex(['attribute_id']);
                $table->dropColumn('attribute_id');
            });
        });
    }
};
