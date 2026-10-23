<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

/**
 * Whether the reason is mandatory depends on the order (completed invoices
 * need one), so the service enforces it.
 */
class CancelRequest extends AbstractFormRequest
{
    protected function requireAtLeastOneField(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
