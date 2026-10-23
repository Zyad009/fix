<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class IndexEmployeeRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'include_voided' => ['nullable', 'boolean'],
        ];
    }
}
