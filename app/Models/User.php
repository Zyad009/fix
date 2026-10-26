<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * A service-center account (owner, cashier, technician, technician-cashier).
 *
 * @property int $id
 * @property int|null $branch_id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property bool $is_active
 */
#[Fillable(['name', 'email', 'password', 'role', 'branch_id', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory;

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
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Sanctum resolves token owners before any tenant is in context.
     */
    protected static function requiresTenantContext(): bool
    {
        return false;
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

    public function hasPermission(string $permission): bool
    {
        $granted = $this->role->permissions();

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        $granted = $this->role->permissions();

        return in_array('*', $granted, true) ? config('rbac.permissions') : $granted;
    }
}
