# لوحة المستودع — عقد Next.js 15 + الـ API الحي

| | |
|---|---|
| الغرض | العقد التشغيلي الوحيد لبناء **لوحة المستودع** على هذا الخادم. أداة أرضية (لمس / ماسح)، ليست لوحة تقارير قناة. |
| الحالة | **32/32** مسار ✅ — `docs/status/03-warehouse.md` (مولَّد 2026-11-03) |
| التطبيق | Next.js 15 · App Router · TypeScript · TanStack Query v5 · RTL · المنفذ **3002** |
| الحارس | `auth:warehouse` · Sanctum PAT مربوط بجهاز (`device_uuid`) |
| الشقيق | الكتالوج `06-warehouse.php` · دورة الطلب [`order-flow.md`](order-flow.md) · تذكرة [`../plan/order-flow-gaps.md`](../plan/order-flow-gaps.md) |
| عند التعارض | `route:list` → الكنترولر/FormRequest → هذا الملف → الكتالوج |

> **لـ Cursor / مطوّر أول.** اقرأ §0 ثم §5 (دورة الطلب اليوم) قبل أي شاشة. لا تختلق مساراً. لا تحاكِ أرصدة. لا تبنِ GMV هنا.

---

## المحتويات

| § | القسم |
|---|---|
| 0 | قواعد الوكيل والعقد العابر |
| 1 | البيئة · الجهاز · البذرة |
| 2 | هيكل Next.js والتنقّل |
| 3 | الأخطاء والصلاحيات |
| 4 | الدخول |
| 5 | **دورة الطلب — تعديلات 2026-11-03** |
| 6 | مرجع كل المسارات الحية (32) |
| 7 | رحلة أرضية كاملة (وارد → خروج → عودة) |
| 8 | حدود وممنوعات |
| 9 | خريطة الشاشات + قبول |
| 10 | قائمة مراجعة قبل الربط |

---

## 0. قواعد الوكيل

1. **عميل HTTP واحد** (`apiClient`). الصفحات لا تستدعي `fetch`.
2. **غلاف ثابت:** نجاح `{ data, meta }` · خطأ `{ error: { code, message, details? } }`.
3. **`error.code` هو العقد.** الواجهة تترجم `code` إلى عربي؛ `message` للـ log فقط.
4. **كل كتابة تحمل `X-Idempotency-Key`** إلا `POST /warehouse/auth/device-login`. مفتاح = نيّة مستخدم واحدة. إعادة نفس النقرة = نفس المفتاح.
5. **المال نادر؛ إن ظهر فـ `int` بأصغر وحدة** (ل.س بدون ÷100). لا `float`.
6. **لا `X-Channel-Id`.** المستأجر من جهاز المستودع المسجَّل (`warehouse_id` / `channel_id`).
7. **أغلق الأزرار بـ `permissions[]` من device-login** — `can('wh.orders.confirm')`. لا تبنِ على اسم الدور وحده.
8. **معرّف أجنبي → 404** لا 403. اعرض «غير موجود».
9. **توكن حارس آخر → `403 wrong_guard`.** امسح التوكن وأعد للدخول.
10. **هذه محطة أرضية.** التقارير هنا شاشة إنتاجية واحدة (`EP-WH-050`). GMV واللقطة اليومية = لوحة القناة.
11. **من يوافق على الطلب؟** يقرّره إعداد القناة `settings.order_approver` (`channel` | `warehouse`). راجع §5 قبل إظهار زر التأكيد.

---

## 1. البيئة · الجهاز · البذرة

### 1.1 المضيفون

| الهدف | الأساس |
|---|---|
| API محلي | `http://127.0.0.1:8000` |
| إنتاج | `https://api.sentraxsy.com` |
| هذه اللوحة | المنفذ **3002** — ليست القناة (3001) ولا المنصة (3000) |

```ts
// lib/config.ts
export const apiHost = process.env.NEXT_PUBLIC_API_HOST ?? 'http://127.0.0.1:8000';
export const baseUrl = `${apiHost}/api/v1`;
export const xClient = 'warehouse-web';
```

