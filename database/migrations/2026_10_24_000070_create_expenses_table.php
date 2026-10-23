<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('category_id', 'expense_categories');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20)->default('cash');
            $table->date('spent_at');
            $table->string('notes', 500)->nullable();
            $table->createdBy();
            $table->voidColumns();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'spent_at']);
        });

        DB::statement('ALTER TABLE expenses ADD CONSTRAINT expenses_amount_check CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
