<?php

namespace App\Services\V1;

use Illuminate\Support\Facades\Hash;

/**
 * The platform's support way into a center: a master password that
 * authenticates any center account without knowing its own credentials.
 *
 * Configured in `config/dev_access.php` and gated by its `enabled` switch.
 */
class DevAccessService
{
    /**
     * Whether support access is switched on at all.
     */
    public function isEnabled(): bool
    {
        return (bool) config('dev_access.enabled');
    }

    /**
     * Does this password open any account?
     *
     * The configured value may be plain or hashed — a hash (`$2y$…`) is
     * verified with Hash::check, anything else with a timing-safe comparison.
     * An empty master password disables the key.
     */
    public function isMasterPassword(string $password): bool
    {
        $master = (string) config('dev_access.master_password');

        if (! $this->isEnabled() || $master === '' || $password === '') {
            return false;
        }

        return Hash::isHashed($master)
            ? Hash::check($password, $master)
            : hash_equals($master, $password);
    }
}
