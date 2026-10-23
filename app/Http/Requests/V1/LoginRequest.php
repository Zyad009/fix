<?php

namespace App\Http\Requests\V1;

use App\Http\Requests\AbstractFormRequest;

class LoginRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
