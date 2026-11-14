<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMaintenanceCenter;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A service-center account. What it may do comes from its role.
 *
 * @property int $id
 * @property int|null $branch_id
 * @property int $role_id
 * @property Role $role
 * @property string $name
 * @property string|null $username
 * @property string $email
 * @property bool $is_active
 */
#[Fillable(['name', 'username', 'email', 'password', 'role_id', 'branch_id', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToMaintenanceCenter, HasApiTokens, HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sanctum resolves token owners before any maintenance center is in context.
     */
    protected static function requiresMaintenanceCenterContext(): bool
    {
        return false;
    }

    /**
     * Read without the maintenance center scope: Sanctum resolves the user
     * before any center is in context, and the composite foreign key already
     * keeps the role inside the user's own center.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class)->withoutGlobalScope('maintenance_center');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The staff record (payroll, technician picker) behind this login, if any.
     *
     * @return HasOne<Employee, $this>
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class)->withoutGlobalScope('branch');
    }

    /**
     * The role, loaded on first use: the authenticated user is resolved by
     * Sanctum without relations, and lazy loading is off outside production.
     */
    public function currentRole(): Role
    {
        return $this->loadMissing('role')->role;
    }

    public function isOwner(): bool
    {
        return $this->currentRole()->is_owner;
    }

    public function actsAsTechnician(): bool
    {
        return $this->currentRole()->acts_as_technician;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->currentRole()->grants($permission);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->currentRole()->grantedPermissions();
    }
}
