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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->string('plan_name', 100)->default('Basic');
            $table->decimal('amount', 8, 2)->default(479.00);
            $table->string('currency', 3)->default('KES');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['active', 'expired', 'cancelled', 'pending'])->default('pending');
            $table->string('mpesa_transaction_id', 100)->nullable();
            $table->string('payment_phone', 20)->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['start_date', 'end_date']);
            $table->index(['mpesa_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
