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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('promotion_type', ['percentage', 'fixed_amount', 'buy_x_get_y', 'free_service']);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->decimal('discount_amount', 10, 2)->nullable();
            $table->integer('buy_quantity')->nullable();
            $table->integer('get_quantity')->nullable();
            $table->decimal('minimum_purchase', 10, 2)->nullable();
            $table->decimal('maximum_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_count')->default(0);
            $table->integer('user_usage_limit')->default(1);
            $table->enum('target_type', ['all', 'services', 'products', 'categories', 'specific_items'])->default('all');
            $table->json('target_items')->nullable();
            $table->json('applicable_locations')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('promo_code', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_profile_id']);
            $table->index(['start_date', 'end_date']);
            $table->index(['promo_code']);
            $table->index(['is_active']);
            $table->index(['promotion_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
