# لوحة المستودع — عقد Next.js 15 + الـ API الحي

| | |
|---|---|
| الغرض | الملف الوحيد الذي يحتاجه وكيل Cursor أو مطوّر Next.js لربط **لوحة المستودع** بهذا الخادم. 28/28 مسار ✅ (`docs/status/03-warehouse.md`). |
| التطبيق | Next.js 15 · App Router · TypeScript · شاشة لمس / ماسح · المنفذ **3002** |
| الحارس | `auth:warehouse` · Sanctum personal access token مربوط بجهاز |
| الشقيق | `docs/status/03-warehouse.md` · الكتالوج `06-warehouse.php` · `docs/plan/channel-warehouse-v1.1.md` (عمق لاحق) |
| عند التعارض | `route:list` → الكنترولر → **هذا الملف** → الكتالوج |

> **لـ Cursor.** اقرأ §0 أولاً. ابنِ مسار الأرضية فقط (§5). لا تختلق تقارير ويب عامة ولا محاكاة مخزون.

---

## المحتويات

| § | القسم |
|---|---|
| 0 | قواعد الوكيل |
| 1 | البيئة والجهاز والبذرة |
| 2 | هيكل Next.js والتنقّل |
| 3 | الغلاف والأخطاء والعميل |
| 4 | الدخول |
| 5 | مرجع المسارات الحية |
| 6 | رحلة أرضية (استلام → التقاط → تغليف → عهدة) |
| 7 | ما ليس حيّاً / حدود العمق |
| 8 | خريطة الشاشات |

---

## 0. قواعد الوكيل

1. **عميل HTTP واحد.** لا `fetch` من الصفحات مباشرة.
2. **كل استجابة غلاف** `{data, meta}` / `{error:{code,message,…}}`.
3. **`error.code` هو العقد.** الواجهة تعرض نصاً عربياً حسب `code`.
4. **كل كتابة تحمل `X-Idempotency-Key`** إلا `POST /warehouse/auth/device-login`.
5. **المال نادر هنا**؛ إن ظهر فـ `int` بأصغر وحدة (ل.س بدون `/100`).
6. **لا `X-Channel-Id`.** المستأجر من جهاز المستودع (`channel_id` / `warehouse_id` عند التسجيل).
7. **الشاشات تُغلق بـ `permissions[]` من device-login** — `can('wh.picking.execute')` يخفي الزر. لا تبنِ على اسم الدور وحده.
8. **معرّف أجنبي → 404.** لا تكشف الوجود بـ 403.
9. **توكن حارس آخر → `403 wrong_guard`.** امسح وأعد للدخول.
10. **هذه أداة أرضية** (ماسح، طابور، جرد) — ليست لوحة تقارير قناة. تقرير الإنتاجية موجود (`EP-WH-050`) لكنه شاشة واحدة لا بديل عن اللقطة اليومية للقناة.

---

## 1. البيئة والجهاز

### 1.1 المضيفون

| الهدف | الأساس |
|---|---|
| API محلي | `http://127.0.0.1:8000` |
| هذه اللوحة | المنفذ **3002** |

```ts
export const apiHost = process.env.NEXT_PUBLIC_API_HOST ?? 'http://127.0.0.1:8000';
export const baseUrl = `${apiHost}/api/v1`;
export const xClient = 'warehouse-web';
```

### 1.2 الترويسات

| الترويسة | متى |
|---|---|
| `Accept: application/json` | دائماً |
| `Authorization: Bearer <token>` | بعد الدخول |
| `X-Client: warehouse-web` | دائماً |
| `X-Device-Id` | UUID ثابت للجهاز |
| `X-Idempotency-Key` | كل كتابة إلا device-login |

### 1.3 تسجيل الجهاز (لا API عام)

الجهاز يُسجَّل مسبقاً من CLI على الخادم:

```bash
php artisan warehouse:register-device {plain_token} {4digit_pin} {warehouse_id} {channel_id} --label="محطة 1"
```

ثم الدخول:

```http
POST /api/v1/warehouse/auth/device-login
{ "device_token": "<plain_token>", "pin": "4821" }
```

→ `{ token, warehouse:{id,name}, permissions:["wh.queue.view", ...] }`

PIN = 4 أرقام. الجهاز غير المسجّل → 401.

---

## 2. هيكل Next.js

```
app/
  (auth)/login/page.tsx          # device_token + PIN
  (floor)/layout.tsx             # شريط علوي: طوابير + صلاحيات
  (floor)/page.tsx               # GET /warehouse/queues
  (floor)/pick/[id]/page.tsx
  (floor)/pack/[id]/page.tsx
  (floor)/handover/page.tsx
  (floor)/receiving/page.tsx
  (floor)/stocktake/...
  (floor)/returns/[id]/page.tsx
  (floor)/lots/page.tsx
  (floor)/sync/page.tsx          # offline push/status (v1.1 حيّ)
  (floor)/reports/productivity/page.tsx
lib/api/client.ts
```

