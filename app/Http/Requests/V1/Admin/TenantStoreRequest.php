<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\AbstractFormRequest;

class TenantStoreRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'plan' => ['nullable', 'string', 'max:50'],
            'subscription_ends_at' => ['nullable', 'date'],
            'cashier_max_discount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'branch_name' => ['nullable', 'string', 'max:255'],
            'owner' => ['required', 'array'],
            'owner.name' => ['required', 'string', 'max:255'],
            'owner.email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner.password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }
}
