<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Money received is recorded per order (so debt is tracked per vehicle) and
 * grouped by the receipt handed to the customer. Rows are append-only:
 * corrections are voids, cancellations are refunds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->unsignedInteger('receipt_no');
            $table->tenantForeign('customer_id', 'customers');
            $table->tenantForeign('vehicle_id', 'vehicles', nullable: true);
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->tenantForeign('received_by', 'users', nullable: true);
            $table->timestamp('received_at');
            $table->string('notes', 500)->nullable();
            $table->voidColumns();
            $table->timestamps();

            $table->unique(['branch_id', 'receipt_no']);
            $table->index(['tenant_id', 'branch_id', 'received_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            // The branch whose drawer received (or paid back) the money.
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('receipt_id', 'payment_receipts', nullable: true);
            $table->tenantForeign('order_id', 'orders');
            $table->string('type', 20)->default('payment');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->timestamp('paid_at');
            $table->tenantForeign('received_by', 'users', nullable: true);
            $table->string('notes', 500)->nullable();
            $table->voidColumns();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'paid_at']);
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_type_check CHECK (type IN ('payment','refund'))");
        DB::statement('ALTER TABLE payment_receipts ADD CONSTRAINT payment_receipts_amount_check CHECK (amount > 0)');

        // Debt per vehicle (and the customer who owes it): computed, never stored.
        DB::statement(<<<'SQL'
            CREATE VIEW vehicle_balances AS
            SELECT tenant_id, customer_id, vehicle_id,
                   SUM(total - paid) AS balance,
                   COUNT(*) AS orders_count,
                   MIN(completed_at) AS oldest_due_at
            FROM orders
            WHERE status = 'completed' AND total > paid
            GROUP BY tenant_id, customer_id, vehicle_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS vehicle_balances');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_receipts');
    }
};
