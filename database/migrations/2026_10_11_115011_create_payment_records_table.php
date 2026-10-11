<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->morphs('payable');                       
            $table->decimal('amount', 10, 2);
            $table->string('method', 30);                    
            $table->string('reference', 100)->nullable();    
            $table->timestamp('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_profile_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_records');
    }
};