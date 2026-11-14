<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A maintenance center's role. The owner role holds every permission and
 * spans every branch; any other role holds the permissions the owner picked
 * and is pinned to one branch.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property bool $is_owner
 * @property bool $acts_as_technician
 * @property list<string> $permissions
 */
#[Fillable(['key', 'name', 'is_owner', 'acts_as_technician', 'permissions'])]
class Role extends Model
{
    use BelongsToMaintenanceCenter;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_owner' => false,
        'acts_as_technician' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_owner' => 'boolean',
            'acts_as_technician' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /**
     * Permissions a non-owner role may be given.
     *
     * @return list<string>
     */
    public static function assignablePermissions(): array
    {
        return array_values(array_diff(config('rbac.permissions'), config('rbac.owner_only')));
    }

    /**
     * The id of the current maintenance center's role with this key.
     */
    public static function idOf(UserRole|string $key): int
    {
        return static::query()->where('key', $key instanceof UserRole ? $key->value : $key)->valueOrFail('id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function grants(string $permission): bool
    {
        return $this->is_owner || in_array($permission, $this->permissions, true);
    }

    /**
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return $this->is_owner ? config('rbac.permissions') : array_values($this->permissions);
    }

    /**
     * Owners act across every branch; staff are pinned to one.
     */
    public function isBranchBound(): bool
    {
        return ! $this->is_owner;
    }

    /**
     * The shape the API has always used for a role.
     *
     * @return array{label: string, value: string}
     */
    public function response(): array
    {
        return ['label' => $this->name, 'value' => $this->key];
    }
}
