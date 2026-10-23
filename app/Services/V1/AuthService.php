<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    /**
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->withoutGlobalScope('tenant')->with('tenant')->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new BusinessRuleException(__('messages.invalid_credentials'), 401, errorCode: 'invalid_credentials');
        }

        if (! $user->is_active || ! $user->tenant->is_active) {
            throw new BusinessRuleException(__('messages.account_inactive'), 403, errorCode: 'account_inactive');
        }

        tenantContext()->set($user->tenant);

        return [
            'token' => $user->createToken('center', ['center'])->plainTextToken,
            'user' => $user,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new BusinessRuleException(__('messages.current_password_incorrect'), 422);
        }

        $user->update(['password' => $newPassword]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
    }
}
