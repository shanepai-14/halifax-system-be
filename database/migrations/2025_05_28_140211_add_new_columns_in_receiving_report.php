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
        Schema::table('receiving_reports', function (Blueprint $table) {
            $table->decimal('items_total', 15, 2)->nullable()->after('is_paid');
            $table->decimal('additional_costs_total', 15, 2)->nullable()->after('items_total');
            $table->decimal('grand_total', 15, 2)->nullable()->after('additional_costs_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receiving_reports', function (Blueprint $table) {
          $table->dropColumn(['items_total', 'additional_costs_total', 'grand_total']);
        });
    }
};
