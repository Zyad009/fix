<?php

namespace App\Services\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminAuthService
{
    /**
     * @return array{token: string, admin: Admin}
     */
    public function login(string $email, string $password): array
    {
        $admin = Admin::query()->where('email', $email)->first();

        if (! $admin || ! Hash::check($password, $admin->password)) {
            throw new BusinessRuleException(__('messages.invalid_credentials'), 401, errorCode: 'invalid_credentials');
        }

        if (! $admin->is_active) {
            throw new BusinessRuleException(__('messages.account_inactive'), 403, errorCode: 'account_inactive');
        }

        return [
            'token' => $admin->createToken('admin', ['admin'])->plainTextToken,
            'admin' => $admin,
        ];
    }
}
