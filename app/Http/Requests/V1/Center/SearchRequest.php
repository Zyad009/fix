<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\IndexRequest;

class SearchRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }
}
