# Dawar Backend

API لنظام SaaS بيدير مراكز صيانة العربيات في مصر. كل المراكز على قاعدة بيانات واحدة (multi-tenant)، والمشروع API بس من غير واجهة.

- **التقنيات:** Laravel 13، PHP 8.4، MySQL 8، Sanctum.
- **الشرح الكامل للمشروع:** [docs/PROJECT_OVERVIEW.md](docs/PROJECT_OVERVIEW.md) (الهيكلية، الجداول، الـ flows، الصلاحيات).
- **المتطلبات وتصميم الداتابيز:** [brief.md](brief.md) و [shortdoc.md](shortdoc.md).

---

## التشغيل محلياً

```bash
composer install
cp .env.example .env          # وظبط DB_DATABASE=dawar
php artisan key:generate
php artisan migrate
php artisan db:seed --class=DemoSeeder
php artisan storage:link
php artisan serve             # http://localhost:8000
```

**ترجع البيانات من الأول:** الأمر ده **بيمسح كل حاجة** في الداتابيز المحلية.

```bash
php artisan migrate:fresh --drop-views --seed --seeder=DemoSeeder
```

| Seeder | بيعمل إيه | يتشغل فين |
|---|---|---|
| `DatabaseSeeder` | الماركات والموديلات (بلوجوهاتها) + حساب الـ Super Admin من `DAWAR_ADMIN_*` | كل البيئات، والسيرفر كمان |
| `DemoSeeder` | كل اللي فوق + مركزين تجربة | **محلي و demo بس** |

---

## حسابات الدخول (DemoSeeder)

> كلمة السر لكل الحسابات: **`password`**

**Super Admin** على `POST /api/v1/admin/auth/login`:

| الإيميل | الدور |
|---|---|
| `admin@dawar.test` | Super Admin (المراكز والفروع والماركات) |

**المراكز** على `POST /api/v1/center/auth/login`:

| المركز | الإيميل | الدور |
|---|---|---|
| **مركز الدوار التجريبي** (فرع واحد، فيه داتا كاملة) | `owner@dawar.test` | صاحب المركز |
| | `cashier@dawar.test` | كاشير |
| | `tech@dawar.test` | فني |
| | `worker@dawar.test` | فني وكاشير |
| **مركز النور** (فرع واحد، **فاضي** زي مركز لسه مشترك) | `owner2@dawar.test` | صاحب المركز |

**داتا مركز الدوار التجريبي:**
- 8 عملاء بعربياتهم.
- 9 منتجات وخدمات بمخزون.
- 6 فواتير: مدفوعة كامل، وجزئي، وآجل.
- أوردر مفتوح.
- رصيد افتتاحي.
- 4 موظفين.
- مصروفات.

**مركز النور:** فيه بس الـ owner والفرع الرئيسي والتصنيفات الافتراضية، ومفيش أي عملاء أو منتجات أو فواتير. يعني كأن الأدمن لسه عامله.

> على السيرفر حساب الأدمن بياخد الإيميل وكلمة السر من `DAWAR_ADMIN_EMAIL` و `DAWAR_ADMIN_PASSWORD` في `.env`. **ماتستخدمش `password` هناك.**

---

## ملاحظات للفرونت

- **الـ Base URL:** `/api/v1/center` للمراكز و `/api/v1/admin` للأدمن.
- **التوكن:** بيتبعت في `Authorization: Bearer {token}`.
- **الـ Headers:**
  - `lang`: `ar` (الافتراضي) أو `en`.
  - `X-Branch-Id`: للـ owner لو عايز يشتغل على فرع معين.
- **شكل الرد:** كله `{status, msg, data, pagination?}`.
- **الماركات والموديلات:** `GET /api/v1/center/car-makes` بيرجّع كل ماركة بـ `name_ar` و `name_en` و `logo_url` وموديلاتها. **الفرونت ياخد الماركات منه بس**، ويبعت `car_make_id` و `car_model_id`، وسنة الموديل في `year`. كده الإدخال موحّد.
  - اللوجوهات من [car-logos-dataset](https://github.com/filippofilip95/car-logos-dataset)، ومثبتة على commit معيّن فمش هتتغير.
  - الأدمن يقدر يضيف ماركة بلوجو (`logo_url`) من `POST /api/v1/admin/car-makes`.
- **القوائم الثابتة** (طرق الدفع، الحالات، الأدوار...): `GET /api/v1/center/options`.
- **Postman:** استورد الملفين اللي في [postman/](postman/)، وشغّل `center.auth.login`. التوكن ورقم الفرع بيتحفظوا لوحدهم. وشرح كل request موجود في تبويب **Docs** بتاعه.

### تحديث Postman بعد أي تعديل في الـ APIs

الملف بيتولّد من التطبيق نفسه. تبويب **Docs** في كل request فيه شرح عربي وإنجليزي، وبعده الصلاحية والأدوار وجدول المدخلات، ودول الاتنين **بيتقروا من الكود**، فمش ممكن يختلفوا عن الـ API.

1. اكتب شرح الـ endpoint بالعربي والإنجليزي في `postman/build/docs.php`. لو نسيته، الأمر هيفشل ويقولك الـ endpoint الناقص.
2. ضيف مثال الـ body أو الـ query في `postman/build/examples.php`.
3. شغّل:
   ```bash
   composer postman
   ```
4. اعمل commit لملف `postman/Dawar.postman_collection.json`.

---

## رسائل واتساب

لما فاتورة تتقفل، العميل بتوصله **صورة الفاتورة**، ولما يدفع بتوصله **صورة إيصال**. الإرسال في الخلفية عن طريق بوابة GOWA، والفشل مابيأثرش على البيع وبيتسجل في `GET /api/v1/center/whatsapp/messages`.

**المطلوب على السيرفر:**
1. قيم `GOWA_USERNAME` و `GOWA_PASSWORD` في `.env`، نفس قيم fitZone. عنوان البوابة ليه قيمة افتراضية، ولو اتغير حطه في `GOWA_URL`.
2. **الـ queue worker شغال** بـ `deploy/dawar-api-worker.conf`. من غيره مفيش رسالة هتطلع.
3. Node.js ومتصفح Chrome للصور:
   ```bash
   npm ci --omit=dev
   npx puppeteer browsers install chrome
   ```
   وعلى Linux لازم مكتبات تشغيل Chromium. ولو هتستخدم Chrome متسطب على الجهاز، حط مساره في `BROWSERSHOT_CHROME_PATH`. ولو الـ worker شغال بـ root أو في Docker، خلّي `BROWSERSHOT_NO_SANDBOX=true`.
4. الأدمن يحط `whatsapp_device_id` لكل مركز من `PUT /api/v1/admin/tenants/{id}`.

---

## الـ Tests

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
```

- الـ tests بتشتغل على داتابيز MySQL اسمها **`dawar_test`**، ولازم تعملها مرة واحدة الأول.
- الـ TestCase بيرفض أي داتابيز اسمها مش منتهي بـ `_test`، عشان مايمسحش داتا حقيقية.
