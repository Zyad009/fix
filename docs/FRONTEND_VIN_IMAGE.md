# صورة رقم الشاسيه للعربية

> تحديث في API العربيات · 31 أكتوبر 2026 · لفريق الفرونت إند

## الخلاصة

العربية بقى ممكن يكون ليها **صورة واحدة لرقم الشاسيه**، وهي اختيارية. الصورة بتترفع من نفس APIs الإضافة والتعديل الموجودة، ومفيش endpoint جديد.

| | |
|---|---|
| حقل الرفع | `vin_image` في `multipart/form-data` |
| حقل القراءة | `vin_image_url` في أي رد فيه عربية |
| الأنواع والحجم | jpg, jpeg, png, webp بحد أقصى **4 ميجا** |
| الصلاحية | `owner` و `cashier` و `technician_cashier`. الفني ممنوع |

---

## 1. إضافة عربية بصورة

`POST /api/v1/center/vehicles`

نفس طلب الإضافة الحالي، بس يتبعت `multipart/form-data` لو فيه صورة. من غير صورة، الطلب يفضل JSON زي ما هو.

```js
const form = new FormData();
form.append('customer_id', '1');
form.append('plate_number', 'ب ج 456');
form.append('vin', 'JTDBR32E720123456'); // اختياري
form.append('vin_image', file);          // File من <input type="file">

await fetch(`${API}/center/vehicles`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json', lang: 'ar' },
  body: form, // ماتحطش Content-Type بنفسك، المتصفح بيحطه مع الـ boundary
});
```

## 2. رفع أو تغيير الصورة لعربية موجودة

`POST /api/v1/center/vehicles/{id}` ومعاه `_method=PUT`

> ⚠️ **مهم:** الطلب لازم يتبعت `POST` ومعاه حقل `_method` قيمته `PUT`. لو اتبعت `PUT` مباشرة بملف، PHP مش هيقرا الملف والصورة مش هتتحفظ.

```js
const form = new FormData();
form.append('_method', 'PUT');
form.append('vin_image', file);
form.append('color', 'أسود'); // ممكن يتبعت معاها أي حقل تعديل تاني

await fetch(`${API}/center/vehicles/${id}`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
  body: form,
});
```

الصورة القديمة بتتمسح من السيرفر أوتوماتيك لما تترفع صورة جديدة.

## 3. مسح الصورة

`PUT /api/v1/center/vehicles/{id}`

```json
{ "vin_image": null }
```

طلب JSON عادي. الصورة بتتمسح و `vin_image_url` بيرجع `null`.

> ✅ أي `PUT` مافيهوش `vin_image` خالص مابيلمسش الصورة، فشاشة التعديل الحالية مش محتاجة تتغير.

## 4. عرض الصورة

الحقل الجديد `vin_image_url` بيرجع في كل رد فيه عربية: الإضافة، والتعديل، وصفحة العربية `GET vehicles/{id}`، وقايمة العربيات.

```json
{
  "id": 12,
  "plate_number": "ب ج 456",
  "vin": "JTDBR32E720123456",
  "vin_image_url": "https://.../storage/tenants/1/vehicles/AbC123.jpg",
  "make": { "...": "..." }
}
```

القيمة رابط كامل يتحط في `<img src>` مباشرة، أو `null` لو مفيش صورة.

## 5. الأخطاء

| الحالة | السبب |
|---|---|
| `422` | الملف مش صورة، أو نوعه مش jpg/jpeg/png/webp، أو أكبر من 4 ميجا |
| `404` | العربية مش موجودة أو تبع مركز تاني |
| `403` | المستخدم مالوش صلاحية `customers.manage` (زي الفني) |
| `413` | الملف أكبر من حد السيرفر نفسه، والرد ده ممكن مايكونش JSON |

شكل خطأ الـ validation:

```json
{ "status": 0, "code": 422, "message": "يجب أن يكون صورة الشاسيه صورة.", "data": null }
```

ابعت header `Accept: application/json` دايماً، عشان الأخطاء ترجع JSON مش redirect.

## 6. توصيات

- **اضغط الصورة قبل الرفع:** صور الموبايل ساعات بتعدي 4 ميجا. تصغيرها لعرض حوالي 1600px بجودة 0.8 بيخليها أقل من ميجا وواضحة.
- **اتحقق من الحجم والنوع قبل الإرسال** عشان المستخدم ياخد رسالة واضحة من غير ما يستنى السيرفر.
- على الموبايل استخدم `accept="image/*" capture="environment"` عشان الكاميرا الخلفية تفتح على طول.
- اعرض الصورة الحالية في صفحة العربية مع زرارين: تغيير ومسح.

## 7. اللي مش مدعوم

- إضافة عربية جديدة من **شاشة الفاتورة** أو من **إضافة عميل بعربياته** مابتقبلش صورة. الصورة بتترفع بعدها من صفحة العربية.
- صورة واحدة بس لكل عربية، مش ألبوم.

## Postman

في فولدر العربيات اتضاف طلبين جاهزين:

- `center.vehicles.store — مع صورة الشاسيه`
- `center.vehicles.update — رفع أو تغيير صورة الشاسيه`

اختار ملف الصورة في حقل `vin_image` من تاب Body قبل ما تبعت.
