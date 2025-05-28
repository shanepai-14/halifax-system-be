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
        Schema::create('bracket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bracket_id')->constrained('product_price_brackets')->onDelete('cascade');
            $table->integer('min_quantity');
            $table->integer('max_quantity')->nullable(); // null means unlimited
            $table->decimal('price', 10, 2);
            $table->enum('price_type', ['regular', 'wholesale', 'walk_in'])->default('regular');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['bracket_id', 'price_type', 'is_active']);
            $table->index(['min_quantity', 'max_quantity']);
            $table->index(['price_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bracket_items');
    }
};
