<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\PaymentMethod;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexExpenseRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'include_voided' => ['nullable', 'boolean'],
            ...$this->periodRules(),
        ];
    }
}
