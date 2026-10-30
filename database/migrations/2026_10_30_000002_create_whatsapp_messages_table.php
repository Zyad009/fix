<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->string('type', 20);
            $table->tenantForeign('order_id', 'orders', nullable: true);
            $table->tenantForeign('receipt_id', 'payment_receipts', nullable: true);
            // Masked: full numbers never land in the log.
            $table->string('recipient', 32)->nullable();
            $table->string('status', 30);
            $table->string('provider_message_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
