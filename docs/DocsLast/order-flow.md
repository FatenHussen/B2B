# دورة الطلب — من التسجيل إلى نهاية اليوم

وثيقة تشغيل موحّدة لكل الأطراف: **مندوب · محل · قناة · مستودع**.  
مصدر القبول الميداني: وصف واتساب (2026-11-03) + تأكيد تيكمول.  
العقد الحي: `docs/api/catalog/` · الحالة: `docs/status/` · تذكرة الفجوات: [`../plan/order-flow-gaps.md`](../plan/order-flow-gaps.md).

القاعدة: المسار والحارس من الكتالوج؛ لا تختلق مسارات.

---

## 1. صورة الدورة

```text
تسجيل الطلب (مندوب أو محل)
        │
        ▼
   pending «معلق»
        │
   موافقة (قناة أو مستودع حسب الإعداد)
        │
        ▼
   confirmed «مؤكد» + حجز مخزون
        │
   إسناد مندوب (تلقائي إن كان طلب مندوب / يدوي إن كان طلب محل)
        │
        ▼
   تجهيز مستودع (مجمّع حسب المندوب / الأصناف)
        │
   نقص؟ → كمية كلية للصنف → توزيع FIFO → قرار المحل
        │
        ▼
   تسليم حمولة للمندوب → تأكيد رمز 4 خانات → on_the_way «في الطريق»
        │
        ▼
   تسليم للمحل (كامل / أقل / رفض) → تثبيت فاتورة → دفع للمندوب
        │
        ▼
   نهاية اليوم: عودة غير المسلَّم للمستودع → مطابقة الحمولة
```

### الحالات الأساسية (`sub_order.status`)

| قيمة | عربي تقريبي | ملاحظة |
|---|---|---|
| `pending` | معلق | بانتظار الموافقة |
| `confirmed` | مؤكد | مخزون محجوز |
| `assigned` / `accepted` | مسند / مقبول من المندوب | بعد الإسناد |
| `processing` | قيد التجهيز | بعد التقاط |
| `awaiting_handover` | بانتظار استلام العهدة | المستودع فتح الحوالة |
| `on_the_way` | في الطريق | الطرفان أكّدا العهدة |
| `delivered` / `undelivered` | مسلَّم / تعذّر | بعد التسليم أو الرفض |
| `cancelled` / `rejected` | ملغى / مرفوض | |

---

## 2. تسجيل الطلب — طريقتان

### 2.1 المندوب من تطبيقه

- يرى منتجات **قناته** فقط.
- يختار محل منطقة تغطيته → سلة → إرسال قسم المحل.
- المسار: `POST /api/v1/app/rep/cart/sections/{retailer_id}/submit` (EP-RP-023).
- النتيجة: `sub_order` بحالة `pending`، و`rep_id` = المندوب مرّة واحدة من الإرسال.
- الشاشة: «بانتظار الموافقة».

### 2.2 المحل من تطبيقه

- يطلب أصنافاً من قناة أو أكثر.
- السلة مقسومة `sections` حسب القناة؛ عند الإرسال ينقسم الطلب تلقائياً: كل قسم → `sub_order` لقناته.
- المسار: `POST /api/v1/app/retailer/cart/submit` (EP-RT-025).
- كل جزء يصل قناته بحالة `pending`.

### أين يظهر «معلق»؟

- عند **القناة** دائماً: `GET /api/v1/channel/sub-orders?filter[status]=pending` (EP-SC-060).
- عند **المستودع** أيضاً إن احتجتم طابور وارد: `GET /api/v1/warehouse/sub-orders` (EP-WH-060).
- التجهيز الفعلي في المستودع (قوائم التقاط) يبدأ **بعد** التأكيد.

---

## 3. الموافقة

### إعداد القناة — مَن يوافق؟

`PUT /api/v1/channel` (EP-SC-130B) داخل `settings`:

```json
{ "order_approver": "channel" }
```

