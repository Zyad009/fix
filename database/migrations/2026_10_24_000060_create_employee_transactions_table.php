<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee ledger: salary/bonus credit the employee; deduction/advance/payment
 * debit them. Balance > 0 means the center owes the employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_transactions', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('employee_id', 'employees');
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            // First day of the month a salary belongs to.
            $table->date('period')->nullable();
            $table->date('occurred_at');
            $table->string('notes', 500)->nullable();
            $table->createdBy();
            $table->voidColumns();
            $table->timestamps();

            // One live salary per employee per month.
            $table->string('salary_key', 40)->nullable()
                ->storedAs("IF(type = 'salary' AND voided_at IS NULL, CONCAT(employee_id, '-', period), NULL)");
            $table->unique(['tenant_id', 'salary_key']);
            $table->index(['tenant_id', 'branch_id', 'occurred_at']);
        });

        DB::statement('ALTER TABLE employee_transactions ADD CONSTRAINT employee_transactions_amount_check CHECK (amount > 0)');
        DB::statement("ALTER TABLE employee_transactions ADD CONSTRAINT employee_transactions_type_check CHECK (type IN ('salary','bonus','deduction','advance','payment'))");

        DB::statement(<<<'SQL'
            CREATE VIEW employee_balances AS
            SELECT tenant_id, employee_id,
                   SUM(CASE WHEN type IN ('salary', 'bonus') THEN amount ELSE -amount END) AS balance
            FROM employee_transactions
            WHERE voided_at IS NULL
            GROUP BY tenant_id, employee_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS employee_balances');
        Schema::dropIfExists('employee_transactions');
    }
};
