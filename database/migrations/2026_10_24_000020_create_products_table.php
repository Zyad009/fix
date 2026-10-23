<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('category_id', 'categories', nullable: true);
            $table->string('name');
            $table->string('kind', 20)->default('part');
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('track_stock')->default(false);
            $table->boolean('is_active')->default(true);
            $table->createdBy();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('product_branch_stock', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('product_id', 'products');
            $table->decimal('qty', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_branch_stock');
        Schema::dropIfExists('products');
    }
};
