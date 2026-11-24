<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The technical inspection of an invoice (one per order). Only inspected
 * items are stored; each keeps a snapshot of its label, like invoice lines,
 * so later checklist changes never rewrite a past report.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->maintenanceCenterKey();
            $table->maintenanceCenterForeign('order_id', 'orders', onDelete: 'cascade');
            $table->text('diagnostic_result')->nullable();
            $table->text('notes')->nullable();
            $table->maintenanceCenterForeign('inspected_by', 'users', nullable: true);
            $table->timestamps();

            $table->unique('order_id');
        });

        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->maintenanceCenterKey();
            $table->maintenanceCenterForeign('inspection_id', 'inspections', onDelete: 'cascade');
            $table->string('section', 30);
            $table->string('item', 50);
            $table->string('label', 100);
            $table->string('status', 20);
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'item']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
    }
};