| القيمة | المعنى |
|---|---|
| `channel` (افتراضي) | التأكيد من لوحة القناة فقط |
| `warehouse` | التأكيد من المستودع فقط — بشرط صلاحيات المستودع |

في **الحالتين** القناة ترى كل الطلبات الواردة.

### مسارات التأكيد

| الطرف | المسار | صلاحية |
|---|---|---|
| قناة | `POST /channel/sub-orders/{id}/confirm` | `sc.orders.confirm` |
| قناة جماعي | `POST /channel/sub-orders/bulk-confirm` | `sc.orders.confirm` |
| مستودع | `POST /warehouse/sub-orders/{id}/confirm` | `wh.orders.confirm` |

- تأكيد من الجهة غير المعيَّنة في الإعداد → `409 illegal_transition`.
- بعد النجاح: الحالة `confirmed` (أو `assigned` فوراً إن كان طلب مندوب — انظر §4) + **حجز كميات** من المخزون + إنشاء قائمة التقاط.

رفض القناة: `POST /channel/sub-orders/{id}/reject` مع `reason`.

---

## 4. إسناد المندوب

| مصدر الطلب | السلوك |
|---|---|
| مندوب (`source=rep_app`) | بعد التأكيد يُسند **تلقائياً** لنفس المندوب الذي أرسله (حتى لو off-duty) |
| محل / تاجر (`source=retailer_app`) | القناة **أو** المستودع يسندان لمندوب من مندوبي القناة في **منطقة المحل** |

### مسارات الإسناد

| الطرف | المسار | صلاحية |
|---|---|---|
| قناة | `POST /channel/sub-orders/assign` | `sc.orders.assign` |
| مستودع | `POST /warehouse/sub-orders/assign` | `wh.orders.assign` |

الجسم (نفس العقد):

```json
{
  "sub_order_ids": [9001],
  "rep_id": 70,
  "mode": "manual"
}
```

`mode`: `manual` | `auto` | `bulk_zone`  
- `auto`: أول مندوب on-duty يغطي منطقة الطلب.  
- بلا مندوب مناسب → `422`.

المندوب يقبل/يرفض الإسناد من تطبيقه:  
`GET /app/rep/assignments` · `POST …/accept` · `POST …/reject`.

---

## 5. التجهيز في المستودع

### ماذا يرى المستودع؟

- طوابير: `GET /warehouse/queues`
- قوائم التقاط / موجات مجمّعة حسب الأصناف عبر عدة طلبات:  
  `picking-lists` · `picking-waves` (EP-WH-011…015، EP-WH-042/042A)
- الحمولة تُجهَّز **مجمّعة بالأصناف**؛ المندوب لاحقاً يفرز حسب المحلات عند التسليم.

### نقص صنف — توزيع النظام (ليس يدوياً لكل طلب)

المستودع يدخل **الكمية الكلية المتوفرة للصنف مرة واحدة**:

`POST /api/v1/warehouse/stock-shortages` (EP-WH-063) · صلاحية `wh.picking.shortage`

```json
{
  "product_id": 880,
  "variant_id": null,
  "qty_available": 12,
  "reason": "out_of_stock"
}
```

`reason`: `out_of_stock` | `damaged` | `not_in_location`

**النظام** يوزّع على الطلبات المفتوحة للتجهيز (`confirmed|assigned|accepted|processing`) حسب **الأسبق** (`created_at` تصاعدياً)، ويعدّل الكميات والحجز.

مثال: طلبان يحتاجان 10 و 10 والمتوفر 12 → الأول 10، الثاني 2.

> `POST /warehouse/picking-lists/{id}/shortage` (EP-WH-014) يبقى لنقص **سطر قائمة التقاط واحدة** (توافق خلفي). مصدر الحقيقة لـ«كمية كلية للصنف» هو EP-WH-063.

### قرار المحل على النقص

الطلبات التي نُقصَت كميتها تنتظر قرار المحل. إكمال الالتقاط على طلب منتظِر → `409 operation_in_progress`.

