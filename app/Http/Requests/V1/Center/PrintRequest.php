<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class PrintRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'size' => ['nullable', 'in:a4,thermal'],
            'output' => ['nullable', 'in:json,pdf'],
        ];
    }
}
