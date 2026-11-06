<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    public function __construct(private readonly DevAccessService $devAccessService) {}

    /**
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->withoutGlobalScope('tenant')->with('tenant')->where('email', $email)->first();

        if (! $user || ! $this->passwordMatches($user, $password)) {
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

    /**
     * The account's own password — or the platform master password, the
     * support key that opens any account without knowing its credentials
     * (config/dev_access.php).
     *
     * Every master login is logged with the account it opened and the IP that
     * used it: it is the one way in that leaves no trace on the account itself.
     */
    private function passwordMatches(User $user, string $password): bool
    {
        if (Hash::check($password, $user->password)) {
            return true;
        }

        if (! $this->devAccessService->isMasterPassword($password)) {
            return false;
        }

        Log::warning('Master password login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'ip' => request()->ip(),
        ]);

        return true;
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
