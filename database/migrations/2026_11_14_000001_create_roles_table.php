<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles become rows owned by each maintenance center, so owners can shape
 * them. Every existing center gets the default roles and each user is linked
 * to the role matching their old `users.role` key, which is then dropped.
 *
 * `key` is the stable value the API exchanges (`cashier`, `role_12`, ...):
 * the frontend keeps sending and reading the same strings as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->maintenanceCenterKey();
            $table->string('key', 50);
            $table->string('name', 100);
            $table->boolean('is_owner')->default(false);
            $table->boolean('acts_as_technician')->default(false);
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['maintenance_center_id', 'key']);
            $table->unique(['maintenance_center_id', 'name']);
        });

        $now = now();

        foreach (DB::table('maintenance_centers')->pluck('id') as $maintenanceCenterId) {
            DB::table('roles')->insert(array_map(fn (string $key, array $role): array => [
                'maintenance_center_id' => $maintenanceCenterId,
                'key' => $key,
                'name' => UserRole::from($key)->label('ar'),
                'is_owner' => $role['is_owner'],
                'acts_as_technician' => $role['acts_as_technician'],
                'permissions' => json_encode($role['permissions']),
                'created_at' => $now,
                'updated_at' => $now,
            ], array_keys(config('rbac.defaults')), config('rbac.defaults')));
        }

        Schema::table('users', function (Blueprint $table) {
            $table->maintenanceCenterForeign('role_id', 'roles', nullable: true);
        });

        DB::statement('UPDATE users JOIN roles ON roles.maintenance_center_id = users.maintenance_center_id AND roles.`key` = users.role SET users.role_id = roles.id');
        DB::statement('ALTER TABLE users MODIFY role_id BIGINT UNSIGNED NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->nullable()->after('password');
        });

        DB::statement('UPDATE users JOIN roles ON roles.id = users.role_id SET users.role = IF(roles.is_owner, "owner", IF(roles.`key` IN ("cashier", "technician", "technician_cashier"), roles.`key`, "cashier"))');
        DB::statement('ALTER TABLE users MODIFY role VARCHAR(30) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['maintenance_center_id', 'role_id']);
            $table->dropColumn('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
