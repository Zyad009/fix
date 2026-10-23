<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\IndexRequest;

class IndexTenantRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'expired' => ['nullable', 'boolean'],
        ];
    }
}
