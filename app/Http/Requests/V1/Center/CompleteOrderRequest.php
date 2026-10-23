<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class CompleteOrderRequest extends AbstractFormRequest
{
    protected function requireAtLeastOneField(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payments' => ['sometimes', 'array', 'max:5'],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
