<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\UserRole;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexUserRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'role' => ['nullable', Rule::enum(UserRole::class)],
            'branch_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
