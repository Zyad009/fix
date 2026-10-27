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
        return self::paymentRules();
    }

    /**
     * Money collected while completing (shared with the one-call invoice).
     *
     * @param  list<string>  $extra  extra rules for the `payments` array itself
     * @return array<string, mixed>
     */
    public static function paymentRules(array $extra = []): array
    {
        return [
            'payments' => ['sometimes', ...$extra, 'array', 'max:5'],
            'payments.*.amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'payments.*.method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ];
    }
}
