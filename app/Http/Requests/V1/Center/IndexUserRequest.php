<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class IndexUserRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'role' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
