<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Center users. Create them inside a maintenance center context
 * (`maintenanceCenterContext()->run($maintenanceCenter, ...)`): `maintenance_center_id` is stamped from it.
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
            'role_id' => fn (): int => Role::idOf(UserRole::Cashier),
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role_id' => Role::idOf(UserRole::Owner), 'branch_id' => null]);
    }

    public function cashier(int $branchId): static
    {
        return $this->state(fn () => ['role_id' => Role::idOf(UserRole::Cashier), 'branch_id' => $branchId]);
    }

    public function technician(int $branchId): static
    {
        return $this->state(fn () => ['role_id' => Role::idOf(UserRole::Technician), 'branch_id' => $branchId]);
    }

    public function technicianCashier(int $branchId): static
    {
        return $this->state(fn () => ['role_id' => Role::idOf(UserRole::TechnicianCashier), 'branch_id' => $branchId]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
