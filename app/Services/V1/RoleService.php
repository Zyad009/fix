<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The maintenance center's roles. The owner role is fixed (every permission,
 * every branch) so a center can never lock itself out; every other role is
 * the owner's to shape.
 */
class RoleService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @return Collection<int, Role>
     */
    public function list(): Collection
    {
        return Role::query()->withCount('users')->orderByDesc('is_owner')->orderBy('id')->get();
    }

    /**
     * Role picker options, in the `{label, value}` shape the API always used.
     *
     * @return list<array{label: string, value: string}>
     */
    public function options(): array
    {
        return Role::query()->orderByDesc('is_owner')->orderBy('id')->get()->map->response()->all();
    }

    /**
     * The grantable permissions, grouped and labelled for the role editor.
     *
     * @return list<array{key: string, label: string, permissions: list<array{key: string, label: string}>}>
     */
    public function catalog(): array
    {
        $assignable = Role::assignablePermissions();
        $groups = [];

        foreach (config('rbac.groups') as $group => $permissions) {
            $permissions = array_values(array_intersect($permissions, $assignable));

            if ($permissions !== []) {
                $groups[] = [
                    'key' => $group,
                    'label' => __('messages.permission_groups')[$group] ?? $group,
                    'permissions' => array_map(fn (string $permission): array => [
                        'key' => $permission,
                        'label' => __('messages.permission_labels')[$permission] ?? $permission,
                    ], $permissions),
                ];
            }
        }

        return $groups;
    }

    /**
     * Custom roles get a generated key (`role_12`): it is what users and
     * employees carry in the API, and it never changes after creation.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create([
                ...$this->attributes($data),
                'key' => Str::uuid()->toString(),
            ]);

            $role->update(['key' => 'role_'.$role->id]);
            $this->audit->record($role, AuditEvent::Created, new: $this->auditValues($role));

            return $role->loadCount('users');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Role $role, array $data): Role
    {
        $this->ensureNotOwnerRole($role);

        $old = $this->auditValues($role);
        $role->update($this->attributes($data));
        $this->audit->record($role, AuditEvent::Updated, old: $old, new: $this->auditValues($role));

        return $role->loadCount('users');
    }

    /**
     * Only a role nobody holds can go, deactivated accounts included: they
     * still need a role if they are ever reactivated.
     */
    public function destroy(Role $role): void
    {
        $this->ensureNotOwnerRole($role);

        if (User::query()->where('role_id', $role->id)->exists()) {
            throw new BusinessRuleException(__('messages.role_in_use'), 409);
        }

        $role->delete();
    }

    /**
     * The roles a new maintenance center starts with (inside its context).
     */
    public function seedDefaults(): Role
    {
        foreach (config('rbac.defaults') as $key => $role) {
            Role::query()->create(['key' => $key, 'name' => UserRole::from($key)->label('ar'), ...$role]);
        }

        return Role::query()->where('is_owner', true)->firstOrFail();
    }

    private function ensureNotOwnerRole(Role $role): void
    {
        if ($role->is_owner) {
            throw new BusinessRuleException(__('messages.owner_role_locked'), 422);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'acts_as_technician', 'permissions']);

        if (isset($attributes['permissions'])) {
            $attributes['permissions'] = array_values(array_unique($attributes['permissions']));
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Role $role): array
    {
        return Arr::only($role->toArray(), ['name', 'acts_as_technician', 'permissions']);
    }
}
