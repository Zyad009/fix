<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class ReportRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            ...$this->periodRules(),
            'sort' => ['nullable', 'in:amount,oldest'],
            'days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ];
    }
}
