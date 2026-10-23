<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\UserRole;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isCreate = $this->isMethod('post');
        $required = $isCreate ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => [$required, 'string', 'min:8', 'max:255'],
            'role' => [$required, Rule::enum(UserRole::class)],
            'branch_id' => ['nullable', 'integer', tenantExists('branches')->where('is_active', true)],
        ];
    }
}
