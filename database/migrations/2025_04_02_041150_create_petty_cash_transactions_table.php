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
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_reference', 20)->unique();
            $table->foreignId('employee_id')->constrained('employees');
            $table->date('date');
            $table->string('purpose', 100);
            $table->text('description')->nullable();
            $table->decimal('amount_issued', 10, 2);
            $table->decimal('amount_spent', 10, 2)->default(0);
            $table->decimal('amount_returned', 10, 2)->default(0);
            $table->string('receipt_attachment')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['issued', 'settled', 'approved', 'cancelled'])->default('issued');
            $table->foreignId('issued_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('petty_cash_transactions');
    }
};
