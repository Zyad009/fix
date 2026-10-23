<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenant = $this->route('tenant');

        return [
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('branches', 'name')->where('tenant_id', $tenant->id)->ignore($this->route('branch')),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
