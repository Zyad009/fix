<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('plan', 50)->default('trial');
            $table->timestamp('subscription_ends_at')->nullable();
            $table->decimal('cashier_max_discount', 12, 2)->default(0);
            $table->string('timezone', 64)->default('Africa/Cairo');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
