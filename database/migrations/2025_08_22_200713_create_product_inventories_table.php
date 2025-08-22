<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_location_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_available')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('minimum_stock_level')->default(5);
            $table->integer('maximum_stock_level')->default(1000);
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->timestamp('last_restocked_at')->nullable();
            $table->timestamps();

            $table->index(['product_id']);
            $table->index(['business_location_id']);
            $table->index(['quantity_available']);
            $table->index(['quantity_available', 'minimum_stock_level']);
            $table->unique(['product_id', 'business_location_id'], 'unique_product_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_inventories');
    }
};
