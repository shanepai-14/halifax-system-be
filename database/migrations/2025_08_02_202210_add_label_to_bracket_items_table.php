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
        Schema::table('bracket_items', function (Blueprint $table) {
             // Add a simple label field to help distinguish multiple prices per tier
            $table->string('label')->default('Price Option')->after('price_type');
            
            // Add sort order to maintain price option ordering within tiers
            $table->integer('sort_order')->default(0)->after('is_active');
            
            // Add index for better performance when querying by bracket and sort order
            $table->index(['bracket_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bracket_items', function (Blueprint $table) {
              $table->dropColumn(['label', 'sort_order']);
            $table->dropIndex(['bracket_id', 'sort_order']);
        });
    }
};
