<?php

/*
 * Per-request documentation for the Postman collection (Markdown, Arabic for
 * the frontend team) and extra variants for endpoints with several modes.
 */

$descriptions = [
    // ---------- Admin ----------
    'admin.car-makes.store' => <<<'MD'
إضافة ماركة عربية للمنصة كلها (Super Admin بس).

| الحقل | النوع | ملاحظات |
|---|---|---|
| `name_ar` | string | **مطلوب**، ومايتكررش |
| `name_en` | string | اختياري |
| `logo_url` | url (https) | اختياري: رابط صورة اللوجو |

الماركات الـ 30 الأساسية جاية من الـ seeder بلوجوهاتها جاهزة.
MD,
    'admin.car-makes.update' => <<<'MD'
تعديل ماركة. ابعت الحقول اللي عايز تغيّرها بس (`name_ar`, `name_en`, `logo_url`).
عشان تشيل اللوجو ابعت `"logo_url": null` مع حقل تاني (زي `name_ar`)، لأن request كله null بيترفض.
MD,

    // ---------- Lookups ----------
    'center.car-makes.index' => <<<'MD'
**المصدر الوحيد للماركات والموديلات في الفرونت** (عشان الإدخال يبقى موحد).

كل ماركة: `id`, `name_ar`, `name_en`, `logo_url`, و `models[]` (`id`, `name_ar`, `name_en`).
في العربية ابعت `car_make_id` و `car_model_id` (من هنا) و `year` (سنة الموديل، رقم من 1950 للسنة الجاية).
MD,

    // ---------- Catalog ----------
    'center.pos.catalog' => <<<'MD'
شاشة "إنشاء فاتورة" في call واحد.

**الرد:**
```json
{ "next_order_no": 8, "categories": [ { "id": 1, "name": "زيوت", "products": [ ... ] } ] }
```
- `next_order_no`: رقم الفاتورة **المتوقع** للعرض بس (زي INV-1008، والـ prefix شغل الفرونت). الرقم الفعلي بيرجع مع الحفظ.
- كل منتج: `id`, `name`, `barcode`, `kind`, `sale_price`, `image_url`, `stock` (رصيد الفرع).

**`q` (اختياري):** باركود (من الماسح) أو جزء من الاسم.
- لو مطابق لباركود منتج نشط بالظبط → بيرجع المنتج ده بس.
- غير كده → بحث بالاسم، وبيرجع التصنيفات اللي فيها نتائج بس.
MD,
    'center.products.store' => <<<'MD'
إضافة منتج أو خدمة (owner بس). ممكن `multipart/form-data` لو هتبعت صورة في `image`.

| الحقل | ملاحظات |
|---|---|
| `name`, `kind` (`part`/`service`), `sale_price` | **مطلوبين** |
| `barcode` | اختياري، **unique جوه المركز** (المنتج المحذوف بيحرره) |
| `category_id`, `cost_price`, `track_stock` | اختياري. الخدمة عمرها ما بتتبع مخزون |
| `image` | jpg/png/webp لحد 2MB |
MD,
    'center.products.update' => 'تعديل منتج (owner). نفس حقول الإضافة، كلها اختيارية. الصورة بتتغير من `POST /products/{id}/image`.',
    'center.products.index' => 'قايمة المنتجات. `q` بيدوّر في الاسم **أو الباركود** (مطابق). فلاتر: `category_id`, `kind`, `is_active`. و `format=excel` للتصدير.',

    // ---------- Orders ----------
    'center.orders.store' => <<<'MD'
**زرار "حفظ الفاتورة".** بيعمل الأوردر، ولو `complete: true` بيقفله فاتورة ويحصّل في نفس الـ call (transaction واحدة: لو أي حاجة فشلت مفيش حاجة بتتسجل).

**العربية — واحد من الاتنين:**
- `vehicle_id`: عربية متسجلة (من البحث).
- أو `customer` + `vehicle` (عميل/عربية من الشاشة):
  - `customer`: `{id}` لعميل موجود، أو `{name, phone}`. لو التليفون متسجل بيتستخدم العميل الموجود.
  - `vehicle`: `{plate_number (مطلوب), vin, car_make_id, car_model_id, year, color}`. لو اللوحة متسجلة لنفس العميل بتتستخدم؛ لو **لعميل تاني → 422** (يختارها من البحث أو ينقل الملكية).
  - محتاج صلاحية إضافة عملاء (الفني العادي → 403).

**باقي الحقول (اختيارية):** `odometer`, `technician_id`, `discount` (مبلغ — النسبة يحسبها الفرونت), `next_service_odometer`, `next_service_date`, `notes`, `items[]`:
- من الكتالوج: `{product_id, qty}` (+ `unit_price` لو عنده صلاحية تغيير السعر).
- صنف حر: `{name, qty, unit_price, kind?}`.

**القفل:** `complete: true` + `payments: [{amount, method?}]` (الـ method افتراضي `cash`). `payments` ممنوعة من غير `complete`.

**الفني الافتراضي:** لو اللي فاتح "فني" أو "فني وكاشير" ومابعتش `technician_id` → بيتحط هو.

**الرد:** `{order, warnings[]}`. الأوردر فيه `parts_total` (المنتجات) و `services_total` (الصيانة) و `subtotal`, `discount`, `total`, `paid`, `remaining`.
`warnings`: العداد أقل من آخر قراءة، أو المخزون نقص (من غير منع).
MD,
    'center.orders.complete' => 'قفل أوردر مفتوح → فاتورة. `payments` اختيارية: `[{amount, method?}]`، والـ method افتراضي `cash`. المخزون بيتخصم هنا.',
    'center.orders.payments' => 'دفعة على أوردر واحد من شاشة الأوردرات (مسموح عربون على أوردر مفتوح). `amount` مطلوب، و `method` اختياري (افتراضي `cash`). مقفول على فرع المستخدم — من صفحة العميل استخدم `customers/{id}/payments`.',

    // ---------- Customers ----------
    'center.customers.statement' => <<<'MD'
**كشف حساب العميل** (زي كشف البنك) من كل الفروع. صلاحية: owner / كاشير / فني وكاشير.

**Query (كله اختياري):** `from`, `to` (Y-m-d بتوقيت المركز), `vehicle_id`, `format=excel`.

**الرد:**
```json
{
  "customer": {"id", "name", "phone"},
  "summary": {"total_invoiced", "total_paid", "balance", "open_orders_deposits", "vehicles": [...]},
  "period": {"from", "to"},
  "opening_balance": 0,
  "entries": [
    {"type": {"value": "invoice|opening_balance|payment", "label"}, "date": 1761300000,
     "reference": 12, "order_id": 5, "receipt_id": null, "vehicle_plate", "branch",
     "method": null, "debit": 300, "credit": 0, "balance": 300}
  ],
  "closing_balance": 600
}
```
- `debit` = عليه (فاتورة / رصيد افتتاحي)، `credit` = له (تحصيل، صف لكل إيصال)، `balance` = الرصيد بعد الحركة.
- `reference` = رقم الفاتورة أو رقم الإيصال حسب النوع.
- `closing_balance` = المديونية الحالية بالظبط (لو من غير `to`).
- الأوردرات الملغية مش ظاهرة. العربون على أوردر لسه مفتوح في `summary.open_orders_deposits` بس.
MD,
    'center.customers.unpaid-orders' => <<<'MD'
**الفواتير اللي عليها مديونية** (لشاشة "سداد فاتورة معينة" و"أختار الفواتير بنفسي").
من كل الفروع، من **الأقدم للأحدث**. `vehicle_id` اختياري.

**الرد:** `{ "total_due": 700, "orders": [ {id, order_no, branch, vehicle, total, paid, remaining, completed_at, ...} ] }`
MD,
    'center.customers.orders.show' => 'عرض فاتورة من فواتير العميل **حتى لو من فرع تاني** (كاملة بالبنود والمدفوعات). لو الفاتورة مش للعميل ده → 404.',
    'center.customers.payments' => 'تسجيل دفعة من صفحة العميل — شوف الـ 3 requests اللي جنبها لكل نوع سداد.',
    'center.vehicles.payments' => 'تحصيل على فواتير العربية دي (اللي على مالكها الحالي) من الأقدم. `amount` مطلوب، `method` اختياري (افتراضي `cash`).',

    // ---------- Employees ----------
    'center.employees.store' => <<<'MD'
إضافة موظف (owner)، **ومعاه حساب الدخول لو محتاج** (زي fitZone).

| الحقل | ملاحظات |
|---|---|
| `name`, `base_salary` | **مطلوبين** |
| `phone`, `job_title`, `hired_at` | اختياري |
| `account` | اختياري: `{email, password, role}` |

`account.role`: `cashier` أو `technician` أو `technician_cashier` (فني وكاشير). **`owner` ممنوع.**
الحساب بيتعمل في نفس فرع الموظف وبنفس اسمه. الرد فيه `account: {id, email, role, is_active}`.
MD,
    'center.employees.update' => <<<'MD'
تعديل موظف. لو معاه `account`:
- الموظف **عنده حساب** → بيتعدّل (أي حقل من `email`, `password`, `role`). تغيير الباسورد بيطلّعه من الأجهزة التانية.
- **معندوش** → بيتعمله حساب (`email` + `password` + `role` مطلوبين).

تغيير اسم الموظف بيغيّر اسم الحساب.
MD,
    'center.employees.toggle' => 'إيقاف/تفعيل موظف. **الإيقاف بيوقف حساب دخوله ويطلّعه فوراً.**',
    'center.users.store' => 'حسابات من غير سجل موظف (زي owner إضافي). حسابات الموظفين الطبيعي تتعمل من `employees` (الحقل `account`). الأدوار: `owner`, `cashier`, `technician`, `technician_cashier`.',
];

