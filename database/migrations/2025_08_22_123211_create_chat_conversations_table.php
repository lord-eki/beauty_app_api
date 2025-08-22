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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('business_profile_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['active', 'closed', 'blocked'])->default('active');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['provider_id']);
            $table->index(['status']);
            $table->index(['last_message_at']);
            $table->unique(['customer_id', 'business_profile_id'], 'unique_customer_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
