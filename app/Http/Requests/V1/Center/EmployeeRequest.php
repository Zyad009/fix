<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'base_salary' => [$required, 'decimal:0,2', 'min:0', 'max:9999999999'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'integer', tenantExists('users'), Rule::unique('employees', 'user_id')->ignore($this->route('employee'))],
        ];
    }
}
