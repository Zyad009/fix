<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\EmployeeTransactionType;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class EmployeeTransactionRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EmployeeTransactionType::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'period' => ['nullable', 'required_if:type,salary', 'date_format:Y-m'],
            'occurred_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
