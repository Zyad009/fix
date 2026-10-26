<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\UserRole;
use App\Http\Requests\AbstractFormRequest;
use App\Models\Employee;
use Illuminate\Validation\Rule;

class EmployeeRequest extends AbstractFormRequest
{
    /**
     * The optional `account` creates (or updates) the employee's login, like
     * fitZone's staff page: one screen for the worker and their account.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';
        $employee = $this->route('employee');
        $userId = $employee instanceof Employee ? $employee->user_id : null;
        $accountRequired = $userId ? 'sometimes' : 'required';

        return [
            'name' => [$required, 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'base_salary' => [$required, 'decimal:0,2', 'min:0', 'max:9999999999'],
            'hired_at' => ['nullable', 'date_format:Y-m-d'],
            'account' => ['sometimes', 'array'],
            'account.email' => ['exclude_without:account', $accountRequired, 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'account.password' => ['exclude_without:account', $accountRequired, 'string', 'min:8', 'max:255'],
            'account.role' => ['exclude_without:account', $accountRequired, Rule::in(UserRole::staffValues())],
        ];
    }
}
