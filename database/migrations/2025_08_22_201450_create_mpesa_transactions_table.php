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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_request_id')->unique();
            $table->string('checkout_request_id')->unique();
            $table->string('mpesa_receipt_number')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('phone_number', 20);
            $table->string('account_reference', 100);
            $table->string('transaction_desc');
            $table->enum('transaction_type', ['subscription', 'order', 'appointment']);
            $table->unsignedBigInteger('reference_id'); // subscription_id, order_id, appointment_id
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled'])->default('pending');
            $table->text('result_desc')->nullable();
            $table->json('callback_data')->nullable();
            $table->timestamps();
            
            $table->index(['status']);
            $table->index(['transaction_type', 'reference_id']);
            $table->index(['phone_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
