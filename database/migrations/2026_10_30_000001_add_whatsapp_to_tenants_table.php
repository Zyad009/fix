<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('whatsapp_device_id')->nullable()->after('timezone');
            $table->boolean('whatsapp_invoice_enabled')->default(true)->after('whatsapp_device_id');
            $table->boolean('whatsapp_payment_enabled')->default(true)->after('whatsapp_invoice_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_device_id', 'whatsapp_invoice_enabled', 'whatsapp_payment_enabled']);
        });
    }
};
