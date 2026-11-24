<?php

/*
 * Hand-written part of the Postman docs: what each endpoint does, in Arabic
 * and English (Markdown). The fields table and the permission/roles line are
 * generated from the code (introspect.php) and appended automatically.
 *
 * $descriptions[route name] = ['ar' => ..., 'en' => ...]
 * $variants[route name]     = extra requests for endpoints with several modes
 * $fieldsOnly[route name]   = the query/body fields the endpoint really reads
 *                             (shared list requests accept more than each uses)
 * $fieldNotes[route name]   = per-field notes (defaults, conditions)
 * $requestInput[route name] = input that switches conditional rules on
 */

$descriptions = [

    // =====================================================================
    // Admin
    // =====================================================================

    'admin.auth.login' => [
        'ar' => 'دخول الـ Super Admin. بيرجّع `token` (ability `admin`) يتبعت في `Authorization: Bearer`. التوكن ده مايشتغلش على APIs المراكز.',
        'en' => 'Super admin login. Returns a `token` (ability `admin`) to send as `Authorization: Bearer`. It does not work on center APIs.',
    ],
    'admin.auth.logout' => [
        'ar' => 'تسجيل خروج: بيلغي التوكن الحالي.',
        'en' => 'Logout: revokes the current token.',
    ],
    'admin.auth.me' => [
        'ar' => 'بيانات الأدمن الحالي.',
        'en' => 'The signed-in admin.',
    ],
    'admin.maintenance-centers.index' => [
        'ar' => 'قايمة المراكز (paginated). `q` بحث بالاسم/التليفون، `is_active`، و `expired=1` للي اشتراكهم خلص.',
        'en' => 'Centers list (paginated). `q` searches name/phone; `is_active`; `expired=1` for ended subscriptions.',
    ],
    'admin.maintenance-centers.store' => [
        'ar' => <<<'MD'
إنشاء مركز جديد (مفيش تسجيل ذاتي). في transaction واحدة بيعمل:
المركز + حساب الـ **owner** + **الفرع الرئيسي** (`branch_name` اختياري) + التصنيفات الافتراضية للمنتجات والمصروفات.

- `subscription_ends_at`: آخر يوم في الاشتراك؛ بعده المركز يبقى **قراءة بس** (أي كتابة → 402).
- `cashier_max_discount`: أقصى خصم بالجنيه للكاشير.
- `owner.username`: اختياري، وفريد على مستوى كل المراكز.
MD,
        'en' => <<<'MD'
Create a center (no self sign-up). One transaction creates the center, its **owner** account, the **main branch** (`branch_name` optional) and the default product/expense categories.

- `subscription_ends_at`: last day of the subscription; after it the center is **read-only** (writes → 402).
- `cashier_max_discount`: the cashier's maximum discount in EGP.
- `owner.username`: optional, and unique across all centers.
MD,
    ],
    'admin.maintenance-centers.show' => [
        'ar' => 'تفاصيل مركز.',
        'en' => 'Center details.',
    ],
    'admin.maintenance-centers.update' => [
        'ar' => 'تعديل بيانات مركز (الاسم، التليفون، العنوان، حد خصم الكاشير، المنطقة الزمنية، و `whatsapp_device_id` رقم واتساب اللي بيبعت منه على GOWA). الاشتراك له endpoint لوحده.',
        'en' => 'Update a center (name, phone, address, cashier discount limit, timezone, and `whatsapp_device_id`, its sending WhatsApp device on GOWA). The subscription has its own endpoint.',
    ],
    'admin.maintenance-centers.toggle' => [
        'ar' => 'إيقاف/تفعيل مركز. الإيقاف بيمسح توكنات كل مستخدميه فوراً ويمنع الدخول.',
        'en' => 'Suspend/activate a center. Suspending revokes all its users\' tokens and blocks login.',
    ],
    'admin.maintenance-centers.subscription' => [
        'ar' => 'تجديد/تغيير الاشتراك: `plan` و `subscription_ends_at` (`null` = مفتوح).',
        'en' => 'Renew/change the subscription: `plan` and `subscription_ends_at` (`null` = no end date).',
    ],
    'admin.maintenance-centers.branches.index' => [
        'ar' => 'فروع مركز.',
        'en' => 'A center\'s branches.',
    ],
    'admin.maintenance-centers.branches.store' => [
        'ar' => 'إضافة فرع لمركز (الفروع بيعملها الأدمن بس). كل فرع له ترقيم فواتير وإيصالات مستقل.',
        'en' => 'Add a branch to a center (admin only). Each branch numbers its own invoices and receipts.',
    ],
    'admin.maintenance-centers.branches.update' => [
        'ar' => 'تعديل بيانات فرع.',
        'en' => 'Update a branch.',
    ],
    'admin.maintenance-centers.branches.toggle' => [
        'ar' => 'إيقاف/تفعيل فرع. الفرع الرئيسي مايتوقفش.',
        'en' => 'Deactivate/activate a branch. The main branch cannot be deactivated.',
    ],
    'admin.car-makes.index' => [
        'ar' => 'كل الماركات (النشطة والموقوفة) بموديلاتها ولوجوهاتها.',
        'en' => 'All makes (active and inactive) with their models and logos.',
    ],
    'admin.car-makes.store' => [
        'ar' => 'إضافة ماركة للمنصة كلها. `logo_url` رابط https لصورة اللوجو (اختياري). الماركات الأساسية (30) جاية من الـ seeder بلوجوهاتها.',
        'en' => 'Add a platform-wide make. `logo_url` is an https image link (optional). The 30 base makes come from the seeder with logos.',
    ],
    'admin.car-makes.update' => [
        'ar' => 'تعديل ماركة (ابعت اللي هيتغير بس). لشيل اللوجو: `"logo_url": null` مع حقل تاني (request كله null بيترفض).',
        'en' => 'Update a make (send only what changes). To remove the logo send `"logo_url": null` together with another field (an all-null request is rejected).',
    ],
    'admin.car-makes.toggle' => [
        'ar' => 'إيقاف/تفعيل ماركة. الموقوفة ماتظهرش للمراكز ولا تتختار لعربية جديدة.',
        'en' => 'Deactivate/activate a make. Inactive makes are hidden from centers and cannot be picked.',
    ],
    'admin.car-models.store' => [
        'ar' => 'إضافة موديل لماركة (الاسم مايتكررش جوه نفس الماركة).',
        'en' => 'Add a model to a make (name unique within the make).',
    ],
    'admin.car-models.update' => [
        'ar' => 'تعديل موديل.',
        'en' => 'Update a model.',
    ],
    'admin.car-models.toggle' => [
        'ar' => 'إيقاف/تفعيل موديل.',
        'en' => 'Deactivate/activate a model.',
    ],

    // =====================================================================
    // Center — auth & settings
    // =====================================================================

    'center.auth.login' => [
        'ar' => <<<'MD'
دخول مستخدم المركز (5 محاولات في الدقيقة). ابعت `email` أو `username` مع `password`، ولو الاتنين اتبعتوا بيتستخدم `email`.

**الرد:** `token`, `user` (بالدور)، `maintenance_center`، `branches` (المتاحة له)، `permissions` (عشان الفرونت يخفي الأزرار).
بيترفض لو المستخدم أو المركز موقوف (403 `account_inactive`).
MD,
        'en' => <<<'MD'
Center user login (5 attempts per minute). Send `email` or `username` with `password`; if both are sent, `email` is used.

**Returns:** `token`, `user` (with role), `maintenance_center`, available `branches`, and `permissions` (for hiding UI actions).
Rejected if the user or the center is suspended (403 `account_inactive`).
MD,
    ],
    'center.auth.logout' => [
        'ar' => 'تسجيل خروج: بيلغي التوكن الحالي.',
        'en' => 'Logout: revokes the current token.',
    ],
    'center.auth.me' => [
        'ar' => 'المستخدم الحالي + المركز + الفروع المتاحة + الصلاحيات (نفس شكل رد الدخول من غير توكن).',
        'en' => 'Current user, center, available branches and permissions (the login payload without the token).',
    ],
    'center.auth.password' => [
        'ar' => 'تغيير كلمة السر (لازم الحالية + التأكيد). بيطلّع الجلسات التانية.',
        'en' => 'Change password (current password + confirmation required). Signs out other sessions.',
    ],
    'center.settings.show' => [
        'ar' => 'إعدادات المركز: الاسم، التليفون، العنوان، اللوجو، حد خصم الكاشير، الاشتراك.',
        'en' => 'Center settings: name, phone, address, logo, cashier discount limit, subscription.',
    ],
    'center.settings.update' => [
        'ar' => <<<'MD'
تعديل إعدادات المركز (owner)، ومنها رسائل واتساب الأوتوماتيك. الرد فيه `whatsapp_connected`، ورقم الإرسال (device) بيحدده الأدمن بس.

- `whatsapp_invoice_enabled` و `whatsapp_payment_enabled`: صورة الفاتورة وصورة الإيصال. `whatsapp_invoice_enabled` بيتحكم كمان في إشعار تعديل الفاتورة المقفولة.
- `whatsapp_service_reminder_enabled`: تذكير قبل معاد الصيانة الجاية بيوم. مقفول افتراضياً.
- `whatsapp_debt_reminder_enabled` و `debt_reminder_days` (1 لـ 365، الافتراضي 30): تذكير المديونية. مقفول افتراضياً.

**قاعدة تذكير المديونية:** العميل بيتفكّر لما يعدّي `debt_reminder_days` يوم من غير أي حاجة جديدة: لا فاتورة جديدة عليها فلوس، ولا دفعة (حتى لو جزئية)، ولا تذكير سابق. وبيتكرر كل فترة لحد ما يسدد كله.

التذكيرات بتتبعت كل يوم الساعة 10 الصبح بتوقيت القاهرة.
MD,
        'en' => <<<'MD'
Update center settings (owner), including the automatic WhatsApp messages. The response includes `whatsapp_connected`; the sending device is set by the admin only.

- `whatsapp_invoice_enabled`, `whatsapp_payment_enabled`: invoice and receipt images. `whatsapp_invoice_enabled` also controls the edit notice of a completed invoice.
- `whatsapp_service_reminder_enabled`: a reminder the day before the next service date. Off by default.
- `whatsapp_debt_reminder_enabled` and `debt_reminder_days` (1–365, default 30): debt reminder. Off by default.

**Debt reminder rule:** a customer is reminded once `debt_reminder_days` pass with nothing new: no new unpaid invoice, no payment (even partial), no earlier reminder. It repeats every period until the debt is fully paid.

Reminders go out daily at 10:00 Cairo time.
MD,
    ],
    'center.settings.logo' => [
        'ar' => 'رفع لوجو المركز (`multipart/form-data`، حقل `image`). بيظهر في الفواتير المطبوعة.',
        'en' => 'Upload the center logo (`multipart/form-data`, field `image`). Shown on printed invoices.',
    ],
    'center.users.index' => [
        'ar' => 'حسابات الدخول (paginated) بفلتر الدور والفرع والحالة.',
        'en' => 'Login accounts (paginated), filterable by role, branch and status.',
    ],
    'center.users.store' => [
        'ar' => 'إضافة حساب من غير سجل موظف (زي owner إضافي). **حسابات الموظفين تتعمل من `employees` (الحقل `account`).** `role` هو `key` الدور (من `roles` أو `options`). أي دور غير المالك لازم `branch_id`؛ الـ owner مالوش فرع. حسابات المالك يعملها ويعدلها المالك بس. `username` اختياري وفريد على مستوى كل المراكز (حروف إنجليزي وأرقام و`-` و`_`، من 3 لـ 50 حرف).',
        'en' => 'Create an account without an employee record (e.g. another owner). **Staff logins are created from `employees` (`account` field).** `role` is the role `key` (from `roles` or `options`). Every role other than the owner needs `branch_id`; owners have none. Only an owner can create or change owner accounts. `username` is optional and unique across all centers (English letters, digits, `-` and `_`, 3 to 50 characters).',
    ],
    'center.roles.index' => [
        'ar' => 'أدوار المركز (owner بس). أول واحد دايماً دور المالك. كل دور معاه `key` (القيمة اللي بتتبعت في `role` للحسابات والموظفين) و`permissions` و`users_count`. `is_default` معناها إنه من الأدوار الأربعة اللي المركز بيبدأ بيها.',
        'en' => 'The center\'s roles (owner only). The owner role always comes first. Each role carries its `key` (the value sent as `role` for accounts and employees), `permissions` and `users_count`. `is_default` marks the four roles every center starts with.',
    ],
    'center.roles.permissions' => [
        'ar' => 'كتالوج الصلاحيات اللي ينفع تتدي لأي دور، متقسم مجموعات وبأسماء بلغة الـ `lang`. ده اللي شاشة تعديل الدور بتعرضه كـ checkboxes.',
        'en' => 'The grantable permissions, grouped and labelled in the `lang` language. This is what the role editor shows as checkboxes.',
    ],
    'center.roles.store' => [
        'ar' => 'إضافة دور: `name` و`permissions` (من الكتالوج) و`acts_as_technician` اختياري (لو `true` الأوردرات اللي الحساب يفتحها بتتسجل باسمه كفني). الـ `key` بيتعمل لوحده (`role_12`) ومابيتغيرش. أي دور غير المالك بيبقى مربوط بفرع واحد.',
        'en' => 'Create a role: `name`, `permissions` (from the catalog) and optional `acts_as_technician` (when `true`, orders the account opens default to it as the technician). The `key` is generated (`role_12`) and never changes. Every role other than the owner is pinned to one branch.',
    ],
    'center.roles.show' => [
        'ar' => 'تفاصيل دور.',
        'en' => 'Role details.',
    ],
    'center.roles.update' => [
        'ar' => 'تعديل الاسم أو الصلاحيات أو `acts_as_technician`. التعديل بيتطبق على الحسابات اللي عليها الدور من الطلب الجاي. دور المالك مينفعش يتعدل (422). بيتسجل في سجل العمليات.',
        'en' => 'Update the name, permissions or `acts_as_technician`. Accounts holding the role get the change from their next request. The owner role cannot be edited (422). Logged in the audit log.',
    ],
    'center.roles.destroy' => [
        'ar' => 'حذف دور. مرفوض لدور المالك (422)، ولأي دور عليه حسابات حتى لو موقوفة (409).',
        'en' => 'Delete a role. Refused for the owner role (422) and for any role held by accounts, suspended ones included (409).',
    ],
    'center.users.show' => [
        'ar' => 'تفاصيل حساب.',
        'en' => 'Account details.',
    ],
    'center.users.update' => [
        'ar' => 'تعديل حساب، بما فيه `username` و`email`. إرسال `username: null` بيمسحه. تغيير كلمة السر بيطلّعه من كل الأجهزة. مايقدرش يغيّر دوره هو.',
        'en' => 'Update an account, including `username` and `email`. Sending `username: null` clears it. A password change signs it out everywhere. You cannot change your own role.',
    ],
    'center.users.toggle' => [
        'ar' => 'إيقاف/تفعيل حساب (الإيقاف بيطلّعه فوراً). مايقدرش يوقف نفسه.',
        'en' => 'Suspend/activate an account (suspending signs it out). You cannot suspend yourself.',
    ],
    'center.audit-logs.index' => [
        'ar' => 'سجل التعديلات الحساسة (مين، إمتى، القيم قبل وبعد، السبب): تعديل/إلغاء فواتير، إبطال، المخزون، نقل ملكية...',
        'en' => 'Audit trail of sensitive changes (who, when, before/after, reason): invoice edits/cancellations, voids, stock, ownership transfers…',
    ],

    // =====================================================================
    // Center — lookups
    // =====================================================================

    'center.search' => [
        'ar' => 'البحث السريع في شاشة الكاشير: باللوحة (بأي شكل كتابة: مسافات، أرقام عربي/إنجليزي)، أو التليفون، أو الاسم. بيرجّع عربيات وعملاء.',
        'en' => 'Cashier quick search by plate (any spelling: spaces, Arabic/Latin digits), phone or name. Returns vehicles and customers.',
    ],
    'center.car-makes.index' => [
        'ar' => <<<'MD'
**المصدر الوحيد للماركات والموديلات في الفرونت** (عشان الإدخال يبقى موحد).

كل ماركة: `id`, `name_ar`, `name_en`, `logo_url`, و `models[]`.
في العربية ابعت `car_make_id` و `car_model_id` من هنا، و `year` (سنة الموديل).
MD,
        'en' => <<<'MD'
**The single source of makes and models for the frontend** (so input stays consistent).

Each make: `id`, `name_ar`, `name_en`, `logo_url`, `models[]`.
On a vehicle send `car_make_id` / `car_model_id` from here, and `year` (model year).
MD,
    ],
    'center.options' => [
        'ar' => 'القوائم الثابتة للفرونت (قيمة + اسم بلغة الـ `lang`): طرق الدفع، حالات الأوردر، أنواع المنتجات، أدوار المركز (بما فيها اللي المالك عملها، والقيمة هي `key`)، أنواع حركات الموظفين...',
        'en' => 'Fixed lists for the frontend (value + label in the `lang` language): payment methods, order statuses, product kinds, the center\'s roles (custom ones included, the value is the `key`), employee transaction types…',
    ],

    // =====================================================================
    // Center — customers & vehicles
    // =====================================================================

    'center.customers.index' => [
        'ar' => 'قايمة العملاء (paginated) ومعاها مديونية كل عميل. `q` بالاسم أو التليفون، `has_debt=1` للمدينين، و `format=excel`.',
        'en' => 'Customers (paginated) with each one\'s debt. `q` by name or phone, `has_debt=1` for debtors, `format=excel`.',
    ],
    'center.customers.store' => [
        'ar' => 'إضافة عميل، وممكن معاه عربياته (`vehicles[]`) في نفس الطلب. التليفون موبايل مصري: 11 رقم يبدأ بـ 010 أو 011 أو 012 أو 015 (الأرقام العربية والمسافات بتتشال)، ومايتكررش جوه المركز، واللوحة كذلك.',
        'en' => 'Create a customer, optionally with their vehicles (`vehicles[]`). The phone is an Egyptian mobile: 11 digits starting 010, 011, 012 or 015 (Arabic digits and spaces are normalized), unique within the center, as is the plate.',
    ],
    'center.customers.show' => [
        'ar' => 'العميل بعربياته، و `debts`: إجمالي المديونية وتفصيلها على كل عربية.',
        'en' => 'The customer with vehicles and `debts`: total debt and per-vehicle breakdown.',
    ],
    'center.customers.update' => [
        'ar' => 'تعديل بيانات العميل (العربيات من `vehicles`).',
        'en' => 'Update a customer (vehicles are managed through `vehicles`).',
    ],
    'center.customers.destroy' => [
        'ar' => 'حذف عميل (soft delete). مرفوض لو عليه مديونية.',
        'en' => 'Delete a customer (soft delete). Refused while they owe money.',
    ],
    'center.customers.balance' => [
        'ar' => 'مديونية العميل: الإجمالي + كل عربية (الرصيد، عدد الفواتير، أقدم فاتورة).',
        'en' => 'Customer debt: total + per vehicle (balance, invoice count, oldest invoice).',
    ],
    'center.customers.statement' => [
        'ar' => <<<'MD'
**كشف حساب العميل** (زي كشف البنك) من كل الفروع.

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
- `reference` = رقم الفاتورة أو الإيصال حسب النوع.
- `closing_balance` = المديونية الحالية بالظبط (من غير `to`).
- الأوردرات الملغية مش ظاهرة. العربون على أوردر مفتوح في `summary.open_orders_deposits` بس.
- `from`/`to` بأيام المركز؛ اللي قبل `from` بيتجمع في `opening_balance`.
MD,
        'en' => <<<'MD'
**Customer account statement** (bank-statement style) across all branches.

- `debit` = owed (invoice / opening balance), `credit` = paid (collection, one row per receipt), `balance` = running balance after the row.
- `reference` = invoice number or receipt number depending on the type.
- `closing_balance` equals the current debt exactly (without `to`).
- Cancelled orders are left out. Deposits on still-open orders appear only in `summary.open_orders_deposits`.
- `from`/`to` are center-local days; everything before `from` is summed into `opening_balance`.
MD,
    ],
    'center.customers.unpaid-orders' => [
        'ar' => <<<'MD'
**الفواتير اللي عليها مديونية** (لشاشة "سداد فاتورة معينة" و"أختار الفواتير بنفسي")، من كل الفروع، من الأقدم للأحدث.

**الرد:** `{ "total_due": 700, "orders": [ {id, order_no, branch, vehicle, total, paid, remaining, completed_at, ...} ] }`
MD,
        'en' => <<<'MD'
**Invoices that still owe money** (for "pay a specific invoice" and "choose invoices myself"), every branch, oldest first.

**Returns:** `{ "total_due": 700, "orders": [ {id, order_no, branch, vehicle, total, paid, remaining, completed_at, ...} ] }`
MD,
    ],
    'center.customers.orders.show' => [
        'ar' => 'عرض فاتورة من فواتير العميل **حتى لو من فرع تاني** (بالبنود والمدفوعات). لو مش للعميل ده → 404.',
        'en' => 'Show one of the customer\'s invoices **even from another branch** (items and payments). Not this customer\'s → 404.',
    ],
    'center.customers.payments' => [
        'ar' => 'تسجيل دفعة من صفحة العميل — شوف الـ 3 requests اللي جنبها لكل نوع سداد.',
        'en' => 'Record a payment from the customer page — see the 3 requests next to it, one per payment mode.',
    ],
    'center.vehicles.index' => [
        'ar' => 'قايمة العربيات (paginated). `q` باللوحة (بأي شكل كتابة)، فلتر بالعميل والماركة، و `format=excel`.',
        'en' => 'Vehicles (paginated). `q` by plate (any spelling), filter by customer and make, `format=excel`.',
    ],
    'center.vehicles.store' => [
        'ar' => 'إضافة عربية لعميل. اللوحة بتتخزن زي ما اتكتبت وبتتطابق بعد توحيدها (مسافات/أرقام/همزات). الماركة والموديل من `car-makes`، و `year` سنة الموديل. صورة الشاسيه اختيارية في `vin_image` (`multipart/form-data`)، شوف الطلب التاني.',
        'en' => 'Add a vehicle to a customer. The plate is stored as typed and matched after normalisation (spaces/digits/letter variants). Make/model from `car-makes`; `year` is the model year. The chassis image is optional in `vin_image` (`multipart/form-data`), see the second request.',
    ],
    'center.vehicles.show' => [
        'ar' => 'صفحة العربية: المالك الحالي، آخر عداد وآخر زيارة، الصيانة الجاية، ومديونية المالك الحالي عليها.',
        'en' => 'Vehicle page: current owner, last odometer and visit, next service, and the current owner\'s debt on it.',
    ],
    'center.vehicles.update' => [
        'ar' => 'تعديل بيانات العربية (المالك يتغير من `transfer-owner`). إرسال `vin_image: null` بيمسح صورة الشاسيه. ولرفع صورة، شوف الطلب التاني.',
        'en' => 'Update a vehicle (the owner changes through `transfer-owner`). Sending `vin_image: null` removes the chassis image. To upload one, see the second request.',
    ],
    'center.vehicles.destroy' => [
        'ar' => 'حذف عربية (soft delete). مرفوض لو عليها مديونية.',
        'en' => 'Delete a vehicle (soft delete). Refused while it carries debt.',
    ],
    'center.vehicles.transfer-owner' => [
        'ar' => 'نقل ملكية العربية لعميل تاني. **الفواتير القديمة وديونها بتفضل على المالك القديم.** بيتسجل في الـ audit.',
        'en' => 'Transfer the vehicle to another customer. **Past invoices and their debt stay with the previous owner.** Audited.',
    ],
    'center.vehicles.history' => [
        'ar' => 'تاريخ زيارات العربية (الفواتير بقراءات العداد)، paginated.',
        'en' => 'The vehicle\'s visit history (invoices with odometer readings), paginated.',
    ],
    'center.vehicles.payments' => [
        'ar' => 'تحصيل على فواتير العربية دي (اللي على مالكها الحالي) من الأقدم. `method` افتراضي `cash`.',
        'en' => 'Collect against this vehicle\'s invoices (owed by its current owner), oldest first. `method` defaults to `cash`.',
    ],
    'center.vehicles.opening-balance' => [
        'ar' => 'تسجيل دين قديم من الدفتر الورق على العربية (owner). بيتعمل كفاتورة مقفولة "رصيد افتتاحي"، ومابيدخلش في تقارير المبيعات ولا بيأثر على العداد.',
        'en' => 'Record an old paper-ledger debt on the vehicle (owner). Stored as a completed "opening balance" invoice, excluded from sales reports and odometer.',
    ],

    // =====================================================================
    // Center — catalog
    // =====================================================================

    'center.pos.catalog' => [
        'ar' => <<<'MD'
شاشة "إنشاء فاتورة" في call واحد.

**الرد:** `{ "next_order_no": 8, "categories": [ { "id", "name", "products": [ ... ] } ] }`
- `next_order_no`: رقم الفاتورة **المتوقع** للعرض بس (الـ prefix زي INV- شغل الفرونت). الرقم الفعلي بيرجع مع الحفظ.
- كل منتج: `id`, `name`, `barcode`, `kind`, `sale_price`, `image_url`, `stock` (رصيد الفرع).

**`q`:** باركود (من الماسح) أو جزء من الاسم. باركود مطابق → المنتج ده بس؛ غير كده بحث بالاسم والتصنيفات اللي فيها نتائج بس.
MD,
        'en' => <<<'MD'
The "new invoice" screen in one call.

**Returns:** `{ "next_order_no": 8, "categories": [ { "id", "name", "products": [ ... ] } ] }`
- `next_order_no`: the **expected** invoice number, display only (a prefix like INV- is the frontend's). The real number comes back on save.
- Each product: `id`, `name`, `barcode`, `kind`, `sale_price`, `image_url`, `stock` (branch stock).

**`q`:** a scanned barcode or part of a name. Exact barcode match → that product only; otherwise a name search returning only matching categories.
MD,
    ],
    'center.categories.index' => [
        'ar' => 'تصنيفات المنتجات مترتبة بـ `sort_order` ومعاها عدد المنتجات.',
        'en' => 'Product categories ordered by `sort_order`, with product counts.',
    ],
    'center.categories.store' => [
        'ar' => 'إضافة تصنيف (owner).',
        'en' => 'Create a category (owner).',
    ],
    'center.categories.show' => [
        'ar' => 'تفاصيل تصنيف.',
        'en' => 'Category details.',
    ],
    'center.categories.update' => [
        'ar' => 'تعديل تصنيف (الاسم والترتيب).',
        'en' => 'Update a category (name and order).',
    ],
    'center.categories.destroy' => [
        'ar' => 'حذف تصنيف. مرفوض لو فيه منتجات (أوقفه بدل كده).',
        'en' => 'Delete a category. Refused while it has products (deactivate it instead).',
    ],
    'center.categories.toggle' => [
        'ar' => 'إيقاف/تفعيل تصنيف (الموقوف مابيظهرش في شاشة الفاتورة).',
        'en' => 'Deactivate/activate a category (hidden from the invoice screen when inactive).',
    ],
    'center.products.index' => [
        'ar' => 'قايمة المنتجات والخدمات (paginated). `q` بالاسم أو **الباركود**. سعر التكلفة بيظهر للـ owner بس. و `format=excel`.',
        'en' => 'Products and services (paginated). `q` by name or **barcode**. Cost price is shown to the owner only. `format=excel`.',
    ],
    'center.products.store' => [
        'ar' => 'إضافة منتج (`part`) أو خدمة (`service`) (owner). ممكن `multipart/form-data` مع صورة في `image`. `barcode` مايتكررش جوه المركز. الخدمة عمرها ما بتتبع مخزون.',
        'en' => 'Create a product (`part`) or service (`service`) (owner). May be `multipart/form-data` with an `image`. `barcode` is unique within the center. Services never track stock.',
    ],
    'center.products.show' => [
        'ar' => 'تفاصيل منتج ومخزونه (الفرع المختار، أو مجموع الفروع لو مفيش فرع محدد).',
        'en' => 'Product details and stock (selected branch, or all branches when none is selected).',
    ],
    'center.products.update' => [
        'ar' => 'تعديل منتج (owner). تغيير السعر **مابيأثرش على الفواتير القديمة** (البنود متنسخة). الصورة من `products/{id}/image`.',
        'en' => 'Update a product (owner). Price changes **do not affect past invoices** (lines are snapshots). The image is changed via `products/{id}/image`.',
    ],
    'center.products.destroy' => [
        'ar' => 'حذف منتج (soft delete). الفواتير القديمة بتفضل سليمة. لو المنتج اتباع قبل كده الأفضل توقفه.',
        'en' => 'Delete a product (soft delete). Past invoices stay intact. Prefer deactivating products that were sold.',
    ],
    'center.products.toggle' => [
        'ar' => 'إيقاف/تفعيل منتج (الموقوف مايتباعش).',
        'en' => 'Deactivate/activate a product (inactive products cannot be sold).',
    ],
    'center.products.image' => [
        'ar' => 'رفع/تغيير صورة المنتج (`multipart/form-data`، حقل `image`). الصورة القديمة بتتمسح.',
        'en' => 'Upload/replace the product image (`multipart/form-data`, field `image`). The old file is deleted.',
    ],
    'center.products.stock' => [
        'ar' => 'جرد يدوي: تحديد رصيد المنتج في الفرع المختار (owner) مع سبب. بيتسجل في الـ audit.',
        'en' => 'Manual count: set the product\'s stock in the selected branch (owner) with a reason. Audited.',
    ],

    // =====================================================================
    // Center — routine services
    // =====================================================================

    'center.routine-services.index' => [
        'ar' => 'الخدمات الاعتيادية (اسم + تفعيل، من غير سعر)، مترتبة **من الأكتر استخدامًا**. كل خدمة معاها `usage_count` (بنود الفواتير غير الملغية)، و`last_used_at`، و`suggested_price`: أكتر سعر اتكرر في آخر 3 شهور (التعادل → الأحدث)، ولو مفيش استخدام فيها → آخر سعر، ولو ما اتستخدمتش خالص → `null`. شاشة الفاتورة تبعت `is_active=1`.',
        'en' => 'Routine services (name + active, no price), ordered **most used first**. Each has `usage_count` (lines on non-cancelled invoices), `last_used_at` and `suggested_price`: the most frequent price of the last 3 months (tie → newest), else the last price used, else `null`. The invoice screen sends `is_active=1`.',
    ],
    'center.routine-services.store' => [
        'ar' => 'إضافة خدمة اعتيادية. الاسم مايتكررش في المركز.',
        'en' => 'Create a routine service. The name is unique within the center.',
    ],
    'center.routine-services.show' => [
        'ar' => 'تفاصيل الخدمة مع `prices`: توزيع الأسعار في آخر 3 شهور (كل سعر اتعمل كام مرة)، الأكتر الأول.',
        'en' => 'Service details with `prices`: the price distribution of the last 3 months (each price and how often it was used), most used first.',
    ],
    'center.routine-services.update' => [
        'ar' => 'تغيير اسم الخدمة. الفواتير القديمة بتفضل بالاسم القديم (البند snapshot).',
        'en' => 'Rename the service. Past invoices keep the old name (lines are snapshots).',
    ],
    'center.routine-services.destroy' => [
        'ar' => 'حذف خدمة. **الخدمة اللي اتستخدمت في أي فاتورة (حتى الملغية) مينفعش تتمسح → 409**، أوقفها بدل كده. الخدمة الافتراضية الممسوحة مابترجعش تاني مع الـ seeder.',
        'en' => 'Delete a service. **A service used on any invoice (cancelled ones included) cannot be deleted → 409**; deactivate it instead. A deleted default is not recreated by the seeder.',
    ],
    'center.routine-services.toggle' => [
        'ar' => 'إيقاف/تفعيل خدمة (الموقوفة مينفعش تتضاف لفاتورة).',
        'en' => 'Deactivate/activate a service (inactive services cannot be added to invoices).',
    ],
    'center.routine-services.history' => [
        'ar' => 'سجل استخدام الخدمة (paginated، الأحدث الأول) من كل الفروع: رقم الفاتورة وحالتها، الفرع، العميل، اللوحة، الكمية والسعر والتاريخ. الفواتير الملغية مش ظاهرة.',
        'en' => 'Usage history (paginated, newest first) across all branches: invoice number and status, branch, customer, plate, qty, price and date. Cancelled invoices are excluded.',
    ],

    // =====================================================================
    // Center — orders
    // =====================================================================

    'center.orders.index' => [
        'ar' => 'قايمة الأوردرات/الفواتير (paginated) في الفرع. `q` برقم الفاتورة أو اللوحة. الفني بيشوف المفتوح بس. الأرصدة الافتتاحية مستبعدة إلا بـ `include_opening_balance=1`. كل صف فيه `whatsapp` حالة آخر رسالة للفاتورة (زي التفاصيل).',
        'en' => 'Orders/invoices (paginated) in the branch. `q` by invoice number or plate. Technicians see open orders only. Opening balances are excluded unless `include_opening_balance=1`. Each row carries `whatsapp`, the latest invoice message status (see order details).',
    ],
    'center.orders.store' => [
        'ar' => <<<'MD'
**زرار "حفظ الفاتورة".** بيعمل الأوردر، ولو `complete: true` بيقفله فاتورة ويحصّل في نفس الـ call (transaction واحدة: لو أي حاجة فشلت مفيش حاجة بتتسجل).

**العربية — واحد من الاتنين:**
- `vehicle_id`: عربية متسجلة (من البحث).
- أو `customer` + `vehicle` من الشاشة:
  - `customer`: `{id}` لعميل موجود، أو `{name, phone}` (لو التليفون متسجل بيتستخدم العميل الموجود).
  - `vehicle`: لو اللوحة متسجلة لنفس العميل بتتستخدم؛ لو **لعميل تاني → 422**.
  - محتاج صلاحية إضافة عملاء (الفني العادي → 403).

**البنود:** من الكتالوج `{product_id, qty}` (+ `unit_price` لو عنده صلاحية تغيير السعر)، أو خدمة اعتيادية `{routine_service_id, qty, unit_price}` (السعر بيكتبه الكاشير، والمقترح من `routine-services`)، أو صنف حر `{name, qty, unit_price}`.
**الخصم:** مبلغ (النسبة يحسبها الفرونت). الكاشير ليه حد أقصى.
**القفل:** `complete: true` + `payments: [{amount, method?}]` (`method` افتراضي `cash`).
**الفني الافتراضي:** لو اللي فاتح "فني" أو "فني وكاشير" ومابعتش `technician_id` → بيتحط هو.

**الرد:** `{order, warnings[]}` — الأوردر فيه `parts_total` و `services_total` و `subtotal`, `discount`, `total`, `paid`, `remaining`. التحذيرات: العداد أقل من آخر قراءة أو المخزون نقص (من غير منع).
MD,
        'en' => <<<'MD'
**The "save invoice" button.** Creates the order; with `complete: true` it also completes it and collects payments in the same call (one transaction: on any failure nothing is saved).

**Vehicle — one of:**
- `vehicle_id`: a registered vehicle (from search).
- or `customer` + `vehicle` typed on the screen:
  - `customer`: `{id}` of an existing customer, or `{name, phone}` (a known phone reuses that customer).
  - `vehicle`: a plate already registered to the same customer is reused; registered to **another customer → 422**.
  - Requires the add-customers permission (plain technician → 403).

**Lines:** catalog `{product_id, qty}` (+ `unit_price` with the price-override permission), routine service `{routine_service_id, qty, unit_price}` (typed price; suggested by `routine-services`) or free line `{name, qty, unit_price}`.
**Discount:** an amount (the frontend converts percentages). Cashiers have a cap.
**Complete:** `complete: true` + `payments: [{amount, method?}]` (`method` defaults to `cash`).
**Default technician:** a technician / technician-cashier who omits `technician_id` becomes the technician.

**Returns:** `{order, warnings[]}` — the order includes `parts_total`, `services_total`, `subtotal`, `discount`, `total`, `paid`, `remaining`. Warnings: odometer below the last reading, or stock went short (never blocking).
MD,
    ],
    'center.orders.show' => [
        'ar' => 'تفاصيل الأوردر بالبنود والمدفوعات والعربية والعميل. الفني مابيشوفش التكلفة ولا الأوردرات المقفولة. `whatsapp`: آخر رسالة واتساب للفاتورة `{id, status, recipient, error, sent_at, created_at}`؛ `status` واحدة من `queued` قيد الإرسال، `sent`، `failed` (السبب في `error`: نص مترجم مختصر للمستخدم، والتفاصيل الفنية في لوج السيرفر بس)، أو `skipped_*`. ولو مفيش رسالة بتبقى `null`.',
        'en' => 'Order details with lines, payments, vehicle and customer. Technicians see neither costs nor closed orders. `whatsapp`: the invoice\'s latest WhatsApp message `{id, status, recipient, error, sent_at, created_at}`; `status` is `queued`, `sent`, `failed` (reason in `error`: a short translated text for the user; technical details stay in the server log) or `skipped_*`. `null` when no message exists.',
    ],
    'center.orders.update' => [
        'ar' => 'تعديل رأس الأوردر: العداد، الفني، الخصم، الصيانة الجاية، الملاحظات. الفاتورة المقفولة: owner بس، وبيتسجل في الـ audit. الإجمالي مايقلش عن المدفوع. لو الفاتورة مقفولة، العميل بيوصله على واتساب إشعار تعديل (صورة الفاتورة المحدثة فيها التعديلات والإجمالي قبل وبعد). التعديلات المتتالية بتتجمع في رسالة واحدة بتتبعت بعد دقيقتين (`WHATSAPP_INVOICE_UPDATE_DELAY`). تغيير الفني لوحده مابيبعتش حاجة.',
        'en' => 'Update the order header: odometer, technician, discount, next service, notes. Completed invoices: owner only, audited. The total cannot drop below what was paid. On a completed invoice the customer gets a WhatsApp edit notice (the updated invoice image with what changed and the total before/after). Consecutive edits are batched into one message sent after two minutes (`WHATSAPP_INVOICE_UPDATE_DELAY`). Changing only the technician sends nothing.',
    ],
    'center.orders.items.store' => [
        'ar' => 'إضافة بند: من الكتالوج (الاسم والسعر والتكلفة بيتنسخوا)، أو خدمة اعتيادية `routine_service_id` + `unit_price` (الاسم بيتنسخ من الخدمة والسعر إجباري، والخدمة الموقوفة → 422)، أو صنف حر. على فاتورة مقفولة (owner) المخزون بيتظبط فوراً. لو الفاتورة مقفولة، العميل بيوصله على واتساب إشعار تعديل (صورة الفاتورة المحدثة فيها التعديلات والإجمالي قبل وبعد). التعديلات المتتالية بتتجمع في رسالة واحدة بتتبعت بعد دقيقتين (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
        'en' => 'Add a line: from the catalog (name/price/cost are snapshotted), a routine service `routine_service_id` + `unit_price` (name snapshotted from the service, price required, inactive service → 422) or a free line. On a completed invoice (owner) stock is adjusted immediately. On a completed invoice the customer gets a WhatsApp edit notice (the updated invoice image with what changed and the total before/after). Consecutive edits are batched into one message sent after two minutes (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
    ],
    'center.orders.items.update' => [
        'ar' => 'تعديل بند (الكمية/السعر/الاسم). الإجماليات بتتحسب من جديد في نفس الـ transaction. لو الفاتورة مقفولة، العميل بيوصله على واتساب إشعار تعديل (صورة الفاتورة المحدثة فيها التعديلات والإجمالي قبل وبعد). التعديلات المتتالية بتتجمع في رسالة واحدة بتتبعت بعد دقيقتين (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
        'en' => 'Update a line (qty/price/name). Totals are recalculated in the same transaction. On a completed invoice the customer gets a WhatsApp edit notice (the updated invoice image with what changed and the total before/after). Consecutive edits are batched into one message sent after two minutes (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
    ],
    'center.orders.items.destroy' => [
        'ar' => 'حذف بند. الإجماليات بتتحسب من جديد. لو الفاتورة مقفولة، العميل بيوصله على واتساب إشعار تعديل (صورة الفاتورة المحدثة فيها التعديلات والإجمالي قبل وبعد). التعديلات المتتالية بتتجمع في رسالة واحدة بتتبعت بعد دقيقتين (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
        'en' => 'Remove a line. Totals are recalculated. On a completed invoice the customer gets a WhatsApp edit notice (the updated invoice image with what changed and the total before/after). Consecutive edits are batched into one message sent after two minutes (`WHATSAPP_INVOICE_UPDATE_DELAY`).',
    ],
    'center.orders.complete' => [
        'ar' => 'قفل أوردر مفتوح → فاتورة: المخزون بيتخصم، وآخر عداد وزيارة للعربية بيتحدثوا، وصورة الفاتورة بتتبعت للعميل على واتساب. `payments` اختيارية (`method` افتراضي `cash`). الأوردر الفاضي مايتقفلش.',
        'en' => 'Complete an open order into an invoice: stock leaves, the vehicle\'s last odometer/visit update, and the invoice image goes to the customer on WhatsApp. `payments` optional (`method` defaults to `cash`). Empty orders cannot be completed.',
    ],
    'center.orders.cancel' => [
        'ar' => 'إلغاء أوردر. المفتوح: أي حد يقدر يفتح أوردرات. المقفول: owner بس وبسبب؛ المخزون بيرجع، ولو مدفوع بيتعمل مرتجع (refund) بتاريخ النهارده.',
        'en' => 'Cancel an order. Open: anyone who can create orders. Completed: owner only, with a reason; stock returns and paid money is refunded today.',
    ],
    'center.orders.payments' => [
        'ar' => 'دفعة على أوردر واحد من شاشة الأوردرات (مسموح عربون على أوردر مفتوح)، مايزيدش عن المتبقي. مقفول على فرع المستخدم — من صفحة العميل استخدم `customers/{id}/payments`.',
        'en' => 'Pay one order from the orders screen (deposits on open orders allowed), never more than what is due. Branch-bound — from the customer page use `customers/{id}/payments`.',
    ],
    'center.orders.print' => [
        'ar' => 'طباعة الفاتورة: `output=json` (الافتراضي، الفرونت يرسمها) أو `output=pdf` عربي RTL بمقاس `size=a4` (افتراضي) أو `thermal` (80mm).',
        'en' => 'Print the invoice: `output=json` (default, the frontend renders it) or `output=pdf` (Arabic RTL) in `size=a4` (default) or `thermal` (80mm).',
    ],

    // Technical inspection
    'center.inspection.checklist' => [
        'ar' => 'قائمة الفحص الفني الثابتة: أقسام (المحرك، الفتيس، الفرامل...) وجوه كل قسم البنود بمفاتيحها وأسمائها بلغة الـ `lang`. الفرونت يرسم الشاشة منها ويبعت `key` البند في الحفظ.',
        'en' => 'The fixed technical inspection checklist: sections (engine, transmission, brakes...) each with its item keys and labels in the `lang` language. The frontend draws the form from it and sends each item `key` when saving.',
    ],
    'center.orders.inspection.show' => [
        'ar' => 'الفحص الفني للفاتورة، أو `data: null` لو مفيش فحص. `sections` فيها كل بنود القائمة، والبند اللي ما اتفحصش `status` بتاعه `null`. `summary` فيها عدد كل حالة.',
        'en' => 'The invoice\'s technical inspection, or `data: null` when none. `sections` lists every checklist item; an item not inspected has `status: null`. `summary` counts each result.',
    ],
    'center.orders.inspection.update' => [
        'ar' => 'حفظ الفحص الفني (إضافة أو تعديل). **البنود المبعوتة بتستبدل القديمة كلها**: ابعت كل البنود اللي اتفحصت، وأي بند مش مبعوت يبقى "لم يُفحص". `status`: `ok` سليم · `attention` يحتاج متابعة · `urgent` لازم يتغير. نفس قواعد تعديل الفاتورة: الملغية مقفولة، والمقفولة محتاجة صلاحية تعديل الفاتورة المقفولة. فاتورة فحص بس = فاتورة بندها خدمة "فحص فني".',
        'en' => 'Save the technical inspection (create or edit). **The items sent replace all stored ones**: send every inspected item; any item left out counts as not inspected. `status`: `ok` · `attention` · `urgent`. Same rules as editing the invoice: cancelled invoices are locked and closed ones need the edit-closed-invoices permission. An inspection-only invoice is an invoice whose line is an "inspection" service.',
    ],
    'center.orders.inspection.destroy' => [
        'ar' => 'مسح الفحص الفني للفاتورة، بنفس قواعد تعديل الفاتورة.',
        'en' => 'Delete the invoice\'s technical inspection, under the same rules as editing the invoice.',
    ],
    'center.orders.inspection.print' => [
        'ar' => 'طباعة تقرير الفحص: `output=json` (الافتراضي) أو `output=pdf` A4 عربي. التقرير فيه البنود اللي اتفحصت بس، مع بيانات العميل والعربية والعداد ونتيجة جهاز كشف الأعطال. لو مفيش فحص بيرجع 404.',
        'en' => 'Print the inspection report: `output=json` (default) or `output=pdf` (A4, Arabic). It lists only inspected items, with the customer, vehicle, odometer and the diagnostic scanner result. 404 when there is no inspection.',
    ],

    // =====================================================================
    // Center — receipts
    // =====================================================================

    'center.receipts.index' => [
        'ar' => 'إيصالات التحصيل (paginated). `q` برقم الإيصال، فلتر بالعميل وطريقة الدفع والفترة، و `voided` للملغية.',
        'en' => 'Collection receipts (paginated). `q` by receipt number; filter by customer, method, period, and `voided`.',
    ],
    'center.receipts.show' => [
        'ar' => 'تفاصيل إيصال وتوزيعه على الفواتير (والمتبقي على كل فاتورة).',
        'en' => 'Receipt details and its allocation over invoices (with each invoice\'s remaining amount).',
    ],
    'center.receipts.void' => [
        'ar' => 'إبطال إيصال (owner، بسبب): كل الدفعات اللي تحته بتتلغي والدين بيرجع. مرفوض لو بيغطي أوردر ملغي.',
        'en' => 'Void a receipt (owner, with a reason): all its payments stop counting and the debt returns. Refused if it covers a cancelled order.',
    ],
    'center.receipts.print' => [
        'ar' => 'طباعة إيصال التحصيل: `output=json` أو `pdf`، و `size=thermal` (افتراضي) أو `a4`.',
        'en' => 'Print the receipt: `output=json` or `pdf`, `size=thermal` (default) or `a4`.',
    ],

    // =====================================================================
    // Center — employees & payroll
    // =====================================================================

    'center.employees.technicians' => [
        'ar' => 'الموظفين النشطين في الفرع (لاختيار الفني على الأوردر).',
        'en' => 'Active employees of the branch (technician picker on orders).',
    ],
    'center.employees.index' => [
        'ar' => 'الموظفين (paginated) برصيد كل واحد (موجب: المركز عليه فلوس للموظف، سالب: الموظف عليه سلف) وحساب دخوله.',
        'en' => 'Employees (paginated) with balance (positive: the center owes them; negative: they owe advances) and their login account.',
    ],
    'center.employees.store' => [
        'ar' => <<<'MD'
إضافة موظف (owner)، **ومعاه حساب الدخول لو محتاج** (زي fitZone).

`account`: `{username?, email, password, role}` — الدور `cashier` أو `technician` أو `technician_cashier` (فني وكاشير). **`owner` ممنوع.**
الحساب بيتعمل في نفس فرع الموظف وبنفس اسمه. الرد فيه `account: {id, username, email, role, is_active}`.
MD,
        'en' => <<<'MD'
Create an employee (owner), **with a login account when needed** (like fitZone).

`account`: `{username?, email, password, role}` — role `cashier`, `technician` or `technician_cashier`. **`owner` is not allowed.**
The account is created in the employee's branch with the same name. The response includes `account: {id, username, email, role, is_active}`.
MD,
    ],
    'center.employees.show' => [
        'ar' => 'تفاصيل موظف ورصيده وحساب دخوله.',
        'en' => 'Employee details, balance and login account.',
    ],
    'center.employees.update' => [
        'ar' => 'تعديل موظف. `account`: لو عنده حساب بيتعدّل (أي حقل؛ تغيير الباسورد بيطلّعه)، ولو معندوش بيتعمل (`email` + `password` + `role`). تغيير الاسم بيغيّر اسم الحساب.',
        'en' => 'Update an employee. `account`: updates the existing login (any field; a password change signs it out) or creates one (`email` + `password` + `role`). Renaming the employee renames the account.',
    ],
    'center.employees.toggle' => [
        'ar' => 'إيقاف/تفعيل موظف. **الإيقاف بيوقف حساب دخوله ويطلّعه فوراً.**',
        'en' => 'Deactivate/activate an employee. **Deactivating also suspends their login and signs it out.**',
    ],
    'center.employees.statement' => [
        'ar' => 'كشف حساب الموظف: كل الحركات (مرتب، حافز، خصم، سلفة، قبض) والرصيد الحالي. `include_voided=1` للملغية.',
        'en' => 'Employee ledger: all movements (salary, bonus, deduction, advance, payment) and the current balance. `include_voided=1` to include voided rows.',
    ],
    'center.employees.transactions.store' => [
        'ar' => 'حركة على حساب الموظف: `salary` (+، لازم `period` بصيغة Y-m، مرة واحدة في الشهر)، `bonus` (+)، `deduction` (−)، `advance` سلفة (−)، `payment` قبض (−). كله كاش.',
        'en' => 'Ledger movement: `salary` (+, needs `period` as Y-m, once per month), `bonus` (+), `deduction` (−), `advance` (−), `payment` (−). All cash.',
    ],
    'center.employee-transactions.void' => [
        'ar' => 'إبطال حركة موظف بسبب (مفيش تعديل ولا حذف). إبطال المرتب بيسمح بإصدار مرتب جديد لنفس الشهر.',
        'en' => 'Void an employee movement with a reason (no edit/delete). Voiding a salary allows issuing that month again.',
    ],
    'center.payroll.preview' => [
        'ar' => 'معاينة مرتبات الشهر (`period`): الموظفين النشطين في الفرع، مرتبهم الأساسي، ومين اتصرفله قبل كده.',
        'en' => 'Preview a month\'s payroll (`period`): active branch employees, base salaries, and who was already paid.',
    ],
    'center.payroll.issue' => [
        'ar' => 'إصدار مرتبات الشهر: حركة `salary` لكل موظف نشط بمرتبه الأساسي، و `items` لتعديل مبلغ موظف معين. اللي اتصرفله قبل كده بيتعدّى.',
        'en' => 'Issue the month\'s payroll: a `salary` movement per active employee at base salary; `items` overrides amounts. Already-paid employees are skipped.',
    ],

    // =====================================================================
    // Center — expenses
    // =====================================================================

    'center.expense-categories.index' => [
        'ar' => 'تصنيفات المصروفات (إيجار، كهرباء، مياه، مرافق، نثريات + اللي المركز يضيفه).',
        'en' => 'Expense categories (rent, electricity, water, utilities, petty cash + the center\'s own).',
    ],
    'center.expense-categories.store' => [
        'ar' => 'إضافة تصنيف مصروفات.',
        'en' => 'Create an expense category.',
    ],
    'center.expense-categories.update' => [
        'ar' => 'تعديل تصنيف مصروفات.',
        'en' => 'Update an expense category.',
    ],
    'center.expense-categories.destroy' => [
        'ar' => 'حذف تصنيف مصروفات. مرفوض لو مستخدم في مصروفات.',
        'en' => 'Delete an expense category. Refused while used by expenses.',
    ],
    'center.expense-categories.toggle' => [
        'ar' => 'إيقاف/تفعيل تصنيف مصروفات.',
        'en' => 'Deactivate/activate an expense category.',
    ],
    'center.expenses.index' => [
        'ar' => 'المصروفات (paginated) في الفرع، بفلتر التصنيف وطريقة الدفع والفترة. الملغية مستبعدة إلا بـ `include_voided=1`. و `format=excel`.',
        'en' => 'Expenses (paginated) in the branch, filtered by category, method and period. Voided ones excluded unless `include_voided=1`. `format=excel`.',
    ],
    'center.expenses.store' => [
        'ar' => 'تسجيل مصروف. `method` افتراضي `cash`، و `spent_at` افتراضي النهارده.',
        'en' => 'Record an expense. `method` defaults to `cash`, `spent_at` to today.',
    ],
    'center.expenses.summary' => [
        'ar' => 'ملخص المصروفات في الفترة مقسم حسب التصنيف وحسب طريقة الدفع.',
        'en' => 'Expense totals for the period by category and by payment method.',
    ],
    'center.expenses.show' => [
        'ar' => 'تفاصيل مصروف.',
        'en' => 'Expense details.',
    ],
    'center.expenses.void' => [
        'ar' => 'إبطال مصروف بسبب (owner). مفيش تعديل ولا حذف.',
        'en' => 'Void an expense with a reason (owner). No edit or delete.',
    ],

    // =====================================================================
    // Center — WhatsApp
    // =====================================================================

    'center.orders.whatsapp' => [
        'ar' => 'إعادة إرسال صورة الفاتورة للعميل على واتساب (الفواتير المقفولة بس). بيشتغل حتى لو رسالة الفاتورة الأوتوماتيك موقوفة. واتساب مش مربوط أو الرقم غلط → 422.',
        'en' => 'Resend the invoice image to the customer on WhatsApp (completed invoices only). Works even when the automatic invoice message is off. WhatsApp not connected or a bad number → 422.',
    ],
    'center.orders.whatsapp.resend' => [
        'ar' => 'لو العميل كتب رقمه غلط: `phone` الرقم الصح (موبايل مصري 11 رقم يبدأ بـ 010/011/012/015) بيتحدّث في بيانات العميل، وبعدين صورة الفاتورة بتتبعت تاني عليه. محتاج صلاحية إقفال الفواتير وتعديل العملاء (10 محاولات في الدقيقة). الرقم متسجل لعميل تاني، أو الفاتورة مش مقفولة، أو واتساب مش مربوط → 422 والرقم مابيتغيرش. الحالة بتظهر في `whatsapp` في تفاصيل الفاتورة.',
        'en' => 'When the customer typed a wrong number: `phone` is the correct one (Egyptian mobile, 11 digits starting 010/011/012/015). It is saved on the customer, then the invoice image is sent again. Needs the complete-invoice and manage-customers permissions (10 attempts per minute). A number owned by another customer, an invoice that is not completed, or WhatsApp not connected → 422 and the number is not changed. The status shows in the invoice\'s `whatsapp`.',
    ],
    'center.receipts.whatsapp' => [
        'ar' => 'إعادة إرسال صورة إيصال السداد للعميل على واتساب. الإيصال الملغي مرفوض (422).',
        'en' => 'Resend the payment receipt image to the customer on WhatsApp. A voided receipt is refused (422).',
    ],
    'center.whatsapp.test' => [
        'ar' => 'تجربة الربط: رسالة نصية لرقم معين (10 محاولات في الدقيقة). النتيجة بتظهر في سجل الرسائل.',
        'en' => 'Connection test: a text message to a given number (10 attempts per minute). The result appears in the message log.',
    ],
    'center.whatsapp.messages' => [
        'ar' => <<<'MD'
سجل رسائل واتساب بتاعة المركز (paginated). الرقم متخفي.

**الأنواع:** `invoice` فاتورة، `invoice_updated` إشعار تعديل فاتورة مقفولة، `payment` إيصال، `test` تجربة، `service_reminder` تذكير صيانة، `debt_reminder` تذكير مديونية (فيه `customer`).

**الحالات:** `queued` قيد الإرسال (لسه في الطابور)، `sent` أُرسلت، `failed` فشلت بعد 3 محاولات (السبب في `error`: نص مترجم مختصر، زي تعذّر تجهيز الصورة أو تعذّر الإرسال)، `skipped_disabled` الرسالة موقوفة، `skipped_no_device` واتساب مش مربوط، `skipped_invalid_phone` رقم غلط، `skipped_no_debt` المديونية اتسددت قبل الإرسال، `skipped_order_cancelled` الفاتورة اتلغت قبل إرسال إشعار التعديل.
الرسائل بتتبعت في الخلفية، فالـ API بيرد قبل ما الرسالة توصل. متابعتها من هنا.
MD,
        'en' => <<<'MD'
The center's WhatsApp message log (paginated). Numbers are masked.

**Types:** `invoice`, `invoice_updated` edit notice of a completed invoice, `payment`, `test`, `service_reminder`, `debt_reminder` (carries `customer`).

**Statuses:** `queued` waiting in the queue; `sent`; `failed` after 3 attempts (reason in `error`: a short translated text, such as the image could not be prepared or the message could not be sent); `skipped_disabled` message switched off; `skipped_no_device` WhatsApp not connected; `skipped_invalid_phone` bad number; `skipped_no_debt` debt settled before sending; `skipped_order_cancelled` the invoice was cancelled before its edit notice went out.
Messages go out in the background, so the API answers before delivery. Follow them here.
MD,
    ],

    // =====================================================================
    // Center — reports (owner)
    // =====================================================================

    'center.reports.today' => [
        'ar' => 'Dashboard النهارده (بتوقيت المركز): عدد الأوردرات، المبيعات، المحصّل، الديون الجديدة، المصروفات، الأوردرات المفتوحة، إجمالي الديون. للفرع المختار أو كل الفروع.',
        'en' => 'Today\'s dashboard (center time): orders, sales, collected, new debt, expenses, open orders, total debt. Selected branch or all branches.',
    ],
    'center.reports.sales' => [
        'ar' => 'المبيعات في الفترة: الفواتير المقفولة (من غير الملغي والرصيد الافتتاحي)، مقسمة على الأيام، والتحصيل حسب طريقة الدفع. و `format=excel`.',
        'en' => 'Sales for the period: completed invoices (excluding cancelled and opening balances), per day, and collections by payment method. `format=excel`.',
    ],
    'center.reports.debts' => [
        'ar' => 'العملاء المدينين (paginated) ولكل عميل تفصيل على عربياته. `sort=amount` (الأكبر) أو `oldest` (الأقدم). و `format=excel`.',
        'en' => 'Debtor customers (paginated), each broken down by vehicle. `sort=amount` (largest) or `oldest`. `format=excel`.',
    ],
    'center.reports.profit' => [
        'ar' => 'الأرباح في الفترة = المبيعات − تكلفة البضاعة (من التكلفة المنسوخة في البنود) − المصروفات − المرتبات. `items_without_cost` = بنود مالهاش تكلفة متسجلة.',
        'en' => 'Profit for the period = sales − cost of goods (snapshotted line costs) − expenses − salaries. `items_without_cost` counts lines with no recorded cost.',
    ],
    'center.reports.cash-flow' => [
        'ar' => 'حركة الخزنة (الكاش) في الفترة: الداخل = التحصيل الكاش، الخارج = المرتجعات الكاش + المصروفات الكاش + السلف + القبض.',
        'en' => 'Cash drawer flow for the period: in = cash collections; out = cash refunds + cash expenses + advances + salary payments.',
    ],
    'center.reports.service-due' => [
        'ar' => 'العربيات اللي صيانتها الجاية (من آخر فاتورة) قرّبت خلال `days` يوم (افتراضي 7) أو عدّت، عشان المركز يكلمهم. paginated.',
        'en' => 'Vehicles whose next service (from their latest invoice) is within `days` days (default 7) or overdue, for follow-up calls. Paginated.',
    ],
];

$variants = [
    'center.auth.login' => [
        null,
        [
            'name' => 'center.auth.login — بالـ username',
            'body' => ['username' => 'owner3', 'password' => 'password'],
        ],
    ],
    'center.vehicles.store' => [
        null,
        [
            'name' => 'center.vehicles.store — مع صورة الشاسيه',
            'form' => [
                ['key' => 'customer_id', 'value' => '1', 'type' => 'text'],
                ['key' => 'plate_number', 'value' => 'ب ج 456', 'type' => 'text'],
                ['key' => 'vin', 'value' => 'JTDBR32E720123456', 'type' => 'text'],
                ['key' => 'vin_image', 'type' => 'file', 'src' => []],
            ],
            'ar' => 'إضافة عربية مع صورة رقم الشاسيه. الطلب `multipart/form-data`، والصورة في `vin_image` (jpg, jpeg, png, webp بحد أقصى 12 ميجا). السيرفر بيصغّرها لـ 2000 بكسل على الضلع الطويل ويحفظها JPEG. رابطها بيرجع في `vin_image_url`.',
            'en' => 'Add a vehicle with a photo of its chassis number. The request is `multipart/form-data`, with the image in `vin_image` (jpg, jpeg, png, webp, 12 MB max). The server shrinks it to 2000 px on the long edge and stores it as JPEG. Its link comes back in `vin_image_url`.',
        ],
    ],
    'center.vehicles.update' => [
        null,
        [
            'name' => 'center.vehicles.update — رفع أو تغيير صورة الشاسيه',
            'method' => 'POST',
            'form' => [
                ['key' => '_method', 'value' => 'PUT', 'type' => 'text'],
                ['key' => 'vin_image', 'type' => 'file', 'src' => []],
            ],
            'ar' => 'رفع أو تغيير صورة رقم الشاسيه، والقديمة بتتمسح. **الطلب لازم يتبعت `POST` ومعاه `_method=PUT`**، لأن PHP مابيقراش ملفات `PUT` مباشرة. ممكن تبعت معاها أي حقل تاني من حقول التعديل.',
            'en' => 'Upload or replace the chassis number image; the old file is deleted. **Send it as `POST` with `_method=PUT`**, because PHP does not parse files on a direct `PUT`. Any other update field may go with it.',
        ],
    ],
    'center.customers.payments' => [
        [
            'name' => 'center.customers.payments — سداد من الإجمالي (الأقدم أولاً)',
            'body' => ['amount' => 500, 'method' => 'cash', 'notes' => null],
            'ar' => <<<'MD'
**سداد من إجمالي المديونية — توزيع تلقائي من الأقدم.**

- `amount` مطلوب، `method` اختياري (افتراضي `cash`)، `notes` اختياري.
- `allocation` مش لازم (الافتراضي `oldest`).
- `vehicle_id` اختياري: يقصر التوزيع على فواتير عربية واحدة.
- المبلغ أكبر من إجمالي المديونية → 422.

**الرد:** الإيصال (`receipt_no`, `amount`, `method`, `payments[]` بالتوزيع والمتبقي على كل فاتورة). يتطبع من `receipts/{id}/print`.
MD,
            'en' => <<<'MD'
**Pay from the total debt — automatic, oldest first.**

- `amount` required, `method` optional (default `cash`), `notes` optional.
- `allocation` may be omitted (default `oldest`).
- `vehicle_id` optional: limits the allocation to one vehicle's invoices.
- Amount above the total debt → 422.

**Returns:** the receipt (`receipt_no`, `amount`, `method`, `payments[]` with the split and each invoice's remaining). Print via `receipts/{id}/print`.
MD,
        ],
        [
            'name' => 'center.customers.payments — أختار الفواتير بنفسي',
            'body' => ['amount' => 500, 'method' => 'instapay', 'allocation' => 'manual', 'orders' => [['order_id' => 1, 'amount' => 300], ['order_id' => 2, 'amount' => 200]]],
            'ar' => <<<'MD'
**سداد من الإجمالي — المستخدم يوزّع بنفسه.**

- `allocation: "manual"` و `orders: [{order_id, amount}]` (الفواتير من `unpaid-orders`).
- **مجموع المبالغ لازم = `amount`** وإلا 422.
- كل مبلغ مايزيدش عن المتبقي على فاتورته.
- الفاتورة لازم تكون للعميل ده ومقفولة ولسه عليها فلوس، وإلا 422 **ومفيش حاجة بتتسجل**.
- ممكن فواتير من فروع مختلفة؛ الإيصال بيتسجل في درج فرع المستخدم.

نصيحة للفرونت: لما يعلّم فاتورة املأ مبلغها بالمتبقي عليها وخلّيه يعدّل.
MD,
            'en' => <<<'MD'
**Pay from the total debt — the user splits it.**

- `allocation: "manual"` and `orders: [{order_id, amount}]` (invoices from `unpaid-orders`).
- **The amounts must add up to `amount`**, otherwise 422.
- Each amount ≤ what that invoice still owes.
- Each invoice must be this customer's, completed and unpaid, otherwise 422 **and nothing is recorded**.
- Invoices may come from different branches; the receipt goes to the user's branch drawer.

Frontend tip: when an invoice is ticked, prefill its remaining amount and let the user edit it.
MD,
        ],
        [
            'name' => 'center.customers.payments — سداد فاتورة معينة',
            'body' => ['amount' => 400, 'allocation' => 'manual', 'orders' => [['order_id' => 2, 'amount' => 400]]],
            'ar' => '**سداد فاتورة معينة** = `manual` بفاتورة واحدة (المبلغ = `amount`). هنا `method` مش متبعت → بيتسجل `cash`.',
            'en' => '**Pay a specific invoice** = `manual` with a single invoice (its amount = `amount`). `method` is omitted here → recorded as `cash`.',
        ],
    ],
    'center.orders.store' => [
        [
            'name' => 'center.orders.store — عربية متسجلة (أوردر مفتوح)',
            'body' => ['vehicle_id' => 1, 'odometer' => 52000, 'technician_id' => null, 'items' => [['product_id' => 1, 'qty' => 1], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 200]], 'discount' => 0, 'next_service_odometer' => 57000, 'next_service_date' => '2027-01-15'],
            'ar' => 'أوردر مفتوح لعربية متسجلة (العربية لسه في الشغل)، يتقفل بعدين من `orders/{id}/complete`. الشرح الكامل للحقول في الـ request التاني.',
            'en' => 'An open order for a registered vehicle (still being worked on), completed later via `orders/{id}/complete`. Full field docs are on the other request.',
        ],
        [
            'name' => 'center.orders.store — عميل جديد + حفظ الفاتورة وتحصيل',
            'body' => ['customer' => ['name' => 'محمد علي', 'phone' => '01012345678'], 'vehicle' => ['plate_number' => 'أ ب ج 1234', 'vin' => null, 'car_make_id' => 1, 'car_model_id' => null, 'year' => 2019], 'odometer' => 45000, 'items' => [['product_id' => 1, 'qty' => 1], ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]], 'discount' => 50, 'next_service_odometer' => 50000, 'next_service_date' => '2027-01-15', 'complete' => true, 'payments' => [['amount' => 300, 'method' => 'cash']]],
        ],
    ],
];

