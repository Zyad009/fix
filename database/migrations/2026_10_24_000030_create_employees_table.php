<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('branch_id', 'branches');
            $table->tenantForeign('user_id', 'users', nullable: true);
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->date('hired_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->createdBy();
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
