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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_inventory_id')->constrained()->onDelete('cascade');
            $table->enum('movement_type', ['stock_in', 'stock_out', 'adjustment', 'damaged', 'expired', 'sold', 'returned']);
            $table->integer('quantity');
            $table->enum('reference_type', ['purchase', 'sale', 'adjustment', 'return'])->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_inventory_id']);
            $table->index(['movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
