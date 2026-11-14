# تطبيق التاجر — مواصفة Flutter + عقد الـ API

| | |
|---|---|
| المستند | **العقد الوحيد** لبناء تطبيق التاجر (Flutter) على واجهة Laravel هذه |
| الجمهور | مطوّر Flutter، وCursor عند إكمال الشاشات، ومراجع القبول |
| الحارس | `auth:app` + `app.kind:retailer` · `X-Client: retailer-android` أو `retailer-ios` · **لا** `X-Channel-Id` |
| الأساس | `/api/v1` |
| التاريخ | 2026-11-03 — 35/35 ✅ + قرار نقص التجهيز (order-flow) · العقد المختصر: [retailer.md](retailer.md) |
| الشقيق | المندوب: [flutter-rep.md](flutter-rep.md) · الدورة: [order-flow.md](order-flow.md) · الفهرس: [ALL.md](ALL.md) |

> **قاعدة الأولوية:** الكود الحي ← `docs/status/04-retailer-app.md` + `06-shared-app.md` + `07-public.md` ← الكتالوج `07-retailer.php` + `09-shared-app.php` + `00-public.php` ← هذا الملف.
> جملة هنا تخالف الكنترولر → الكود يفوز. مسار بلا حياة في `status/` → لا تختلقه.

---

## المحتويات

| § | القسم |
|---|---|
| 0 | رموز · مال · بيئة · هاتف |
| 1 | من هو التاجر |
| 2 | هيكل Flutter |
| 3 | الغلاف والترويسات والأخطاء |
| 4 | عام + جلسة (OTP · refs · session) |
| 5 | مسارات التاجر الحية (33) |
| 6 | مشترك التطبيقين (ما يخص التاجر) |
| 7 | رحلات شاشة → API |
| 8 | صلاحيات `rt.*` |
| 9 | بذور QA |
| 10 | ممنوع / حدود |

---

## 0. رموز · مال · بيئة

### 0.1 الرموز

| رمز | المعنى |
|---|---|
| ✅ | حي — Dio حقيقي |
| 🔁 | يحتاج `X-Idempotency-Key` (UUID لكل تأكيد) |
| ⛔ | غير موجود — لا تستدعِ |

لا تخفِ شاشات بفحص `rt.*` للتصفح؛ أخفِ **أزرار الكتابة** فقط عند نقص الصلاحية (`insufficient_permission` + `permission` في الخطأ).

### 0.2 المال

كل مبلغ `int` بأصغر وحدة. ل.س `decimals = 0`:

- خادم: `12000` → عرض: `12,000 ل.س`
- **لا تقسم على 100.** لا `double` في نماذج المال.

### 0.3 الوقت

`Asia/Damascus`. اعرض أوقات الخادم كما هي.

### 0.4 البيئة

| مفتاح | محلي |
|---|---|
| `BASE_URL` | `http://127.0.0.1:8000` (محاكي Android: `10.0.2.2`) |
| `X-Client` | `retailer-android` / `retailer-ios` |
| `X-Device-Id` | UUID ثابت (SecureStorage) |
| `X-App-Version` | مثل `1.0.0 (1)` |
| OTP محلي | نص `"000000"` (ست أصفار). إنتاج 6 خانات عشوائية |
| لا إيميل / لا كلمة سر | OTP فقط |

### 0.5 الهاتف

طبّع قبل الإرسال: `0931000001` → `+963931000001`. القاعدة `^\+9639\d{8}$`.

---

## 1. من هو التاجر

- حساب `app` نوع `retailer`. هاتف واحد = نوع واحد.
- إن `user_type === "rep"` → تطبيق خاطئ — اخرج فوراً. لا مبدّل أدوار.
- التاجر يشتري من **عدة قنوات**؛ `/app/*` **لا** يضبط مستأجراً، و**ممنوع** إرسال `X-Channel-Id`.
- السلة متعددة الأقسام (`sections`) — قسم لكل قناة/قائمة.
- التسجيل: `POST /app/retailer/register` بعد OTP عندما الحساب جديد.

### حسابات البذرة (`php artisan db:seed`)

| هاتف | المحل | ملاحظة |
|---|---|---|
| `+963931000001` / `0931000001` | سوبر ماركت الأمانة (المزة) | نشط · قوائم أسعار |
| `+963931000002` | بقالية النور | نشط |
| OTP محلي | `"000000"` كنص | الرقم `0` يُرفض |

---

## 2. هيكل Flutter مقترح

```
lib/
  core/          # dio, envelope, money, phone, idempotency, secure_storage
  features/
    auth/        # OTP · register
    home/        # home · home-blocks · banners
    catalog/     # categories · products · brands · favorites · shortages
    cart/        # cart · quote · submit
    orders/      # list · detail · tracking · cancel · reorder
    receive/     # receipts · confirm · returns · rate rep
    account/     # summary · statement · debts · payments
    offers/
    loyalty/
    notifications/
    sync/
  routing/
```

