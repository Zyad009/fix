<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Center users. Create them inside a tenant context
 * (`tenantContext()->run($tenant, ...)`): `tenant_id` is stamped from it.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Cashier,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => UserRole::Owner, 'branch_id' => null]);
    }

    public function cashier(int $branchId): static
    {
        return $this->state(fn () => ['role' => UserRole::Cashier, 'branch_id' => $branchId]);
    }

    public function technician(int $branchId): static
    {
        return $this->state(fn () => ['role' => UserRole::Technician, 'branch_id' => $branchId]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
