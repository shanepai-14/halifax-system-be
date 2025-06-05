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
        Schema::create('receiving_reports', function (Blueprint $table) {
            $table->id('rr_id');
            $table->unsignedBigInteger('po_id');
            $table->string('invoice')->nullable();
            $table->string('batch_number')->unique();
            $table->integer('term');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiving_reports');
    }
};
