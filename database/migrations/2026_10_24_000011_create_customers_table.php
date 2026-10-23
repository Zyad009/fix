<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->string('name');
            $table->string('phone', 20);
            // Unique among live rows only: a soft-deleted customer frees the number.
            $table->string('phone_active', 20)->nullable()->storedAs('IF(deleted_at IS NULL, phone, NULL)');
            $table->text('notes')->nullable();
            $table->createdBy();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'phone_active']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
