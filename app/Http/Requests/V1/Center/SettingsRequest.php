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
        ];
    }
}
