<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

enum UserRole: string
{
    use EnumTrait;

    case Owner = 'owner';
    case Cashier = 'cashier';
    case Technician = 'technician';

    public const LABELS = [
        'owner' => ['ar' => 'المالك', 'en' => 'Owner'],
        'cashier' => ['ar' => 'كاشير', 'en' => 'Cashier'],
        'technician' => ['ar' => 'فني', 'en' => 'Technician'],
    ];

    /**
     * Owners act across every branch; staff are pinned to one.
     */
    public function isBranchBound(): bool
    {
        return $this !== self::Owner;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return config('rbac.roles.'.$this->value, []);
    }
}
