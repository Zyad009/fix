<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the tenant concept to maintenance center across the schema.
 *
 * Columns and tables are renamed in place, so MySQL carries the foreign keys and
 * indexes along and isolation between centers is never unenforced. Foreign key
 * constraints keep their old names because MySQL cannot rename them; drop them
 * by their explicit name if ever needed.
 *
 * DDL in MySQL is not transactional: take a backup before running this.
 */
return new class extends Migration
{
    private const TABLES = [
        'audit_logs',
        'branches',
        'categories',
        'customers',
        'employee_transactions',
        'employees',
        'expense_categories',
        'expenses',
        'order_items',
        'orders',
        'payment_receipts',
        'payments',
        'product_branch_stock',
        'products',
        'users',
        'vehicles',
        'whatsapp_messages',
    ];

    /**
     * Old index names whose direct replacement would pass MySQL's 64-character limit.
     */
    private const SHORTENED_INDEXES = [
        'audit_logs_tenant_id_auditable_type_auditable_id_index' => 'audit_logs_maintenance_center_id_auditable_index',
        'employee_transactions_tenant_id_branch_id_occurred_at_index' => 'emp_transactions_maintenance_center_id_branch_occurred_index',
        'payment_receipts_tenant_id_branch_id_received_at_index' => 'payment_receipts_maintenance_center_id_branch_received_index',
    ];

    public function up(): void
    {
        $this->dropViews();

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` RENAME COLUMN `tenant_id` TO `maintenance_center_id`");
        }

        Schema::rename('tenants', 'maintenance_centers');

        foreach ($this->indexNames('%tenant_id%') as $index) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
                $index->table_name,
                $index->index_name,
                self::SHORTENED_INDEXES[$index->index_name] ?? str_replace('tenant_id', 'maintenance_center_id', $index->index_name),
            ));
        }

        $this->createViews('maintenance_center_id');

        DB::table('audit_logs')->where('auditable_type', 'tenant')->update(['auditable_type' => 'maintenance_center']);
    }

    public function down(): void
    {
        $this->dropViews();

        $shortened = array_flip(self::SHORTENED_INDEXES);

        foreach ($this->indexNames('%maintenance_center_id%') as $index) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
                $index->table_name,
                $index->index_name,
                $shortened[$index->index_name] ?? str_replace('maintenance_center_id', 'tenant_id', $index->index_name),
            ));
        }

        Schema::rename('maintenance_centers', 'tenants');

        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE `{$table}` RENAME COLUMN `maintenance_center_id` TO `tenant_id`");
        }

        $this->createViews('tenant_id');

        DB::table('audit_logs')->where('auditable_type', 'maintenance_center')->update(['auditable_type' => 'tenant']);
    }

    /**
     * @return list<object{table_name: string, index_name: string}>
     */
    private function indexNames(string $pattern): array
    {
        return DB::select(
            'SELECT DISTINCT TABLE_NAME AS table_name, INDEX_NAME AS index_name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND INDEX_NAME LIKE ?',
            [$pattern],
        );
    }

    private function dropViews(): void
    {
        DB::statement('DROP VIEW IF EXISTS vehicle_balances');
        DB::statement('DROP VIEW IF EXISTS employee_balances');
    }

    private function createViews(string $column): void
    {
        DB::statement(<<<SQL
            CREATE VIEW vehicle_balances AS
            SELECT {$column}, customer_id, vehicle_id,
                   SUM(total - paid) AS balance,
                   COUNT(*) AS orders_count,
                   MIN(completed_at) AS oldest_due_at
            FROM orders
            WHERE status = 'completed' AND total > paid
            GROUP BY {$column}, customer_id, vehicle_id
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW employee_balances AS
            SELECT {$column}, employee_id,
                   SUM(CASE WHEN type IN ('salary', 'bonus') THEN amount ELSE -amount END) AS balance
            FROM employee_transactions
            WHERE voided_at IS NULL
            GROUP BY {$column}, employee_id
        SQL);
    }
};
