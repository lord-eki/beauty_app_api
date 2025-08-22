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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_location_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->foreignId('staff_id')->nullable()->constrained('service_staff')->onDelete('set null');
            $table->date('appointment_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])->default('pending');
            $table->text('customer_notes')->nullable();
            $table->text('business_notes')->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'refunded'])->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['business_profile_id']);
            $table->index(['appointment_date', 'start_time']);
            $table->index(['status']);
            $table->index(['staff_id', 'appointment_date']);
            $table->unique(['staff_id', 'appointment_date', 'start_time'], 'unique_staff_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
