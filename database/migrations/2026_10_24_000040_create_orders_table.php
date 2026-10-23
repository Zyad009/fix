<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The order is the invoice: it starts `open` and becomes the invoice once
 * `completed`. subtotal/total/paid are denormalized and always recalculated
 * from order_items/payments inside the same transaction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->unsignedInteger('order_no');
            $table->tenantForeign('customer_id', 'customers');
            $table->tenantForeign('vehicle_id', 'vehicles');
            $table->tenantForeign('technician_id', 'employees', nullable: true);
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('odometer')->nullable();
            $table->unsignedInteger('next_service_odometer')->nullable();
            $table->date('next_service_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('paid', 12, 2)->default(0);
            $table->boolean('is_opening_balance')->default(false);
            $table->text('notes')->nullable();
            $table->createdBy();
            $table->timestamp('completed_at')->nullable();
            $table->tenantForeign('completed_by', 'users', nullable: true);
            $table->timestamp('cancelled_at')->nullable();
            $table->tenantForeign('cancelled_by', 'users', nullable: true);
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'order_no']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
            $table->index(['tenant_id', 'status', 'completed_at']);
            $table->index(['tenant_id', 'next_service_date']);
        });

        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_discount_check CHECK (discount >= 0 AND discount <= subtotal)');
        DB::statement('ALTER TABLE orders ADD CONSTRAINT orders_paid_check CHECK (paid >= 0 AND paid <= total)');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN ('open','completed','cancelled'))");

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('order_id', 'orders', onDelete: 'cascade');
            // NULL = free item typed at the counter.
            $table->tenantForeign('product_id', 'products', nullable: true);
            $table->string('name');
            $table->string('kind', 20)->default('part');
            $table->decimal('qty', 10, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->storedAs('ROUND(qty * unit_price, 2)');
            $table->createdBy();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_qty_check CHECK (qty > 0)');
        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_price_check CHECK (unit_price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