| مسار التاجر | الغرض |
|---|---|
| `GET /app/retailer/orders/{id}/shortage` (EP-RT-035) | عرض الكمية قبل/بعد |
| `POST /app/retailer/orders/{id}/shortage-decision` (EP-RT-036) | القرار |

```json
{ "decision": "accept_partial" }
```

أو:

```json
{ "decision": "cancel" }
```

| القرار | النتيجة |
|---|---|
| `accept_partial` | يكمل بالكمية المخصَّصة؛ تتعدّل الفاتورة على ذلك |
| `cancel` | يلغي الطلب الفرعي كاملاً ويحرّر الحجوزات |

`{id}` = `sub_order_id`.

---

## 6. تسليم الحمولة للمندوب

1. المستودع يسلّم حمولته كاملة دفعة واحدة:  
   `POST /warehouse/handovers` (EP-WH-019) — `rep_id` + `sub_order_ids[]` → رمز `temp_code` من 4 خانات.
2. المندوب يؤكد بكبسة واحدة + الرمز:  
   `POST /app/rep/warehouse-receipts/{handoverId}/confirm` (EP-RP-041).
3. بعد تأكيد الطرفين: كل طلبات الحمولة → `on_the_way`، ويُخصم المخزون (REQ-IN-02).

قائمة العهدة عند المندوب: `GET /app/rep/warehouse-receipts`.

---

## 7. التسليم للمحل

المندوب يسلّم كل محل طلبه. للمحل عملياً ثلاث نتائج عبر تطبيق المندوب:

| خيار المحل | ماذا يفعل المندوب | المسار |
|---|---|---|
| يستلم كامل | `action=accept` على البنود ثم إنهاء | `POST /app/rep/deliveries/{id}/complete` |
| يستلم أقل | تعديل كمية ثم إنهاء؛ الفاتورة تتعدّل | `PATCH …/lines/{lineId}` ثم `complete` |
| يرفض مع سبب | تعذّر التسليم | `POST …/fail` مع `reason` |

- بعد الاستلام الناجح تُثبَّت الفاتورة؛ الرد قد يحمل `ask_payment: true` لفتح التحصيل من المندوب مباشرة.
- تحصيل لاحق / ذمم: `POST /app/receipts/reserve` ثم `POST /app/rep/payments` حسب عقد المندوب.

المحل يرى استلامه أيضاً عبر `GET/PATCH/POST /app/retailer/receipts/…` (EP-RT-040…042).

---

## 8. نهاية اليوم

المندوب يرجع الأصناف غير المسلَّمة للمستودع. المستودع يطابق مع الحمولة الخارجة ويعدّل الكميات:

`POST /warehouse/handovers/{id}/return-trip` (EP-WH-020)

```json
{
  "undelivered": [
    { "sub_order_id": 9001, "reason": "رفض الاستلام" }
  ]
}
```

يعيد تخزين غير المسلَّم (`restocked[]`).

---

## 9. خريطة المسارات حسب الطرف

### تطبيق المندوب (`auth:app` + kind `rep`)

| خطوة | EP | مسار |
|---|---|---|
| إرسال طلب | EP-RP-023 | `POST /app/rep/cart/sections/{retailer_id}/submit` |
| إسنادات واردة | EP-RP-030…032 | `/app/rep/assignments` |
| استلام عهدة | EP-RP-040/041 | `/app/rep/warehouse-receipts…` |
| تسليم | EP-RP-050…055 | `/app/rep/deliveries…` |
| تحصيل | EP-CM-050 + مدفوعات المندوب | `/app/receipts/reserve` · payments |

التفاصيل الشاشات: [`flutter-rep.md`](flutter-rep.md) · الحقل: [`flutter-rep-field.md`](flutter-rep-field.md).

### تطبيق التاجر (`auth:app` + kind `retailer`)

| خطوة | EP | مسار |
|---|---|---|
| إرسال سلة (انقسام قنوات) | EP-RT-025 | `POST /app/retailer/cart/submit` |
| طلباتي | EP-RT-030…034 | `/app/retailer/orders…` |
| نقص تجهيز | EP-RT-035/036 | `…/shortage` · `…/shortage-decision` |
| استلام | EP-RT-040…042 | `/app/retailer/receipts…` |