$variants = [
    'center.customers.payments' => [
        [
            'name' => 'center.customers.payments — سداد من الإجمالي (الأقدم أولاً)',
            'body' => ['amount' => 500, 'method' => 'cash', 'notes' => null],
            'description' => <<<'MD'
**سداد من إجمالي المديونية — توزيع تلقائي من الأقدم.**

- `amount` مطلوب، `method` اختياري (افتراضي `cash`)، `notes` اختياري.
- `allocation` مش لازم تتبعت (الافتراضي `oldest`).
- `vehicle_id` اختياري: يقصر التوزيع على فواتير عربية واحدة.
- المبلغ أكبر من إجمالي المديونية → 422.

**الرد:** الإيصال (`receipt_no`, `amount`, `method`, `payments[]` بالتوزيع على كل فاتورة والمتبقي عليها). يتطبع من `receipts/{id}/print`.
MD,
        ],
        [
            'name' => 'center.customers.payments — أختار الفواتير بنفسي',
            'body' => ['amount' => 500, 'method' => 'instapay', 'allocation' => 'manual', 'orders' => [['order_id' => 1, 'amount' => 300], ['order_id' => 2, 'amount' => 200]]],
            'description' => <<<'MD'
**سداد من إجمالي المديونية — المستخدم يوزّع بنفسه.**

- `allocation: "manual"` و `orders: [{order_id, amount}]` (الفواتير من `unpaid-orders`).
- **مجموع المبالغ لازم = `amount`** وإلا 422.
- كل مبلغ مايزيدش عن المتبقي على فاتورته.
- الفاتورة لازم تكون للعميل ده ومقفولة ولسه عليها فلوس، وإلا 422 **ومفيش حاجة بتتسجل**.
- ممكن فواتير من فروع مختلفة؛ الإيصال بيتسجل في درج فرع المستخدم.
- `vehicle_id` ممنوع هنا.

نصيحة للفرونت: لما يعلّم فاتورة املأ مبلغها بالمتبقي عليها، وخلّي المستخدم يعدّل.
MD,
        ],
        [
            'name' => 'center.customers.payments — سداد فاتورة معينة',
            'body' => ['amount' => 400, 'allocation' => 'manual', 'orders' => [['order_id' => 2, 'amount' => 400]]],
            'description' => <<<'MD'
**سداد فاتورة معينة** = `manual` بفاتورة واحدة (المبلغ = `amount`).
`method` مش متبعت هنا → بيتسجل `cash`.
MD,
        ],
    ],
    'center.orders.store' => [
        [
            'name' => 'center.orders.store — عربية متسجلة (أوردر مفتوح)',
            'body' => ['vehicle_id' => 1, 'odometer' => 52000, 'technician_id' => null, 'items' => [['product_id' => 1, 'qty' => 1], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 200]], 'discount' => 0, 'next_service_odometer' => 57000, 'next_service_date' => '2027-01-15'],
            'description' => 'أوردر مفتوح لعربية متسجلة (العربية لسه في الشغل). يتقفل بعدين من `orders/{id}/complete`. التفاصيل الكاملة للحقول في الـ request التاني.',
        ],
        [
            'name' => 'center.orders.store — عميل جديد + حفظ الفاتورة وتحصيل',
            'body' => ['customer' => ['name' => 'محمد علي', 'phone' => '01012345678'], 'vehicle' => ['plate_number' => 'أ ب ج 1234', 'vin' => null, 'car_make_id' => 1, 'car_model_id' => null, 'year' => 2019], 'odometer' => 45000, 'items' => [['product_id' => 1, 'qty' => 1], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]], 'discount' => 50, 'next_service_odometer' => 50000, 'next_service_date' => '2027-01-15', 'complete' => true, 'payments' => [['amount' => 300, 'method' => 'cash']]],
            'description' => null, // uses the main description
        ],
    ],
];

$extraBodies = [
    'admin.car-makes.store' => ['name_ar' => 'تسلا', 'name_en' => 'Tesla', 'logo_url' => 'https://cdn.jsdelivr.net/gh/filippofilip95/car-logos-dataset@e334a4d2e8742108ba164c629dff5636576d3808/logos/optimized/tesla.png'],
    'admin.car-makes.update' => ['name_ar' => 'تسلا', 'logo_url' => null],
    'center.products.update' => ['sale_price' => 900, 'barcode' => '6221234567890'],
    'center.employees.update' => ['base_salary' => 6500, 'account' => ['role' => 'technician_cashier']],
    'center.orders.complete' => ['payments' => [['amount' => 500, 'method' => 'cash'], ['amount' => 100]]],
    'center.orders.payments' => ['amount' => 200],
    'center.vehicles.payments' => ['amount' => 300, 'method' => 'instapay'],
];

$extraQueries = [
    'center.customers.unpaid-orders' => 'vehicle_id=',
];
