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
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            // Add columns for balance tracking
            $table->decimal('balance_before', 15, 2)->nullable()->after('amount_returned')->comment('Balance before this transaction');
            $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before')->comment('Balance after this transaction');
            $table->decimal('balance_change', 15, 2)->nullable()->after('balance_after')->comment('Change in balance (+/-)');
        });

        // Also add similar columns to petty_cash_funds table for complete audit trail
        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->decimal('balance_before', 15, 2)->nullable()->after('amount')->comment('Balance before adding funds');
            $table->decimal('balance_after', 15, 2)->nullable()->after('balance_before')->comment('Balance after adding funds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after', 'balance_change']);
        });

        Schema::table('petty_cash_funds', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after']);
        });
    }
};