<?php

namespace App\Http\Requests\V1\Admin;

use App\Http\Requests\AbstractFormRequest;

class SubscriptionRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan' => ['sometimes', 'required', 'string', 'max:50'],
            'subscription_ends_at' => ['present', 'nullable', 'date'],
        ];
    }
}
