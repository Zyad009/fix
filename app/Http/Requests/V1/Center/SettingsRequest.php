<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class SettingsRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'cashier_max_discount' => ['sometimes', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'whatsapp_invoice_enabled' => ['sometimes', 'boolean'],
            'whatsapp_payment_enabled' => ['sometimes', 'boolean'],
            'whatsapp_service_reminder_enabled' => ['sometimes', 'boolean'],
            'whatsapp_debt_reminder_enabled' => ['sometimes', 'boolean'],
            'debt_reminder_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ];
    }
}
