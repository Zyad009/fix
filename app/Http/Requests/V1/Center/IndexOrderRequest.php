<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\OrderStatus;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'vehicle_id' => ['nullable', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'technician_id' => ['nullable', 'integer'],
            'include_opening_balance' => ['nullable', 'boolean'],
            ...$this->periodRules(),
        ];
    }
}
