<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->tenantKey();
            $table->tenantForeign('user_id', 'users', nullable: true);
            $table->string('auditable_type', 50);
            $table->unsignedBigInteger('auditable_id');
            $table->string('event', 50);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('reason', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'auditable_type', 'auditable_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
