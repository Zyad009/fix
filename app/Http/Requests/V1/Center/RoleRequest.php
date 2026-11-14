<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use App\Models\Role;
use Illuminate\Validation\Rule;

class RoleRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:100', maintenanceCenterUnique('roles', 'name')->ignore($this->route('role'))],
            'acts_as_technician' => ['sometimes', 'boolean'],
            'permissions' => [$required, 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Role::assignablePermissions())],
        ];
    }
}
