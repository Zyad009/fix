<?php

namespace App\Http\Requests\V1\Center;

use Illuminate\Validation\Rule;

/**
 * Collection from the customer page: `oldest` spreads the amount over the
 * unpaid invoices oldest first; `manual` gives each chosen invoice its own
 * amount (one invoice = "pay this invoice").
 */
class CustomerPaymentRequest extends PaymentRequest
{
    public const ALLOCATION_OLDEST = 'oldest';

    public const ALLOCATION_MANUAL = 'manual';

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if (blank($this->input('allocation'))) {
            $this->merge(['allocation' => self::ALLOCATION_OLDEST]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isManual = $this->input('allocation') === self::ALLOCATION_MANUAL;

        return [
            ...parent::rules(),
            'allocation' => ['required', Rule::in([self::ALLOCATION_OLDEST, self::ALLOCATION_MANUAL])],
            'vehicle_id' => [$isManual ? 'prohibited' : 'nullable', 'integer', tenantExists('vehicles')],
            'orders' => [$isManual ? 'required' : 'prohibited', 'array', 'min:1', 'max:100'],
            'orders.*.order_id' => ['required', 'integer', 'distinct'],
            'orders.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
        ];
    }
}