شريط سفلي مقترح: رئيسية · كتالوج · سلة · طلباتي · حساب.

---

## 3. الغلاف والترويسات

### ترويسات كل طلب

| ترويسة | قيمة |
|---|---|
| `Accept` | `application/json` |
| `Authorization` | `Bearer <token>` بعد الدخول |
| `X-Client` | `retailer-android` / `retailer-ios` |
| `X-Device-Id` | UUID الجهاز |
| `X-App-Version` | نسخة البناء |
| `X-Idempotency-Key` | كل `POST`/`PUT`/`PATCH`/`DELETE` إلا OTP وطلب الرمز |
| `X-Channel-Id` | **أبداً** |

نجاح: `{ "data": …, "meta": { "server_time", … } }`  
خطأ: `{ "error": { "code", "message", "permission?", "details?" } }`

| HTTP | `code` شائع |
|---|---|
| 401 | `unauthenticated`, `token_revoked` |
| 403 | `wrong_guard`, `insufficient_permission` |
| 404 | `not_found` (يشمل خارج الملك) |
| 409 | `illegal_transition`, `operation_in_progress`, `idempotency_key_conflict` |
| 422 | `validation_failed` |

توكن مندوب على مسار تاجر (أو العكس عبر kind) → ارفض محلياً بعد `session`؛ مسار حارس آخر → `wrong_guard`.

---

## 4. عام + جلسة

| EP | طريقة | مسار | ملاحظة |
|---|---|---|---|
| EP-CM-001 | POST | `/public/auth/request-otp` | `{ phone, purpose: "login"\|"register", … }` |
| EP-CM-002 | POST | `/public/auth/verify-otp` | → token عند حساب موجود |
| EP-CM-003 | POST | `/public/auth/resend-otp` | |
| EP-PB-001 | GET | `/public/refs` | تسجيل (محافظات، أنشطة…) |
| EP-PB-010 | GET | `/public/app-config` | فحص النسخة |
| EP-PB-011 | GET | `/public/content/intro` | انترو |
| EP-CM-004 | GET | `/app/session` | إقلاع: `user_type`, permissions, flags |
| EP-CM-005 | POST | `/app/auth/logout` | 🔁 |

بعد verify بدون ملف تاجر مكتمل → شاشة `register` ثم `EP-RT-001`.

---

## 5. مسارات التاجر الحية (33/33)

المصدر: `docs/status/04-retailer-app.md`.

### تسجيل · رئيسية · كتالوج

| EP | طريقة | مسار | صلاحية | 🔁 |
|---|---|---|---|---|
| EP-RT-001 | POST | `/app/retailer/register` | — | ✔ |
| EP-RT-010 | GET | `/app/retailer/home` | — | |
| EP-RT-011 | GET | `/app/retailer/categories` | — | |
| EP-RT-012 | GET | `/app/retailer/products` | — | |
| EP-RT-013 | GET | `/app/retailer/products/{id}` | — | |
| EP-RT-014 | POST | `/app/retailer/products/{id}/favorite` | — | ✔ |
| EP-RT-015A | GET | `/app/retailer/brands` | — | |
| EP-RT-015B | GET | `/app/retailer/brands/{id}` | — | |

### نواقص

| EP | طريقة | مسار | 🔁 |
|---|---|---|---|
| EP-RT-070A | GET | `/app/retailer/shortages` | |
| EP-RT-070B | POST | `/app/retailer/shortages` | ✔ |
| EP-RT-070C | DELETE | `/app/retailer/shortages/{id}` | ✔ |

### سلة

| EP | طريقة | مسار | 🔁 |
|---|---|---|---|
| EP-RT-020 | GET | `/app/retailer/cart` | |
| EP-RT-021 | POST | `/app/retailer/cart/lines` | ✔ |
| EP-RT-022 | PATCH | `/app/retailer/cart/lines/{id}` | ✔ |
| EP-RT-023 | DELETE | `/app/retailer/cart/lines/{id}` | ✔ |
| EP-RT-024 | PATCH | `/app/retailer/cart/sections/{ref}` | ✔ |
| EP-RT-025 | POST | `/app/retailer/cart/submit` | ✔ |

قبل الإرسال: `POST /app/pricing/quote` (مشترك) لعرض الأسعار النهائية.

### طلباتي

| EP | طريقة | مسار | 🔁 |
|---|---|---|---|
| EP-RT-030 | GET | `/app/retailer/orders` | |
| EP-RT-031 | GET | `/app/retailer/orders/{id}` | |
| EP-RT-032 | POST | `/app/retailer/orders/{id}/cancel` | ✔ |
| EP-RT-033 | POST | `/app/retailer/orders/{id}/reorder` | ✔ |
| EP-RT-034 | GET | `/app/retailer/orders/{id}/tracking` | |
| EP-RT-035 | GET | `/app/retailer/orders/{id}/shortage` | |
| EP-RT-036 | POST | `/app/retailer/orders/{id}/shortage-decision` | ✔ |