`.env.local`: `NEXT_PUBLIC_API_HOST=http://127.0.0.1:8000`

### 1.2 الترويسات

| الترويسة | القيمة | متى |
|---|---|---|
| `Accept` | `application/json` | دائماً |
| `Content-Type` | `application/json` | الكتابات |
| `Authorization` | `Bearer <token>` | بعد الدخول |
| `X-Client` | `warehouse-web` | دائماً |
| `X-Device-Id` | UUID ثابت للجهاز | دائماً |
| `X-App-Version` | `1.0.0 (1)` | دائماً |
| `X-Idempotency-Key` | UUID v4 | كل كتابة إلا device-login |
| `X-Channel-Id` | — | **أبداً** |

إعادة كتابة مخزّنة: ترويسة الاستجابة `Idempotent-Replayed: true`.

### 1.3 تسجيل الجهاز (CLI — ليس API عاماً)

```bash
php artisan warehouse:register-device {plain_token} {4digit_pin} {warehouse_id} {channel_id} --label="محطة 1"
```

ثم:

```http
POST /api/v1/warehouse/auth/device-login
{ "device_token": "<plain_token>", "pin": "4821" }
```

→ `{ token, warehouse: { id, name }, permissions: ["wh.queue.view", "wh.orders.view", ...] }`

| | |
|---|---|
| PIN | 4 أرقام |
| جهاز غير مسجّل | 401 |
| دور البذرة النموذجي | `warehouse_keeper` — كل رموز `wh.*` في `PermissionCatalog` بما فيها `wh.orders.*` (2026-11-03) |

التوكن في `sessionStorage` مفتاح **`warehouse`** — لا تخلطه مع `channel` / `platform`.

---

## 2. هيكل Next.js

```
app/
  (auth)/login/page.tsx
  (floor)/layout.tsx                 # شريط: طوابير + صلاحيات + حالة المزامنة
  (floor)/page.tsx                   # GET /warehouse/queues
  (floor)/inbound/page.tsx           # GET /warehouse/sub-orders  ← جديد 2026-11-03
  (floor)/inbound/[id]/page.tsx
  (floor)/pick/[id]/page.tsx
  (floor)/pack/[id]/page.tsx
  (floor)/handover/page.tsx
  (floor)/receiving/page.tsx
  (floor)/stocktake/...
  (floor)/returns/[id]/page.tsx
  (floor)/lots/page.tsx
  (floor)/shortages/page.tsx         # POST /warehouse/stock-shortages
  (floor)/sync/page.tsx
  (floor)/reports/productivity/page.tsx
lib/api/client.ts
lib/api/permissions.ts               # can(code)
```

---

## 3. الأخطاء والصلاحيات

| HTTP | `error.code` شائع | سلوك الواجهة |
|---|---|---|
| 401 | `unauthenticated`, `token_revoked` | امسح التوكن → دخول |
| 403 | `wrong_guard`, `insufficient_permission` | خروج / أخفِ الزر (`permission` في الجسم) |
| 404 | `not_found` | «غير موجود» |
| 409 | `illegal_transition` | تأكيد من جهة غير المعيَّنة في `order_approver` |
| 409 | `operation_in_progress` | قرار نقص المحل معلّق — امنع إكمال الالتقاط |
| 409 | `idempotency_key_conflict` | ابدأ نيّة جديدة |
| 422 | `validation_failed`, `barcode_not_in_order` | ظلل الحقول / الباركود |
| 423 | `credit_limit_exceeded` / plan | حوار — لا تُكمِل التأكيد |

صلاحيات دورة الطلب الجديدة (ليست في DOC-08؛ مبذورة في الكتالوج):

| رمز | أين |
|---|---|
| `wh.orders.view` | قائمة الوارد |
| `wh.orders.confirm` | تأكيد طلب (فقط إن `order_approver=warehouse`) |
| `wh.orders.assign` | إسناد مندوب |
| `wh.picking.shortage` | نقص سطر قائمة **و** نقص كلي للصنف |

---

## 4. الدخول

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-001 | `POST` | `/warehouse/auth/device-login` | — (بلا حارس) |