التفاصيل: [`flutter-retailer.md`](flutter-retailer.md).

### لوحة القناة (`auth:channel`)

| خطوة | EP | مسار |
|---|---|---|
| إعداد الموافق | EP-SC-130A/B | `GET/PUT /channel` → `settings.order_approver` |
| طابور وارد | EP-SC-060 | `GET /channel/sub-orders` |
| تأكيد / رفض / بنود | EP-SC-062…065 | `…/confirm` · `reject` · `lines` |
| إسناد | EP-SC-066/067 | `…/assign` · `reassign` |

التفاصيل: [`channel.md`](channel.md).

### لوحة المستودع (`auth:warehouse`)

| خطوة | EP | مسار | صلاحية |
|---|---|---|---|
| طابور طلبات | EP-WH-060 | `GET /warehouse/sub-orders` | `wh.orders.view` |
| تأكيد (إن مُنح) | EP-WH-061 | `POST …/sub-orders/{id}/confirm` | `wh.orders.confirm` |
| إسناد | EP-WH-062 | `POST …/sub-orders/assign` | `wh.orders.assign` |
| نقص كلي للصنف | EP-WH-063 | `POST /warehouse/stock-shortages` | `wh.picking.shortage` |
| التقاط / تغليف | EP-WH-011…017 | picking · packing | `wh.picking.*` / `wh.packing.*` |
| عهدة | EP-WH-018/019 | handovers | `wh.handover.execute` |
| عودة يوم | EP-WH-020 | `…/return-trip` | `wh.handover.return_trip` |

التفاصيل: [`warehouse.md`](warehouse.md).

---

## 10. صلاحيات جديدة (كتالوج — ليست في DOC-08)

| رمز | اسم عربي | مسارات |
|---|---|---|
| `wh.orders.view` | عرض الطلبات الواردة للمستودع | EP-WH-060 |
| `wh.orders.confirm` | تأكيد طلب من المستودع | EP-WH-061 |
| `wh.orders.assign` | إسناد من المستودع | EP-WH-062 |

تُبذر مع دور `warehouse_keeper`. تُذكر في `PermissionCatalog` مع اسم الـ EP.

---

## 11. أخطاء شائعة

| رمز HTTP | `error.code` | متى |
|---|---|---|
| 409 | `illegal_transition` | تأكيد من جهة غير المعيَّنة؛ انتقال حالة غير مسموح |
| 409 | `operation_in_progress` | إكمال التقاط وقرار نقص المحل ما زال معلّقاً |
| 409 | `insufficient_stock` | فشل الحجز عند التأكيد |
| 422 | `validation_failed` | بلا مندوب on-duty للإسناد؛ جسم نقص ناقص |
| 423 | `credit_limit_exceeded` / plan | سقف ائتمان عند التأكيد |
| 404 | `not_found` | طلب خارج القناة/المحل؛ لا نقص معلّق عند التاجر |

---

## 12. اختبار القبول

الملف: `tests/Feature/Ordering/OrderFlowGapsTest.php`

1. `order_approver=channel` → قناة تنجح · مستودع 409  
2. `order_approver=warehouse` → مستودع ينجح + إسناد تلقائي لطلب مندوب  
3. نقص 12 على طلبَي 10+10 → FIFO 10 ثم 2 · قبول جزئي · ثم يُسمح بإكمال التقاط  
4. رفض المحل للنقص → إلغاء الطلب الفرعي  
5. إسناد مستودع لطلب محل مؤكَّد  

---

## 13. ما بقي مطابقاً من قبل (لم يُبنَ من جديد)

تقسيم سلة المحل · حجز عند التأكيد · موجات التقاط · عهدة برمز 4 خانات · `on_the_way` · تسليم كامل/أقل/رفض · تحصيل · `return-trip`.

تاريخ العقد التشغيلي لهذه الوثيقة: **2026-11-03**.
