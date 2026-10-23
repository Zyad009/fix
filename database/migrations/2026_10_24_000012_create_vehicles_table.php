<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            // Current owner; past visits keep the customer who paid at the time.
            $table->tenantForeign('customer_id', 'customers');
            $table->string('plate_number', 30);
            $table->string('plate_normalized', 30);
            $table->string('plate_active', 30)->nullable()->storedAs('IF(deleted_at IS NULL, plate_normalized, NULL)');
            $table->string('vin', 50)->nullable();
            $table->string('vin_active', 50)->nullable()->storedAs('IF(deleted_at IS NULL, vin, NULL)');
            $table->foreignId('car_make_id')->nullable()->constrained('car_makes');
            $table->foreignId('car_model_id')->nullable()->constrained('car_models');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color', 50)->nullable();
            // Cache of the latest completed visit; orders.odometer is the source of truth.
            $table->unsignedInteger('last_odometer')->nullable();
            $table->timestamp('last_visit_at')->nullable();
            $table->text('notes')->nullable();
            $table->createdBy();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'plate_active']);
            $table->unique(['tenant_id', 'vin_active']);
            $table->index(['tenant_id', 'plate_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
