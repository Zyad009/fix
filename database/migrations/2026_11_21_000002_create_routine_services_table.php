<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Routine services: recurring jobs (oil change, tire fitting...) with a name
 * and no fixed price; the price is typed on each invoice line. Soft deletes
 * let the defaults seeder tell a deleted default from a missing one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_services', function (Blueprint $table) {
            $table->id();
            $table->maintenanceCenterKey();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->maintenanceCenterForeign('created_by', 'users', nullable: true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['maintenance_center_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_services');
    }
};
