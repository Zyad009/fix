<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class UserService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return User::query()
            ->with('branch:id,name')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): User
    {
        $user = User::query()->create($this->attributes($data));

        return $user->load('branch:id,name');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $isSelf = $user->is(actor());

        if ($isSelf && isset($data['role']) && $data['role'] !== $user->role->value) {
            throw new BusinessRuleException(__('messages.cannot_modify_self'), 422);
        }

        $user->update($this->attributes([
            'role' => $user->role->value,
            'branch_id' => $user->branch_id,
            ...$data,
        ]));

        if (isset($data['password']) && ! $isSelf) {
            $user->tokens()->delete();
        }

        return $user->load('branch:id,name');
    }

    public function toggle(int $userId): User
    {
        $user = User::query()->findOrFail($userId);

        if ($user->is(actor())) {
            throw new BusinessRuleException(__('messages.cannot_modify_self'), 422);
        }

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $this->audit->record($user, AuditEvent::Toggled, new: ['is_active' => $user->is_active]);

        return $user->load('branch:id,name');
    }

    /**
     * Owners span all branches; staff must belong to exactly one.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['name', 'email', 'password', 'role', 'branch_id']);
        $role = UserRole::from($data['role']);

        if (! $role->isBranchBound()) {
            $attributes['branch_id'] = null;
        } elseif (empty($attributes['branch_id'])) {
            throw new BusinessRuleException(__('messages.staff_branch_required'), 422);
        }

        return $attributes;
    }
}