التوكن في `sessionStorage` مفتاح **`warehouse`** — لا تخلطه مع `channel` / `platform`.

---

## 3. الغلاف والأخطاء

نفس عقد المنصة/القناة:

| HTTP | `error.code` شائع |
|---|---|
| 401 | `unauthenticated`, `token_revoked` |
| 403 | `wrong_guard`, `insufficient_permission` (+ `permission`) |
| 404 | `not_found` |
| 409 | `illegal_transition`, `operation_in_progress`, `idempotency_key_conflict` |
| 422 | `validation_failed`, `barcode_not_in_order` |

---

## 4. الدخول

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-001 | `POST` | `/warehouse/auth/device-login` | — (بلا حارس) |

لا يوجد logout API — امسح التوكن محلياً.

---

## 5. مرجع المسارات الحية (28)

المصدر: `docs/status/03-warehouse.md` — كل الصفوف ✅.

### طوابير والتقاط

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
| EP-WH-060 | GET | `/warehouse/sub-orders` | `wh.orders.view` |
| EP-WH-061 | POST | `/warehouse/sub-orders/{id}/confirm` | `wh.orders.confirm` |
| EP-WH-062 | POST | `/warehouse/sub-orders/assign` | `wh.orders.assign` |
| EP-WH-063 | POST | `/warehouse/stock-shortages` | `wh.picking.shortage` |

### تغليف · عهدة · استلام

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
| EP-WH-016 | POST | `/warehouse/packing/{id}/verify` | `wh.packing.execute` |
| EP-WH-017 | POST | `/warehouse/packing/{id}/complete` | `wh.packing.execute` |
| EP-WH-018 | GET | `/warehouse/handovers/pending` | `wh.handover.execute` |
| EP-WH-019 | POST | `/warehouse/handovers` | `wh.handover.execute` |
| EP-WH-020 | POST | `/warehouse/handovers/{id}/return-trip` | `wh.handover.return_trip` |
| EP-WH-021 | POST | `/warehouse/receiving` | `wh.receiving.execute` |
| EP-WH-022 | POST | `/warehouse/receiving/{id}/qc` | `wh.receiving.qc` |

### جرد · مرتجعات · lots · مزامنة · تقرير

| EP | طريقة | مسار | صلاحية |
|---|---|---|---|
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

الأجسام والاستجابات: انسخ من الكتالوج `06-warehouse.php` أو من اختبارات `tests/Feature/Fulfillment/`.

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

**مسح صنف:**

```http
POST /warehouse/picking-lists/11/scan
{ "barcode": "6291000000000", "qty": 1 }
```

باركود غير في الطلب → `422` / `barcode_not_in_order`.

**اعتماد جرد** يتطلب صلاحية حرجة + تأكيد مزدوج حيث ينطبق (`wh.stocktake.approve`).

---

## 6. رحلة أرضية مقترحة

1. `GET /queues` → بطاقات الأعداد.
2. اختر قائمة التقاط → `GET /picking-lists/{id}` (ترتيب الممر/الرف).
3. امسح أو أدخل يدوياً → `scan` / `manual`؛ نقص → `shortage`.
4. `complete` التقاط → شاشة تغليف `verify` ثم `complete`.
5. `GET /handovers/pending` → `POST /handovers` عند حضور المندوب.
6. وارد جديد: `receiving` ثم `qc`.
7. جرد دوري: `stocktakes` → lines → submit → جهاز/مستخدم ثانٍ `approve`.

---

## 7. ما ليس حيّاً / حدود

| الموضوع | الحقيقة |
|---|---|
| Offline كامل بأوضاع معقّدة | مزامنة `sync/*` حيّة؛ عمق إضافي في `channel-warehouse-v1.1.md` إن بقي |
| رفض/تلف متقدّم عند QC | راجع الكتالوج؛ لا تختلق حالات غير مذكورة |
| خريطة GPS للمندوبين | لوحة القناة (`/channel/reps/...`) — ليست هنا |
| تقارير قناة / GMV | لوحة القناة |

---

## 8. خريطة الشاشات

| # | الشاشة | يستدعي | فارغ |
|---|---|---|---|
| 1 | دخول الجهاز | device-login | — |
| 2 | الطوابير | queues | أصفار صادقة |
| 3 | التقاط | picking-lists* | «لا قائمة.» |
| 4 | تغليف | packing* | — |
| 5 | عهدة | handovers* | «لا عهد بانتظار.» |
| 6 | استلام وارد | receiving* | — |
| 7 | جرد | stocktakes* | يحتاج موافقة ثانية |
| 8 | فرز مرتجع | returns/{id}/sort | — |
| 9 | الدفعات (lots) | stock-lots* | — |
| 10 | مزامنة | sync/* | تعارضات صريحة |
| 11 | إنتاجية | reports/productivity | بعد بيانات |

---

*آخر تحديث: 2026-09-25 · الحالة: 28/28 حيّ.*
