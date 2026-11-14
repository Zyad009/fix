<?php

namespace App\Enums;

use App\Support\Traits\EnumTrait;

/**
 * Keys and default names of the roles every maintenance center starts with
 * (config/rbac.php `defaults`). What a role may do is stored on the center's
 * own `roles` row, see App\Models\Role.
 */
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
}
