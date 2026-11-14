<?php

/*
|--------------------------------------------------------------------------
| Role-based access control
|--------------------------------------------------------------------------
|
| The permission catalog is defined here, in code, because every permission
| is enforced in code: routes through the `permission:` middleware, services
| for the finer-grained ones (discount limits, editing completed invoices,
| costs). Roles live in the `roles` table, one set per maintenance center:
| the owner role holds everything, the others hold what the owner picks.
|
*/

$groups = [
    'settings' => ['settings.manage', 'users.manage', 'roles.manage', 'audit.view'],
    'customers' => ['customers.view', 'customers.manage'],
    'catalog' => ['catalog.view', 'catalog.manage', 'stock.manage', 'costs.view'],
    'orders' => [
        'orders.view',
        'orders.view_closed',
        'orders.create',
        'orders.price_override',
        'orders.discount',
        'orders.discount_unlimited',
        'orders.complete',
        'orders.edit_completed',
        'orders.cancel_completed',
    ],
    'payments' => ['payments.collect', 'payments.void', 'opening_balances.manage'],
    'expenses' => ['expenses.view', 'expenses.manage', 'expenses.void'],
    'payroll' => ['payroll.manage'],
    'reports' => ['reports.view'],
];

$cashier = [
    'customers.view',
    'customers.manage',
    'catalog.view',
    'orders.view',
    'orders.view_closed',
    'orders.create',
    'orders.price_override',
    'orders.discount',
    'orders.complete',
    'payments.collect',
    'expenses.view',
    'expenses.manage',
];

return [

    'groups' => $groups,

    'permissions' => array_merge(...array_values($groups)),

    /*
     * Held by the owner role only and never grantable to another role: a role
     * that could edit roles could grant itself everything.
     */
    'owner_only' => ['roles.manage'],

    /*
     * The roles every new maintenance center starts with. Owners can rename
     * and re-permission them (except the owner role) or delete unused ones.
     */
    'defaults' => [
        'owner' => ['is_owner' => true, 'acts_as_technician' => false, 'permissions' => []],

        'cashier' => ['is_owner' => false, 'acts_as_technician' => false, 'permissions' => $cashier],

        // Medium workshops: the same person repairs, sells and collects, and
        // corrects the invoices they closed. Their linked employee record
        // becomes the technician of the orders they open.
        'technician_cashier' => [
            'is_owner' => false,
            'acts_as_technician' => true,
            'permissions' => [...$cashier, 'orders.edit_completed'],
        ],

        'technician' => [
            'is_owner' => false,
            'acts_as_technician' => true,
            'permissions' => ['customers.view', 'catalog.view', 'orders.view', 'orders.create'],
        ],
    ],

];