$paging = ['page', 'per_page'];

$fieldsOnly = [
    'center.whatsapp.messages' => ['type', 'status', 'from', 'to', ...$paging],
    'admin.maintenance-centers.index' => ['q', 'is_active', 'expired', ...$paging],
    'center.users.index' => ['q', 'role', 'branch_id', 'is_active', ...$paging],
    'center.audit-logs.index' => ['auditable_type', 'auditable_id', 'event', 'user_id', 'from', 'to', ...$paging],
    'center.search' => ['q'],
    'center.customers.index' => ['q', 'has_debt', 'format', ...$paging],
    'center.customers.statement' => ['from', 'to', 'vehicle_id', 'format'],
    'center.customers.unpaid-orders' => ['vehicle_id'],
    'center.vehicles.index' => ['q', 'customer_id', 'car_make_id', 'format', ...$paging],
    'center.vehicles.history' => $paging,
    'center.vehicles.payments' => ['amount', 'method', 'notes'],
    'center.pos.catalog' => ['q'],
    'center.categories.index' => ['is_active'],
    'center.routine-services.index' => ['q', 'is_active', ...$paging],
    'center.routine-services.history' => $paging,
    'center.products.index' => ['q', 'category_id', 'kind', 'is_active', 'format', ...$paging],
    'center.orders.index' => ['q', 'status', 'vehicle_id', 'customer_id', 'technician_id', 'include_opening_balance', 'from', 'to', 'format', ...$paging],
    'center.orders.payments' => ['amount', 'method', 'notes'],
    'center.orders.print' => ['output', 'size'],
    'center.orders.inspection.print' => ['output'],
    'center.receipts.index' => ['q', 'customer_id', 'method', 'voided', 'from', 'to', ...$paging],
    'center.receipts.print' => ['output', 'size'],
    'center.employees.index' => ['q', 'is_active', ...$paging],
    'center.employees.statement' => ['include_voided', ...$paging],
    'center.payroll.preview' => ['period'],
    'center.expenses.index' => ['category_id', 'method', 'include_voided', 'from', 'to', 'format', ...$paging],
    'center.expenses.summary' => ['category_id', 'method', 'from', 'to'],
    'center.reports.sales' => ['from', 'to', 'format'],
    'center.reports.profit' => ['from', 'to', 'format'],
    'center.reports.cash-flow' => ['from', 'to', 'format'],
    'center.reports.debts' => ['sort', 'format', ...$paging],
    'center.reports.service-due' => ['days', ...$paging],
];

$cashDefault = ['method' => 'default: `cash`'];

$fieldNotes = [
    'center.orders.payments' => $cashDefault,
    'center.vehicles.payments' => $cashDefault,
    'center.customers.payments' => [
        ...$cashDefault,
        'allocation' => 'default: `oldest` · `manual` needs `orders`',
        'orders' => 'manual only',
        'vehicle_id' => 'oldest only',
    ],
    'center.expenses.store' => $cashDefault + ['spent_at' => 'default: today'],
    'center.reports.service-due' => ['days' => 'default: 7'],
    'center.routine-services.index' => ['per_page' => 'default: 50'],
    'center.orders.print' => ['output' => 'default: `json`', 'size' => 'default: `a4`'],
    'center.orders.inspection.print' => ['output' => 'default: `json`'],
    'center.receipts.print' => ['output' => 'default: `json`', 'size' => 'default: `thermal`'],
    'center.reports.sales' => ['from' => 'default: today', 'to' => 'default: `from`'],
    'center.reports.profit' => ['from' => 'default: today', 'to' => 'default: `from`'],
    'center.reports.cash-flow' => ['from' => 'default: today', 'to' => 'default: `from`'],
];

$requestInput = [
    'center.customers.payments' => ['allocation' => 'manual'],
];
