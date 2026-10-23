<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\ProductKind;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::enum(ProductKind::class)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
