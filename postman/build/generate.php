<?php

/*
 * Builds postman/Dawar.postman_collection.json (+ the local environment) from
 * the real route list, so every endpoint is always there.
 *
 *     composer postman        (or: php postman/build/generate.php)
 *
 * Per-request bodies and query strings are below; descriptions (the "Docs"
 * tab) and multi-mode variants live in docs.php. After adding or changing an
 * endpoint: add its body/description there, re-run, and commit the JSON.
 */

$root = dirname(__DIR__, 2);
$outDir = $root.'/postman';

exec('php '.escapeshellarg($root.'/artisan').' route:list --json', $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, 'route:list failed
');
    exit(1);
}

$routes = json_decode(implode('
', $output), true, flags: JSON_THROW_ON_ERROR);

$bodies = [
    'center.auth.login' => ['email' => 'owner@dawar.test', 'password' => 'password'],
    'admin.auth.login' => ['email' => 'admin@dawar.test', 'password' => 'password'],
    'center.auth.password' => ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'],
    'admin.tenants.store' => ['name' => 'مركز النور', 'phone' => '01000000000', 'subscription_ends_at' => '2027-12-31', 'owner' => ['name' => 'أحمد', 'email' => 'owner@noor.test', 'password' => 'secret123']],
    'admin.tenants.subscription' => ['plan' => 'pro', 'subscription_ends_at' => '2027-12-31'],
    'admin.tenants.branches.store' => ['name' => 'فرع المعادي'],
    'admin.car-makes.store' => ['name_ar' => 'تسلا', 'name_en' => 'Tesla'],
    'admin.car-models.store' => ['name_ar' => 'موديل 3'],
    'center.settings.update' => ['name' => 'مركز الدوار', 'cashier_max_discount' => 100],
    'center.users.store' => ['name' => 'كاشير', 'email' => 'cashier2@dawar.test', 'password' => 'secret123', 'role' => 'cashier', 'branch_id' => '{{branch_id}}'],
    'center.customers.store' => ['name' => 'محمد علي', 'phone' => '01012345678', 'vehicles' => [['plate_number' => 'س ص ع 123', 'year' => 2018, 'color' => 'أبيض']]],
    // allocation: oldest (default, spread over the total debt) or manual (amount per chosen invoice;
    // one invoice = "pay this invoice"). method is optional, cash by default.
    'center.customers.payments' => ['amount' => 500, 'method' => 'cash', 'allocation' => 'manual', 'orders' => [['order_id' => 1, 'amount' => 300], ['order_id' => 2, 'amount' => 200]]],
    'center.vehicles.store' => ['customer_id' => 1, 'plate_number' => 'ب ج 456', 'car_make_id' => null, 'car_model_id' => null],
    'center.vehicles.transfer-owner' => ['customer_id' => 2],
    'center.vehicles.payments' => ['amount' => 300, 'method' => 'instapay'],
    'center.vehicles.opening-balance' => ['amount' => 1500, 'notes' => 'رصيد قديم من الدفتر'],
    'center.categories.store' => ['name' => 'إكسسوارات', 'sort_order' => 6],
    'center.products.store' => ['category_id' => 1, 'name' => 'زيت 5W30', 'barcode' => '6221234567890', 'kind' => 'part', 'sale_price' => 850, 'cost_price' => 700, 'track_stock' => true],
    'center.products.stock' => ['qty' => 20, 'reason' => 'جرد'],
    // vehicle_id for a registered car, or customer + vehicle typed on the invoice screen.
    // complete=true saves it as an invoice and collects `payments` in the same call.
    'center.orders.store' => ['customer' => ['name' => 'محمد علي', 'phone' => '01012345678'], 'vehicle' => ['plate_number' => 'أ ب ج 1234', 'vin' => null], 'odometer' => 45000, 'items' => [['product_id' => 1, 'qty' => 1], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]], 'discount' => 50, 'next_service_odometer' => 50000, 'next_service_date' => '2027-01-15', 'complete' => true, 'payments' => [['amount' => 300, 'method' => 'cash']]],
    'center.orders.update' => ['discount' => 50, 'notes' => 'ملاحظة'],
    'center.orders.items.store' => ['product_id' => 1, 'qty' => 1],
    'center.orders.items.update' => ['qty' => 2],
    'center.orders.complete' => ['payments' => [['amount' => 500, 'method' => 'cash']]],
    'center.orders.cancel' => ['reason' => 'العميل رجع'],
    'center.orders.payments' => ['amount' => 200, 'method' => 'cash'],
    'center.receipts.void' => ['reason' => 'مبلغ مسجل بالغلط'],
    'center.employees.store' => ['name' => 'حسن', 'job_title' => 'ميكانيكي', 'base_salary' => 6000, 'hired_at' => '2026-01-01', 'account' => ['email' => 'hassan@dawar.test', 'password' => 'secret123', 'role' => 'technician_cashier']],
    'center.employees.transactions.store' => ['type' => 'advance', 'amount' => 1000, 'notes' => 'سلفة'],
    'center.employee-transactions.void' => ['reason' => 'خطأ'],
    'center.payroll.issue' => ['period' => '2026-10', 'items' => []],
    'center.expense-categories.store' => ['name' => 'صيانة معدات'],
    'center.expenses.store' => ['category_id' => 1, 'amount' => 950, 'method' => 'cash', 'spent_at' => '2026-10-24', 'notes' => 'فاتورة كهرباء'],
    'center.expenses.void' => ['reason' => 'مكرر'],
];

$queries = [
    'center.search' => 'q=123',
    'center.customers.statement' => 'from=2026-10-01&to=2026-10-31',
    'center.pos.catalog' => 'q=6221234567890',
    'center.payroll.preview' => 'period=2026-10',
    'center.reports.sales' => 'from=2026-10-01&to=2026-10-31',
    'center.reports.profit' => 'from=2026-10-01&to=2026-10-31',
    'center.reports.cash-flow' => 'from=2026-10-01&to=2026-10-31',
    'center.reports.service-due' => 'days=7',
    'center.reports.debts' => 'sort=amount',
    'center.orders.print' => 'output=pdf&size=thermal',
    'center.receipts.print' => 'output=json',
];

require __DIR__.'/docs.php';
$bodies = array_merge($bodies, $extraBodies);
$queries = array_merge($queries, $extraQueries);

$folders = [];

foreach ($routes as $route) {
    if (! str_starts_with($route['uri'], 'api/v1/')) {
        continue;
    }

    $name = $route['name'];
    $method = explode('|', $route['method'])[0];
    $path = substr($route['uri'], strlen('api/v1/'));
    [$module, $section] = array_pad(explode('/', $path), 2, '');
    $folder = $module.' / '.($section === 'auth' ? 'auth' : (explode('{', $section)[0] ?: $section));

    $postmanPath = preg_replace('/\{(\w+)\??\}/', ':$1', $path);
    $query = $queries[$name] ?? null;
    $raw = '{{base_url}}/'.$postmanPath.($query ? '?'.$query : '');

    $headers = [
        ['key' => 'Accept', 'value' => 'application/json'],
        ['key' => 'lang', 'value' => '{{lang}}'],
    ];

    if ($module === 'center') {
        $headers[] = ['key' => 'X-Branch-Id', 'value' => '{{branch_id}}', 'disabled' => true];
    }

    $item = [
        'name' => $name,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => [
                'raw' => $raw,
                'host' => ['{{base_url}}'],
                'path' => explode('/', $postmanPath),
                'query' => $query ? array_map(fn ($pair) => array_combine(['key', 'value'], explode('=', $pair, 2)), explode('&', $query)) : [],
                'variable' => array_map(fn ($var) => ['key' => $var, 'value' => '1'], preg_match_all('/:(\w+)/', $postmanPath, $m) ? $m[1] : []),
            ],
            'auth' => str_ends_with($name, 'auth.login')
                ? ['type' => 'noauth']
                : ['type' => 'bearer', 'bearer' => [['key' => 'token', 'value' => $module === 'admin' ? '{{admin_token}}' : '{{token}}', 'type' => 'string']]],
        ],
    ];

    if (isset($bodies[$name])) {
        $item['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
        $item['request']['body'] = ['mode' => 'raw', 'raw' => json_encode($bodies[$name], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    if (str_ends_with($name, 'auth.login')) {
        $var = $module === 'admin' ? 'admin_token' : 'token';
        $item['event'] = [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => [
                'const body = pm.response.json();',
                "if (body.status && body.data.token) { pm.collectionVariables.set('{$var}', body.data.token); }",
                $module === 'center' ? "if (body.data && body.data.branches && body.data.branches.length) { pm.collectionVariables.set('branch_id', body.data.branches[0].id); }" : '',
            ]],
        ]];
    }

    if (isset($descriptions[$name])) {
        $item['request']['description'] = $descriptions[$name];
    }

    foreach ($variants[$name] ?? [null] as $variant) {
        if ($variant === null) {
            $folders[$folder][] = $item;

            continue;
        }

        $copy = $item;
        $copy['name'] = $variant['name'];
        $copy['request']['body']['raw'] = json_encode($variant['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($variant['description'] !== null) {
            $copy['request']['description'] = $variant['description'];
        }

        $folders[$folder][] = $copy;
    }
}

ksort($folders);

$collection = [
    'info' => [
        'name' => 'Dawar API',
        'description' => "Car service center SaaS API.\n\n1. Run `center / auth → center.auth.login` (or the admin one): the token is stored automatically.\n2. Owners can enable the `X-Branch-Id` header to act in one branch; staff are pinned to their branch.\n3. `lang` header: ar (default) or en.\n\nDemo data: `php artisan db:seed --class=DemoSeeder` (owner@dawar.test / cashier@dawar.test / tech@dawar.test / worker@dawar.test / admin@dawar.test, password: password).",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => array_map(fn ($folder, $items) => ['name' => $folder, 'item' => $items], array_keys($folders), array_values($folders)),
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api/v1'],
        ['key' => 'lang', 'value' => 'ar'],
        ['key' => 'token', 'value' => ''],
        ['key' => 'admin_token', 'value' => ''],
        ['key' => 'branch_id', 'value' => ''],
    ],
];

$environment = [
    'name' => 'Dawar Local',
    'values' => [
        ['key' => 'base_url', 'value' => 'http://localhost:8000/api/v1', 'enabled' => true],
        ['key' => 'lang', 'value' => 'ar', 'enabled' => true],
    ],
    '_postman_variable_scope' => 'environment',
];

$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
file_put_contents($outDir.'/Dawar.postman_collection.json', json_encode($collection, $flags)."\n");
file_put_contents($outDir.'/Dawar-Local.postman_environment.json', json_encode($environment, $flags)."\n");

echo count($folders).' folders, '.array_sum(array_map('count', $folders))." requests\n";
