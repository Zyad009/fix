<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('whatsapp_service_reminder_enabled')->default(false)->after('whatsapp_payment_enabled');
            $table->boolean('whatsapp_debt_reminder_enabled')->default(false)->after('whatsapp_service_reminder_enabled');
            $table->unsignedSmallInteger('debt_reminder_days')->default(30)->after('whatsapp_debt_reminder_enabled');
        });

        // The invoice whose next service date was already reminded.
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('service_reminder_sent_at')->nullable()->after('next_service_date');
        });

        // Restarts the debt reminder count, like a new invoice or a payment.
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('debt_reminded_at')->nullable()->after('notes');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->tenantForeign('customer_id', 'customers', nullable: true);
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['tenant_id', 'customer_id']);
            $table->dropColumn('customer_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('debt_reminded_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('service_reminder_sent_at');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_service_reminder_enabled', 'whatsapp_debt_reminder_enabled', 'debt_reminder_days']);
        });
    }
};
