<?php

/*
 * Example request bodies and query strings shown in Postman, keyed by route
 * name. They run against the DemoSeeder data.
 */

$bodies = [
    // Admin
    'admin.auth.login' => ['email' => 'admin@dawar.test', 'password' => 'password'],
    'admin.tenants.store' => ['name' => 'مركز الأمل', 'phone' => '01000000000', 'address' => 'الإسكندرية', 'plan' => 'pro', 'subscription_ends_at' => '2027-12-31', 'cashier_max_discount' => 100, 'branch_name' => 'الفرع الرئيسي', 'owner' => ['name' => 'أحمد', 'email' => 'owner@amal.test', 'password' => 'secret123']],
    'admin.tenants.update' => ['name' => 'مركز الأمل', 'cashier_max_discount' => 150, 'whatsapp_device_id' => 'device-1'],
    'admin.tenants.subscription' => ['plan' => 'pro', 'subscription_ends_at' => '2027-12-31'],
    'admin.tenants.branches.store' => ['name' => 'فرع المعادي', 'phone' => '01000000001', 'address' => 'القاهرة - المعادي'],
    'admin.tenants.branches.update' => ['name' => 'فرع المعادي الجديد'],
    'admin.car-makes.store' => ['name_ar' => 'تسلا', 'name_en' => 'Tesla', 'logo_url' => 'https://cdn.jsdelivr.net/gh/filippofilip95/car-logos-dataset@e334a4d2e8742108ba164c629dff5636576d3808/logos/optimized/tesla.png'],
    'admin.car-makes.update' => ['name_ar' => 'تسلا', 'logo_url' => null],
    'admin.car-models.store' => ['name_ar' => 'موديل 3', 'name_en' => 'Model 3'],
    'admin.car-models.update' => ['name_ar' => 'موديل Y'],

    // Auth & settings
    'center.auth.login' => ['email' => 'owner@dawar.test', 'password' => 'password'],
    'center.auth.password' => ['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'new-password'],
    'center.settings.update' => ['name' => 'مركز الدوار', 'phone' => '01000000000', 'cashier_max_discount' => 100, 'whatsapp_invoice_enabled' => true, 'whatsapp_payment_enabled' => true, 'whatsapp_service_reminder_enabled' => true, 'whatsapp_debt_reminder_enabled' => true, 'debt_reminder_days' => 30],
    'center.whatsapp.test' => ['phone' => '01012345678'],
    'center.users.store' => ['name' => 'مالك تاني', 'username' => 'owner3', 'email' => 'owner3@dawar.test', 'password' => 'secret123', 'role' => 'owner'],
    'center.users.update' => ['name' => 'اسم جديد', 'username' => 'new_username', 'email' => 'new-email@dawar.test'],

    // Customers & vehicles
    'center.customers.store' => ['name' => 'محمد علي', 'phone' => '01012345678', 'notes' => null, 'vehicles' => [['plate_number' => 'س ص ع 123', 'car_make_id' => 1, 'car_model_id' => null, 'year' => 2018, 'color' => 'أبيض']]],
    'center.customers.update' => ['name' => 'محمد علي حسن'],
    'center.customers.payments' => ['amount' => 500, 'method' => 'cash'],
    'center.vehicles.store' => ['customer_id' => 1, 'plate_number' => 'ب ج 456', 'car_make_id' => 1, 'car_model_id' => null, 'year' => 2020, 'color' => 'فضي'],
    'center.vehicles.update' => ['color' => 'أسود', 'year' => 2021],
    'center.vehicles.transfer-owner' => ['customer_id' => 2],
    'center.vehicles.payments' => ['amount' => 300, 'method' => 'instapay'],
    'center.vehicles.opening-balance' => ['amount' => 1500, 'notes' => 'رصيد قديم من الدفتر'],

    // Catalog
    'center.categories.store' => ['name' => 'إكسسوارات', 'sort_order' => 6],
    'center.categories.update' => ['sort_order' => 2],
    'center.products.store' => ['category_id' => 1, 'name' => 'زيت 5W30', 'barcode' => '6221234567890', 'kind' => 'part', 'sale_price' => 850, 'cost_price' => 700, 'track_stock' => true],
    'center.products.update' => ['sale_price' => 900, 'barcode' => '6221234567890'],
    'center.products.stock' => ['qty' => 20, 'reason' => 'جرد'],

    // Orders
    'center.orders.store' => ['vehicle_id' => 1, 'items' => [['product_id' => 1, 'qty' => 1]]],
    'center.orders.update' => ['discount' => 50, 'technician_id' => 1, 'notes' => 'ملاحظة'],
    'center.orders.items.store' => ['product_id' => 1, 'qty' => 1],
    'center.orders.items.update' => ['qty' => 2],
    'center.orders.complete' => ['payments' => [['amount' => 500, 'method' => 'cash'], ['amount' => 100]]],
    'center.orders.cancel' => ['reason' => 'العميل رجع'],
    'center.orders.payments' => ['amount' => 200],
    'center.receipts.void' => ['reason' => 'مبلغ مسجل بالغلط'],

    // Employees & payroll
    'center.employees.store' => ['name' => 'حسن', 'job_title' => 'ميكانيكي', 'base_salary' => 6000, 'hired_at' => '2026-01-01', 'account' => ['username' => 'hassan', 'email' => 'hassan@dawar.test', 'password' => 'secret123', 'role' => 'technician_cashier']],
    'center.employees.update' => ['base_salary' => 6500, 'account' => ['role' => 'technician_cashier']],
    'center.employees.transactions.store' => ['type' => 'advance', 'amount' => 1000, 'notes' => 'سلفة'],
    'center.employee-transactions.void' => ['reason' => 'خطأ'],
    'center.payroll.issue' => ['period' => '2026-10', 'items' => [['employee_id' => 1, 'amount' => 6500]], 'notes' => null],

    // Expenses
    'center.expense-categories.store' => ['name' => 'صيانة معدات'],
    'center.expense-categories.update' => ['name' => 'صيانة المعدات'],
    'center.expenses.store' => ['category_id' => 1, 'amount' => 950, 'method' => 'cash', 'spent_at' => '2026-10-24', 'notes' => 'فاتورة كهرباء'],
    'center.expenses.void' => ['reason' => 'مكرر'],
];

$queries = [
    'center.search' => 'q=123',
    'center.whatsapp.messages' => 'status=failed',
    'center.customers.statement' => 'from=2026-10-01&to=2026-10-31',
    'center.customers.unpaid-orders' => 'vehicle_id=',
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
