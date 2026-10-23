<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class PayrollRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', 'date_format:Y-m'],
            'items' => ['sometimes', 'array'],
            'items.*.employee_id' => ['required', 'integer', tenantExists('employees')],
            'items.*.amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
