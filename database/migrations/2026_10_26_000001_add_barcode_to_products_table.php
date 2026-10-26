<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Typed or scanned on the POS screen (a scanner types like a keyboard).
            $table->string('barcode', 64)->nullable()->after('name');
            $table->string('barcode_active', 64)->nullable()->storedAs('IF(deleted_at IS NULL, barcode, NULL)')->after('barcode');

            $table->unique(['tenant_id', 'barcode_active']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'barcode_active']);
            $table->dropColumn(['barcode_active', 'barcode']);
        });
    }
};
