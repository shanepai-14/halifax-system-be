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
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('sale_id');
            $table->string('status')->nullable()->after('reference_number');
            $table->string('void_reason')->nullable()->after('remarks');
            $table->timestamp('voided_at')->nullable()->after('void_reason');
            $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
            
            // Add foreign key constraints if needed
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            
            // Update the received_by foreign key constraint
            $table->dropForeign(['received_by']);
            $table->foreign('received_by')->references('id')->on('customers')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_payments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['received_by']);
            
            // Drop the columns
            $table->dropColumn([
                'user_id',
                'status',
                'void_reason',
                'voided_at',
                'voided_by'
            ]);
            
            // Restore original foreign key for received_by
            $table->foreign('received_by')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
