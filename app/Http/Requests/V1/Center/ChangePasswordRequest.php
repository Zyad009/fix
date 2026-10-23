<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class ChangePasswordRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', 'different:current_password'],
        ];
    }
}
