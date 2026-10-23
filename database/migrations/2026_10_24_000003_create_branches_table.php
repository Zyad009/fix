<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('next_order_no')->default(1);
            $table->unsignedInteger('next_receipt_no')->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
