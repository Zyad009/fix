<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\AuditEvent;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexAuditLogRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'auditable_type' => ['nullable', 'string', 'max:50'],
            'auditable_id' => ['nullable', 'integer'],
            'event' => ['nullable', Rule::enum(AuditEvent::class)],
            'user_id' => ['nullable', 'integer'],
            ...$this->periodRules(),
        ];
    }
}
