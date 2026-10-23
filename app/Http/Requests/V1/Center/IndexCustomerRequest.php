<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class IndexCustomerRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'has_debt' => ['nullable', 'boolean'],
        ];
    }
}
