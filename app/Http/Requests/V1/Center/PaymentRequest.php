<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Requests\AbstractFormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'vehicle_id' => ['nullable', 'integer', tenantExists('vehicles')],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
