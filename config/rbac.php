<?php

/*
|--------------------------------------------------------------------------
| Role-based access control
|--------------------------------------------------------------------------
|
| Single source of truth for what each role may do (brief.md §5). Routes
| enforce these through the `permission:` middleware; services check the
| finer-grained ones (discount limits, editing completed invoices, costs).
| `*` grants everything.
|
*/

return [

    'permissions' => [
        'settings.manage',
        'users.manage',
        'audit.view',

        'customers.view',
        'customers.manage',

        'catalog.view',
        'catalog.manage',
        'stock.manage',
        'costs.view',

        'orders.view',
        'orders.view_closed',
        'orders.create',
        'orders.price_override',
        'orders.discount',
        'orders.discount_unlimited',
        'orders.complete',
        'orders.edit_completed',
        'orders.cancel_completed',

        'payments.collect',
        'payments.void',
        'opening_balances.manage',

        'expenses.view',
        'expenses.manage',
        'expenses.void',

        'payroll.manage',

        'reports.view',
    ],

    'roles' => [
        'owner' => ['*'],

        'cashier' => [
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
        ],

        // Medium workshops: the same person repairs, sells and collects.
        // Same permissions as the cashier; the difference is the linked
        // employee record that becomes the technician of their orders.
        'technician_cashier' => [
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
        ],

        'technician' => [
            'customers.view',
            'catalog.view',
            'orders.view',
            'orders.create',
        ],
    ],

];