### استلام · مرتجع · تقييم

| EP | طريقة | مسار | صلاحية | 🔁 |
|---|---|---|---|---|
| EP-RT-040 | GET | `/app/retailer/receipts/{subOrderId}` | `rt.receive.confirm` | |
| EP-RT-041 | PATCH | `/app/retailer/receipts/{id}/lines/{lineId}` | `rt.receive.confirm` | ✔ |
| EP-RT-042 | POST | `/app/retailer/receipts/{id}/confirm` | `rt.receive.confirm` | ✔ |
| EP-RT-043 | POST | `/app/retailer/return-requests` | `rt.receive.return_request` | ✔ |
| EP-RT-044 | GET | `/app/retailer/return-requests` | — | |
| EP-RT-045 | POST | `/app/retailer/reps/{id}/rate` | — | ✔ |

### دفعات وحساب

| EP | طريقة | مسار | صلاحية | 🔁 |
|---|---|---|---|---|
| EP-RT-050 | POST | `/app/retailer/payments` | `rt.payment.record` | ✔ |
| EP-RT-051 | GET | `/app/retailer/account/summary` | — | |
| EP-RT-052 | GET | `/app/retailer/account/statement` | `rt.account.statement` | |
| EP-RT-053 | POST | `/app/retailer/account/statement/export` | `rt.account.statement` | ✔ |
| EP-RT-054 | GET | `/app/retailer/debts` | — | |

الأجسام التفصيلية: كتالوج `07-retailer.php` واختبارات `tests/Feature/` ذات الصلة. لا تعتمد على شكل قديم في جذر `DocsLast/apps/`.

---

## 6. مشترك يخص التاجر

| EP | طريقة | مسار | ملاحظة |
|---|---|---|---|
| EP-APP-030 | POST | `/app/pricing/quote` | تسعير قبل/مع السلة |
| EP-APP-040 | GET | `/app/offers` | |
| EP-APP-041 | GET | `/app/offers/{id}` | |
| EP-CM-060…063 | * | `/app/notifications*` · `/app/devices/push-token` | |
| EP-SY-001…004 | * | `/app/sync/*` | سحب/دفع/تعارض |
| EP-APP-100 | GET | `/app/content/home-blocks` | بلوكات الرئيسية |
| EP-APP-101 | POST | `/app/content/banners/{id}/click` | ✔ |
| EP-APP-110 | GET | `/app/loyalty` | |
| EP-APP-111 | POST | `/app/loyalty/redeem` | ✔ |

`EP-CM-050 POST /app/receipts/reserve` صلاحية `rp.payment.collect` — **مندوب**؛ لا تستخدمه من تطبيق التاجر.

---

## 7. رحلات شاشة → API

| شاشة | تسلسل |
|---|---|
| أول تشغيل | `app-config` → `intro?` → OTP → `session` → `register?` → `home` |
| تصفّح وشراء | `categories` / `products` → `quote` → `cart/lines` → `cart/submit` |
| متابعة | `orders` → `orders/{id}` → `tracking` |
| نقص تجهيز (order-flow) | `orders/{id}/shortage` → `shortage-decision` (`accept_partial` \| `cancel`) — ليس قائمة `/shortages` الرغبات |
| عند التسليم | `receipts/{subOrderId}` → عدّل بنود → `confirm` → اختياري `reps/{id}/rate` |
| حساب | `account/summary` · `debts` · `payments` · `statement` |

---

## 8. صلاحيات الكتابة الشائعة

| رمز | أين يظهر النقص |
|---|---|
| `rt.receive.confirm` | تأكيد الاستلام |
| `rt.receive.return_request` | طلب مرتجع |
| `rt.payment.record` | تسجيل دفعة |
| `rt.account.statement` | كشف / تصدير |

عند `403 insufficient_permission`: أخفِ الزر أو اعرض رسالة الصلاحية — لا تعِد المحاولة صامتة.

---

## 9. بذور QA

1. `migrate:fresh --seed`
2. هاتف `0931000001` + OTP `"000000"`
3. `GET /app/session` → `user_type: retailer`
4. `GET /app/retailer/home` ثم أضف للسلة وأرسل طلباً تجريبياً بمفتاح تكرار جديد

---

## 10. ممنوع / حدود

| لا تفعل | البديل |
|---|---|
| استدعاء `/app/rep/*` | تطبيق المندوب فقط |
| استدعاء `/channel/*` `/platform/*` `/warehouse/*` | لوحات أخرى |
| إرسال `X-Channel-Id` | العزل بالملكية على `/app/*` |
| قسمة المال على 100 | أصغر وحدة كما هي |
| اختلاق مسار ناقص في status | `RemoteNotReady` أو أخفِ الزر |

التاجر **متعدد القنوات** في سلة واحدة؛ لا تفترض قناة واحدة في الواجهة.

---

*آخر تحديث: 2026-09-25 · التاجر 33/33 · مشترك 18/18 · عام 6/6.*
