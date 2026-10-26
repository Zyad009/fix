<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum UserRole: string
{
    use EnumTrait;

    case Owner = 'owner';
    case Cashier = 'cashier';
    case Technician = 'technician';
    case TechnicianCashier = 'technician_cashier';

    public const LABELS = [
        'owner' => ['ar' => 'المالك', 'en' => 'Owner'],
        'cashier' => ['ar' => 'كاشير', 'en' => 'Cashier'],
        'technician' => ['ar' => 'فني', 'en' => 'Technician'],
        'technician_cashier' => ['ar' => 'فني وكاشير', 'en' => 'Technician & cashier'],
    ];

    /**
     * Owners act across every branch; staff are pinned to one.
     */
    public function isBranchBound(): bool
    {
        return $this !== self::Owner;
    }

    /**
     * Roles that do the work themselves: orders they open default to their
     * own employee record as the technician.
     */
    public function actsAsTechnician(): bool
    {
        return in_array($this, [self::Technician, self::TechnicianCashier], true);
    }

    /**
     * Staff roles that can be given to an employee's login account.
     *
     * @return list<string>
     */
    public static function staffValues(): array
    {
        return array_values(array_filter(self::values(), fn (string $role): bool => $role !== self::Owner->value));
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return config('rbac.roles.'.$this->value, []);
    }
}
