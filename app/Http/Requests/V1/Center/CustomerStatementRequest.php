<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class CustomerStatementRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            ...$this->periodRules(),
            'vehicle_id' => ['nullable', 'integer'],
        ];
    }
}
