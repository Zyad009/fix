<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class IndexRoutineServiceRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
