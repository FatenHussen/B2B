# تطبيق التاجر — عقد API (Flutter)

| | |
|---|---|
| الغرض | عقد المسارات الحية لتطبيق التاجر. شاشات المنتج التفصيلية: [`flutter-retailer.md`](flutter-retailer.md). |
| الحالة | **35/35** تاجر ✅ + مشترك/عام عبر [`shared.md`](shared.md) — `status/04` · `06` · `07` |
| الحارس | `auth:app` + `app.kind:retailer` · `X-Client: retailer-android` \| `retailer-ios` |
| Postman | [`retailer.postman_collection.json`](retailer.postman_collection.json) · env `retailer-android` |
| دورة الطلب | [`order-flow.md`](order-flow.md) §2.2 · §5 قرار النقص |
| عند التعارض | الكود ← status ← كتالوج `07-retailer.php` ← هذا الملف |

> **لا** `X-Channel-Id`. التاجر يشتري من عدة قنوات؛ العزل بالملكية لا بالمستأجر الواحد.

---

## المحتويات

| § | |
|---|---|
| 0 | قواعد · مال · ترويسات |
| 1 | دخول وجلسة |
| 2 | مسارات التاجر (35) |
| 3 | **دورة الطلب 2026-11-03 — قرار النقص** |
| 4 | رحلات شاشة |
| 5 | صلاحيات · ممنوع |
| 6 | قبول |

---

## 0. قواعد · مال · ترويسات

1. عميل HTTP واحد (Dio). غلاف ثابت. `error.code` للعقد.
2. مال `int` أصغر وحدة — ل.س بلا ÷100.
3. كل كتابة: `X-Idempotency-Key` إلا OTP العام.
4. `X-Device-Id` ثابت للتثبيت. `X-App-Version` دائماً.
5. أخفِ أزرار الكتابة عند `insufficient_permission`؛ لا تخفِ التصفح بفحص صلاحية فقط.

| ترويسة | قيمة |
|---|---|
| `Authorization` | `Bearer {{token}}` بعد verify-otp |
| `X-Client` | `retailer-android` / `retailer-ios` |
| `X-Device-Id` | UUID ثابت |
| `X-Idempotency-Key` | UUID لكل نيّة كتابة |

---

## 1. دخول وجلسة

عبر العام + المشترك — انظر [`shared.md`](shared.md):

`app-config` → `intro?` → `request-otp` → `verify-otp` → `GET /app/session` → تسجيل إن لزم (`POST /app/retailer/register`).

OTP خارج production: `"000000"` كنص مع `X-Client: retailer-android`. إنتاج: 6 خانات حقيقية.

---

## 2. مسارات التاجر (35) — ملخص

المصدر التفصيلي: `docs/status/04-retailer-app.md` و`flutter-retailer.md` §5.

| مجموعة | أمثلة |
|---|---|
| تسجيل | `POST /app/retailer/register` |
| رئيسية / كتالوج | `home` · `categories` · `products` · `brands` · favorite |
| نواقص رغبة | `GET/POST/DELETE /app/retailer/shortages` (قائمة رغبات — **ليست** نقص التجهيز) |
| سلة | `cart` · lines · sections · **`POST …/cart/submit`** → ينقسم لـ sub_orders حسب القناة |
| طلبات | list · show · cancel · reorder · tracking |
| **نقص تجهيز** | `…/orders/{id}/shortage` · `…/shortage-decision` |
| استلام / مرتجع / تقييم | receipts* · return-requests · rate rep |
| مالية | payments · account summary/statement/export · debts |

قبل الإرسال: `POST /app/pricing/quote` (مشترك).

---

## 3. دورة الطلب — قرار النقص (2026-11-03)

عندما يقلّل المستودع كمية صنف (FIFO عبر `POST /warehouse/stock-shortages`)، الطلب ينتظر قرار المحل.

| EP | طريقة | مسار | جسم |
|---|---|---|---|
| EP-RT-035 | GET | `/app/retailer/orders/{id}/shortage` | — عرض قبل/بعد |
| EP-RT-036 | POST | `/app/retailer/orders/{id}/shortage-decision` | انظر أدناه |

`{id}` = **`sub_order_id`**.

```json
{ "decision": "accept_partial" }
```

أو:

```json
{ "decision": "cancel" }
```

| قرار | أثر |
|---|---|
| `accept_partial` | يكمل بالكمية المخصَّصة؛ الفاتورة على ذلك |
| `cancel` | يلغي الـ sub-order ويحرّر الحجوزات |

بلا نقص معلّق → `404 not_found`.  
أثناء الانتظار: المستودع لا يكمل التقاطاً على هذا الطلب (`409 operation_in_progress`).

لا تخلط مع `GET /app/retailer/shortages` (قائمة «نواقص» كتالوج/رغبات).

---

## 4. رحلات شاشة

| شاشة | تسلسل |
|---|---|
| أول تشغيل | shared OTP → session → register? → home |
| شراء | categories/products → quote → cart → **submit** (انقسام قنوات) |
| متابعة | orders → show → tracking |
| **نقص تجهيز** | إشعار/قائمة طلبات → shortage → decision |
| استلام | receipts → عدّل بنود → confirm → rate? |
| حساب | summary · debts · payments · statement |

---

## 5. صلاحيات · ممنوع

| رمز | أين |
|---|---|
| `rt.receive.confirm` | تأكيد الاستلام |
| `rt.receive.return_request` | طلب مرتجع |
| `rt.payment.record` | تسجيل دفعة |
| `rt.account.statement` | كشف / تصدير |

ممنوع من هذا التطبيق: مسارات المندوب (`/app/rep/*`) · `POST /app/receipts/reserve` (مندوب) · أي `/channel/*` أو `/warehouse/*`.

---

## 6. قبول

1. 35/35 في `status/04` + مشترك من `shared.md`.
2. قرار النقص مربوط ويعرض الحالتين فقط.
3. submit السلة لا يفترض قناة واحدة.
4. Postman `retailer` يستورد مع env الصحيح؛ التوكن من verify-otp.

*آخر تحديث: 2026-11-03 · يشمل order-flow shortage decision.*
