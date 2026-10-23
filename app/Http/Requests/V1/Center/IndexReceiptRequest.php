<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexReceiptRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'customer_id' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'voided' => ['nullable', 'boolean'],
            ...$this->periodRules(),
        ];
    }
}