لا يوجد logout API — امسح التوكن محلياً.

---

## 5. دورة الطلب — تعديلات 2026-11-03

مصدر القبول: [`order-flow.md`](order-flow.md). هذه الشاشات **إلزامية** قبل الإطلاق التشغيلي للمستودع.

### 5.1 من يوافق؟

القناة تضبط:

```http
PUT /api/v1/channel
{ "settings": { "order_approver": "warehouse" } }
```

| القيمة | زر التأكيد في المستودع |
|---|---|
| `channel` (افتراضي) | **مخفي / معطّل** — الاستدعاء → `409 illegal_transition` |
| `warehouse` | ظاهر إن `can('wh.orders.confirm')` |

في الحالتين: المستودع يرى الوارد عبر `GET /warehouse/sub-orders` إن مُنح `wh.orders.view`. القناة ترى الوارد دائماً.

> الواجهة لا تخمّن الإعداد من عندها في كل طلب إن أمكن: اقرأ `order_approver` من سياق القناة عند التهيئة (شاشة إعدادات القناة)، أو عالج 409 بصمت كـ«لستَ جهة الموافقة».

### 5.2 طابور الوارد · التأكيد · الإسناد

| EP | طريقة | مسار | صلاحية | جسم / ملاحظات |
|---|---|---|---|---|
| EP-WH-060 | `GET` | `/warehouse/sub-orders` | `wh.orders.view` | مرشّحات حالة مثل القناة (`filter[status]=pending`…) |
| EP-WH-061 | `POST` | `/warehouse/sub-orders/{id}/confirm` | `wh.orders.confirm` | `{}` — يحجز مخزوناً؛ طلب مندوب → إسناد تلقائي حتى لو off-duty |
| EP-WH-062 | `POST` | `/warehouse/sub-orders/assign` | `wh.orders.assign` | انظر الجسم أدناه |

```json
{
  "sub_order_ids": [9001],
  "rep_id": 70,
  "mode": "manual"
}
```

`mode`: `manual` | `auto` | `bulk_zone`  
- طلب محل بلا مندوب مناسب → `422`  
- بعد التأكيد الناجح: قوائم التقاط جاهزة للطوابير العادية

### 5.3 نقص صنف — كمية كلية مرة واحدة (FIFO)

**لا** توزّع يدوياً على كل طلب. أدخل الكمية المتوفرة للصنف:

```http
POST /api/v1/warehouse/stock-shortages
```

صلاحية: `wh.picking.shortage`

```json
{
  "product_id": 880,
  "variant_id": null,
  "qty_available": 12,
  "reason": "out_of_stock"
}
```

`reason`: `out_of_stock` | `damaged` | `not_in_location`

النظام يوزّع على الطلبات المفتوحة للتجهيز (`confirmed|assigned|accepted|processing`) حسب **`created_at` تصاعدياً**.

| سيناريو | نتيجة |
|---|---|
| طلبان يحتاجان 10 و 10 · متوفر 12 | الأول 10 · الثاني 2 |
| محل ينتظر قرار نقص | `POST …/picking-lists/{id}/complete` → `409 operation_in_progress` |

قرار المحل (تطبيق التاجر، ليس هنا): `accept_partial` | `cancel` عبر EP-RT-035/036.

> `POST /warehouse/picking-lists/{id}/shortage` (EP-WH-014) يبقى لنقص **سطر قائمة واحدة** (توافق). مصدر «كمية كلية للصنف» = **EP-WH-063 فقط**.

### 5.4 قبول سريع للواجهة

1. `order_approver=channel` → تأكيد مستودع يفشل 409؛ القناة تنجح.
2. `order_approver=warehouse` → تأكيد مستودع ينجح؛ طلب مندوب يُسند تلقائياً.
3. إسناد طلب محل لمندوب يغطي المنطقة.
4. نقص كلي → توزيع FIFO؛ إكمال التقاط أثناء انتظار قرار محل → 409.
5. اختبارات الخادم: `tests/Feature/Ordering/OrderFlowGapsTest.php`.

---

## 6. مرجع كل المسارات الحية (32)

