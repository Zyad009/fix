<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invoice line can come from a routine service: the name is snapshotted
 * from it and the price typed by hand. The index serves usage counts,
 * suggested prices and the usage history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->maintenanceCenterForeign('routine_service_id', 'routine_services', nullable: true);
            $table->index(['routine_service_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['maintenance_center_id', 'routine_service_id']);
            $table->dropIndex(['routine_service_id', 'created_at']);
            $table->dropColumn('routine_service_id');
        });
    }
};
