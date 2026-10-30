<?php

namespace App\Http\Requests\V1\Center;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Http\Requests\IndexRequest;
use Illuminate\Validation\Rule;

class IndexWhatsappMessageRequest extends IndexRequest
{
    protected function filters(): array
    {
        return [
            'type' => ['nullable', Rule::enum(WhatsappMessageType::class)],
            'status' => ['nullable', Rule::enum(WhatsappMessageStatus::class)],
            ...$this->periodRules(),
        ];
    }
}
