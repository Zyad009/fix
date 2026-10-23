<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', tenantExists('expense_categories')->where('is_active', true)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'method' => ['sometimes', Rule::enum(PaymentMethod::class)],
            'spent_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
