<?php

namespace App\Http\Resources\V1\Center;

use App\Enums\UserRole;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Role */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->name,
            'is_owner' => $this->is_owner,
            // One of the roles every center starts with (still editable unless owner).
            'is_default' => UserRole::tryFrom($this->key) !== null,
            'acts_as_technician' => $this->acts_as_technician,
            'permissions' => $this->grantedPermissions(),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