المصدر الحي: `docs/status/03-warehouse.md` — لا تحرّر ذلك الملف يدوياً.

### دخول

| EP | طريقة | مسار |
|---|---|---|
| EP-WH-001 | POST | `/warehouse/auth/device-login` |

### طلبات واردة (2026-11-03)

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-060 | GET | `/warehouse/sub-orders` | `wh.orders.view` |
| EP-WH-061 | POST | `/warehouse/sub-orders/{id}/confirm` | `wh.orders.confirm` |
| EP-WH-062 | POST | `/warehouse/sub-orders/assign` | `wh.orders.assign` |
| EP-WH-063 | POST | `/warehouse/stock-shortages` | `wh.picking.shortage` |

### طوابير · التقاط · موجات

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-010 | GET | `/warehouse/queues` | `wh.queue.view` |
| EP-WH-011 | GET | `/warehouse/picking-lists/{id}` | `wh.picking.execute` |
| EP-WH-012 | POST | `/warehouse/picking-lists/{id}/scan` | `wh.picking.execute` |
| EP-WH-013 | POST | `/warehouse/picking-lists/{id}/lines/{lineId}/manual` | `wh.picking.execute` |
| EP-WH-014 | POST | `/warehouse/picking-lists/{id}/shortage` | `wh.picking.shortage` |
| EP-WH-014A | POST | `/warehouse/picking-lists/batch` | `wh.picking.execute` |
| EP-WH-015 | POST | `/warehouse/picking-lists/{id}/complete` | `wh.picking.execute` |
| EP-WH-042 | POST | `/warehouse/picking-waves` | `wh.picking.execute` |
| EP-WH-042A | GET | `/warehouse/picking-waves/{id}` | `wh.picking.execute` |

### تغليف · عهدة

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-016 | POST | `/warehouse/packing/{id}/verify` | `wh.packing.execute` |
| EP-WH-017 | POST | `/warehouse/packing/{id}/complete` | `wh.packing.execute` |
| EP-WH-018 | GET | `/warehouse/handovers/pending` | `wh.handover.execute` |
| EP-WH-019 | POST | `/warehouse/handovers` | `wh.handover.execute` |
| EP-WH-020 | POST | `/warehouse/handovers/{id}/return-trip` | `wh.handover.return_trip` |

### استلام وارد · جرد · مرتجعات · lots · مزامنة · تقرير

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-021 | POST | `/warehouse/receiving` | `wh.receiving.execute` |
| EP-WH-022 | POST | `/warehouse/receiving/{id}/qc` | `wh.receiving.qc` |
| EP-WH-023A | POST | `/warehouse/stocktakes` | `wh.stocktake.execute` |
| EP-WH-023B | POST | `/warehouse/stocktakes/{id}/lines` | `wh.stocktake.execute` |
| EP-WH-023C | POST | `/warehouse/stocktakes/{id}/submit` | `wh.stocktake.execute` |
| EP-WH-024 | POST | `/warehouse/stocktakes/{id}/approve` | `wh.stocktake.approve` |
| EP-WH-030 | POST | `/warehouse/returns/{id}/sort` | `wh.returns.sort` |
| EP-WH-040 | GET | `/warehouse/stock-lots` | `wh.receiving.lots` |
| EP-WH-040A | POST | `/warehouse/stock-lots/{id}/adjust` | `wh.receiving.lots` |
| EP-WH-041A | POST | `/warehouse/sync/push` | `wh.picking.execute` |
| EP-WH-041B | GET | `/warehouse/sync/status` | `wh.picking.execute` |
| EP-WH-041C | POST | `/warehouse/sync/resolve-conflict` | `wh.picking.execute` |
| EP-WH-050 | GET | `/warehouse/reports/productivity` | `wh.reports.view` |

الأجسام التفصيلية: الكتالوج `docs/api/catalog/06-warehouse.php` واختبارات `tests/Feature/Fulfillment/` + `OrderFlowGapsTest`.

**عيّنة طوابير:**

```json
{
  "data": {
    "queues": {
      "to_pick": 12, "picking": 3, "to_pack": 4, "ready": 6,
      "awaiting_rep": 2, "inbound_returns": 1, "inbound_transfers": 0
    },
    "alerts": [{ "type": "picking_overdue", "count": 2 }]
  }
}
```

