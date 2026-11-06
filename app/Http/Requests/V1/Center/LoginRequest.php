<?php

namespace App\Http\Requests\V1\Center;

use App\Http\Requests\AbstractFormRequest;

class LoginRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'required_without:username', 'email', 'max:255'],
            'username' => ['nullable', 'required_without:email', 'string', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