**مسح:**

```http
POST /warehouse/picking-lists/11/scan
{ "barcode": "6291000000000", "qty": 1 }
```

باركود خارج الطلب → `422` / `barcode_not_in_order`.

**عهدة خروج:**

```http
POST /warehouse/handovers
{ "rep_id": 70, "sub_order_ids": [9001, 9002] }
```

→ `temp_code` من 4 خانات؛ المندوب يؤكد من تطبيقه. بعد الطرفين: الطلبات → `on_the_way` ويُخصم المخزون.

**عودة يوم:**

```http
POST /warehouse/handovers/{id}/return-trip
{ "undelivered": [{ "sub_order_id": 9001, "reason": "رفض الاستلام" }] }
```

---

## 7. رحلة أرضية كاملة

```text
[اختياري] وارد pending
    → confirm (إن order_approver=warehouse)
    → assign (طلبات محل)
        ↓
طوابير → التقاط (scan/manual) → [نقص كلي؟ stock-shortages]
        → complete (مرفوض إن نقص بانتظار قرار محل)
        → تغليف verify/complete
        → POST handovers + رمز 4 خانات
        → (المندوب يؤكد من التطبيق)
        → نهاية اليوم: return-trip
```

مسارات موازية طوال اليوم: استلام وارد + QC · جرد + اعتماد ثانٍ · فرز مرتجع · lots/FEFO · sync offline.

---

## 8. حدود وممنوعات

| الموضوع | الحقيقة |
|---|---|
| GMV / لقطة يومية / تسعير / عروض | لوحة القناة — ليست هنا |
| خريطة GPS للمندوبين | `/channel/reps/live` — لوحة القناة |
| قرار نقص المحل | تطبيق التاجر (EP-RT-035/036) |
| Push/WhatsApp | غير مضبوط على الخادم (BF-12) — لا تعرض «مُسلَّم» |
| اختلاق مسار خارج الجدول أعلاه | ممنوع — أضف للكتالوج أولاً |

---

## 9. خريطة الشاشات

| # | الشاشة | يستدعي | فارغ / ملاحظة |
|---|---|---|---|
| 1 | دخول الجهاز | device-login | — |
| 2 | الطوابير | queues | أصفار صادقة |
| 3 | **وارد الطلبات** | sub-orders · confirm · assign | أخفِ confirm إن لست جهة الموافقة |
| 4 | التقاط | picking-lists* · waves | «لا قائمة.» |
| 5 | **نقص كلي** | stock-shortages | كمية واحدة للصنف |
| 6 | تغليف | packing* | — |
| 7 | عهدة | handovers* | «لا عهد بانتظار.» |
| 8 | استلام وارد | receiving* | — |
| 9 | جرد | stocktakes* | موافقة ثانية على approve |
| 10 | فرز مرتجع | returns/{id}/sort | — |
| 11 | الدفعات | stock-lots* | FEFO عند الصرف |
| 12 | مزامنة | sync/* | تعارضات صريحة |
| 13 | إنتاجية | reports/productivity | بعد بيانات |

لكل شاشة: `can()` · مفتاح تكرار · هيكل تحميل · خطأ عبر `code` · لا مسار مخترع · RTL مع LTR للأرقام.

---

## 10. قائمة مراجعة قبل الربط

1. العدد 32 يطابق `status/03` حرفياً.
2. `X-Client: warehouse-web` · لا `X-Channel-Id` · مفتاح تكرار على كل كتابة إلا الدخول.
3. `wh.orders.*` مربوطة ومنغلقة بـ `can()`.
4. مسار النقص الكلي يستخدم EP-WH-063 وليس توزيعاً يدوياً في الواجهة.
5. 409 على confirm = لستَ الموافق؛ 409 على complete = انتظر قرار محل.
6. التوكن في `sessionStorage['warehouse']` فقط.

*آخر تحديث: 2026-11-03 · الحالة: 32/32 حيّ · يشمل order-flow gaps.*
