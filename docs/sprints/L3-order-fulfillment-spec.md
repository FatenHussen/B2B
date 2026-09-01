# طبقة 3 — توصيف بناء الباك (SP-09 … SP-12)

عقد تنفيذ. يُقرأ بعد L1 وL2. قواعد الغلاف، الحراس، المال الصحيح، Idempotency، Action/Query، Deptrac، و**نمط Form Request / lang في L1 §0.1** سارية. المصدر الملزم للأشكال: `docs/api/catalog/*.php`. كل كتابة جديدة = `ApiFormRequest`؛ أخطاء الحقول من `lang/` أو `InvalidFields`، لا نص خام.

**النطاق:** الحلقة التشغيلية: مخزون → سلة → طلب → تأكيد → مستودع → عهدة → تسليم → استلام → مرتجع.  
**خارج النطاق:** فواتير/دفعات/محفظة كاملة (SP-13)، إشعارات قنوات (SP-14)، ولاء (SP-15). عند اكتمال التسليم يُنشأ **رأس فاتورة أدنى** عبر عقد حتى تعمل أرقام `INV-*` في الردود.

**الموديولات:** `inventory` (Domain) · `ordering` `fulfillment` `delivery` `returns` (Coordination).  
`finance`: جداول فاتورة أدنى + عقد `IssuesInvoice` فقط.  
`identity`: `PATCH /app/rep/status` + محلات المنطقة.  
`notification`: لا يُبنى؛ أحداث المجال تُطلق وتُتجاهل حتى SP-14.

**Deptrac:** Coordination → Domain + Foundation. Coordination **لا** يستورد Coordination. ممنوع `use Modules\Ordering\Domain\Models` من `fulfillment`. العبور: عقود `core` + أحداث.

**شرط الدخول:** طبقة 2 مغلقة (`PricingEngine` مصدر السعر، كتالوج، عروض). `AvailabilityClassifier` يُستبدل بتنفيذ `inventory`.

---

## 0. عقود وأحداث

| عقد في `Modules\Core\Contracts` | ينفّذه | يستهلكه |
|---|---|---|
| `StockLedger` | `inventory` | `ordering` (حجز/تحرير عند تأكيد/إلغاء)، `fulfillment` (وارد، جرد، نقص التقاط)، `delivery`/`returns` (ارتجاع للكمية) |
| `AvailabilityClassifier` | `inventory` (يستبدل تنفيذ L2) | `catalog` تصفح: `in_stock\|low\|out_of_stock` **بلا رقم** للتاجر |
| `PricingEngine` + `OfferApplicator` | `pricing` / `promotion` | `ordering` كل طفرة سلة وsubmit |
| `CatalogProductLookup` | `catalog` | الكل |
| `CreditGuard` | `finance` أو `tenancy` (حد ائتمان تاجر×قناة؛ إن لم يُضبط → يسمح) | تأكيد طلب وsubmit إن لزم |
| `IssuesInvoice` | `finance` (رأس أدنى) | `delivery` عند اكتمال التسليم، `ordering` إن لزم |
| `CreatesPickingList` | `fulfillment` | يُستدعى من مستمع `SubOrderConfirmed` لا من Ordering مباشرة عبر النموذج |
| `RepDutyLookup` | `identity` | إسناد: مندوب `on_duty` ومناطقه (BR-19) |
| `RecordsAudit` | `core` | تسوية مخزون، اعتماد جرد، إلغاء، قرار مرتجع |

أحداث (`core` أو الموديول المصدر، بلا حمولة Eloquent — ids فقط):

`CartSubmitted` · `SubOrderConfirmed` · `SubOrderRejected` · `SubOrderCancelled` · `SubOrderAssigned` · `PickingShortageReported` · `HandoverOpened` · `HandoverConfirmedByRep` · `DeliveryCompleted` · `ReturnRequested` · `ReturnDecided`

مستمعون في الموديول المعني. معاملة واحدة حيث الكتالوج يشترط اتساق فوري (حجز مخزون + قائمة التقاط عند التأكيد؛ تعديل بند التسليم على الجهتين REQ-IN-05).

---

## 1. SP-09 — مخزون + سلة + تقديم (20 API)

### 1.1 ملكية المسارات

| مجموعة | موديول | عدد |
|---|---|---|
| `/channel/inventory/*` | `inventory` | 5 |
| سلة/طلبات التاجر | `ordering` | 11 |
| سلة المندوب + محلات منطقة | `ordering` + `identity` (محلات) | 4 |

### 1.2 مخزون — جداول `inventory`

كل صف تشغيلي: `BelongsToChannel`. المستودع المرجعي من `tenancy.warehouses` (موجود من L1 كحد أدنى — إن ناقص يُكمَّل هنا: `id, supply_channel_id, name, status`).

```
warehouse_locations     warehouse_id, aisle, shelf, code unique per warehouse
stock_balances          UNIQUE (warehouse_id, product_id, variant_id)
                        on_hand, reserved, in_transit, damaged  UNSIGNED (أو BIGINT)
                        available GENERATED أو محسوب: on_hand - reserved  (التالف منفصل لا يُباع)
                        لا float
stock_movements         append-only: type ∈ adjust|reserve|release|pick_deduct|receive|transfer_out|transfer_in|return_in|stocktake
                        qty_delta, qty_before, qty_after, reason, actor morph, ref_type, ref_id
                        BEFORE/AFTER على رصيد المستودع+الصنف. ممنوع UPDATE/DELETE (trigger مثل audit إن أمكن)
stock_reorder_points    (warehouse_id, product_id, variant_id, point)
stock_transfers         from_warehouse_id, to_warehouse_id, status ∈ sent|received, lines
stock_transfer_lines    product_id, variant_id, qty
stock_reservations      sub_order_id, warehouse_id, product_id, variant_id, qty, released_at
```

قواعد:

- التسوية EP-SC-051: `qty_delta` يغيّر `on_hand` (أو `damaged` حسب `reason` إن صُنّف). **dual + crit** عبر Access. دفتر حركة immutable.
- التحويل: `sent` ينقص `on_hand` المصدر ويزيد `in_transit` الهدف؛ `received` (لاحق في وارد المستودع أو endpoint ضمن التحويل) يقلّل `in_transit` ويزيد `on_hand`. في SP-09 الإنشاء يضع `sent` كما في الكتالوج.
- الحجز: **عند تأكيد الطلب (SP-10) لا عند submit.** السلة لا تحجز.
- تصفح التاجر: `available==0` → `out_of_stock`؛ تحت نقطة إعادة الطلب → `low`؛ وإلا `in_stock`. **لا رقم.**
- لوحة القناة ترى الأرقام الأربعة.

وزن التغليف لاحقاً: غرامات صحيحة لا عمود float.

### 1.3 طلبات — جداول `ordering`

```
orders                  retailer_id, source ∈ retailer_app|rep_app, order_no, status ملخص, currency
                        client_created_at, offline_created bool
order_sections          order_id, channel_id, opaque_ref (ch_a1 قبل الكشف), note, scheduled_at
sub_orders              order_id, channel_id, retailer_id, zone_id, source,
                        sub_order_no, status, totals (subtotal, discount, total BIGINT),
                        rep_id nullable, scheduled_at, credit check snapshot
sub_order_lines         product_id, variant_id, qty, unit_price, discount, line_total,
                        applied_rule JSON, offer_id nullable
sub_order_events        stage, at, actor — timeline
carts                   owner morph (retailer|rep), unique active cart
cart_sections           cart_id, channel_id, opaque_ref, note, scheduled_at
cart_lines              section_id, product_id, variant_id, qty, source ∈ browse|shortage|favorite|reorder|offer
                        أسعار مخزّنة للعرض فقط؛ المصدر عند كل GET/طفرة = PricingEngine
```

تقسيم السلة (REQ-IN-08): الخادم يجمع الأسطر حسب `channel_id` للمنتج. للتاجر `opaque_ref` ثابت لنفس القناة داخل السلة (مثلاً hmac قصير) **ليس** اسم القناة (REQ-IN-06). بعد submit: قائمة الطلبات `supply_channel: null` حتى `confirmed`.

إرسال أوفلاين (`offline_created=true`): **يُقبل دائماً** ثم يُعاد التسعير؛ `repricing_diff` يُملأ إن تغيّر الإجمالي (REQ-CM-018). عرض منتهٍ → 409 `offer_no_longer_valid` إن الطلب **متصل**؛ أوفلاين: اقبل وانزع العرض مع فرق السعر.

حد ائتمان عند submit: إن `CreditGuard` يرفض → 423 `credit_limit_exceeded` (وقد يُعاد الفحص عند التأكيد).

مندوب `discount_percent` ≤ سقف SP-07 وإلا 403 `discount_cap_exceeded` (BR-12).

BR-08: حذف بند عرض من السلة يحذف كل بنود ذلك العرض. BR-20: تخفيض كمية يكسر شرط العرض → `removed_offers[]`.

إلغاء التاجر: فقط `pending` وإلا 409 (BR-06). يحرّر لا شيء إن لم يُحجز بعد.

إعادة الطلب: يبني سلة بأسعار اليوم عبر المحرك.

تتبع في SP-09: مراحل من `sub_order_events`. هوية المندوب وإحداثيات **فقط** عند `on_the_way` (BR-03) — في SP-09 غالباً null. `eta_minutes` null حتى SP-12.

### 1.4 مسارات المخزون — 5

| كود | مسار | إذن | سلوك |
|---|---|---|---|
| EP-SC-050 | `GET /channel/inventory/levels` | `sc.inventory.view` | أرصدة + فلتر مستودع/منتج |
| EP-SC-051 | `POST /channel/inventory/adjust` | `sc.inventory.adjust` | dual+crit، حركة دفتر |
| EP-SC-052 | `POST /channel/inventory/transfers` | `sc.inventory.transfer` | `sent` |
| EP-SC-053 | `GET /channel/inventory/movements` | view | دفتر before/after |
| EP-SC-054 | `PUT /channel/inventory/reorder-points` | `sc.inventory.reorder` | upsert نقط |

### 1.5 مسارات التاجر — 11

حارس `app` + تاجر. كل طفرة سلة تعيد السلة كاملة بعد المحرك.

| كود | مسار | سلوك |
|---|---|---|
| EP-RT-020 | `GET /app/retailer/cart` | أقسام بـ `supply_channel_ref` مجهول |
| EP-RT-021 | `POST .../lines` | إضافة + إعادة تسعير |
| EP-RT-022 | `PATCH .../lines/{id}` | كمية؛ `removed_offers` |
| EP-RT-023 | `DELETE .../lines/{id}` | BR-08 |
| EP-RT-024 | `PATCH .../sections/{ref}` | ملاحظة/جدولة |
| EP-RT-025 | `POST .../submit` | شق قنوات، `client_created_at`، أوفلاين. Idempotency إلزامي |
| EP-RT-030 | `GET .../orders` | فلتر حالة؛ `supply_channel` null قبل التأكيد |
| EP-RT-031 | `GET .../orders/{id}` | `rep` null إلا `on_the_way`؛ `invoice` null حتى SP-12 |
| EP-RT-032 | `POST .../cancel` | pending فقط |
| EP-RT-033 | `POST .../reorder` | سلة اليوم |
| EP-RT-034 | `GET .../tracking` | مراحل؛ مندوب عند on_the_way |

`{id}` في قائمة التاجر = **sub_order id** (الكتالوج يعرض SO-9001 كعنصر قائمة). وحّد: موارد التاجر تتعامل مع sub-order.

### 1.6 مندوب — 4

| كود | مسار | موديول | سلوك |
|---|---|---|---|
| EP-RP-020 | `GET /app/rep/zones/{id}/shops` | identity | محلات المنطقة ضمن تغطية المندوب |
| EP-RP-021 | `POST /app/rep/cart/lines` | ordering | سلة مجمّعة حسب `retailer_id`؛ سعر المحرك لمنطقة المحل |
| EP-RP-022 | `GET /app/rep/cart` | ordering | |
| EP-RP-023 | `POST .../sections/{retailer_id}/submit` | ordering | طلب فرعي واحد لقناة المندوب. خصم ≤ سقف |

### 1.7 اختبارات SP-09

- تسوية −3 تنقص `available` وتكتب حركة before/after؛ تعديل الحركة مستحيل.
- سلة تاجر بقناةين → submit ينتج `order` + 2 `sub_orders`.
- `opaque_ref` لا يساوي اسم القناة.
- أوفلاين مع سعر تغيّر → 200 و`repricing_diff` غير null.
- خصم مندوب فوق السقف → 403.
- إلغاء بعد confirmed → 409.
- تاجر لا يرى رقم `available` في الكتالوج (classifier).

---

## 2. SP-10 — آلة SubOrder + إسناد (15 API)

موديول `ordering` (+ `identity` للحالة). `fulfillment` يستمع للتأكيد.

### 2.1 آلة الحالات

حالات canonical (enum `SubOrderStatus`):

```
pending → confirmed | rejected | cancelled
confirmed → assigned | postponed | cancelled | (picking عبر حدث مستودع)
assigned → accepted | unassigned (رفض المندوب) | postponed | cancelled
accepted → (مستودع) … → awaiting_handover → on_the_way → delivered | undelivered | postponed
postponed → assigned | confirmed (حسب ما بقي من إسناد)
```

انتقال ممنوع → 409 `illegal_transition`.  
جدول انتقالات صريح في `SubOrderStateMachine` — لا `if` مبعثرة في Controllers.

| فعل | من | إلى | أثر جانبي |
|---|---|---|---|
| confirm | pending | confirmed | `CreditGuard` قد 423؛ `StockLedger.reserve`؛ حدث → قائمة التقاط؛ **كشف اسم القناة للتاجر** |
| bulk-confirm | حتى 50 id | جزئي: `confirmed[]` + `failed[]` | هدف أداء: 50 طلب < دقيقتين — معاملة لكل id لا تفشل المجموعة كاملة |
| reject | pending | rejected | تحرير لا شيء؛ حدث إشعار |
| edit_lines | pending أو confirmed قبل الالتقاط | يبقى/confirmed | إعادة تسعير المحرك، تعديل حجز، إشعار إلزامي |
| assign | confirmed (أو pending إن سمح المنتج — **لا**: بعد التأكيد فقط إلا جدولة) | assigned | 422 إن المندوب off-duty أو خارج المنطقة (BR-19). `mode`: manual\|auto\|bulk_zone |
| reassign | assigned/accepted **قبل** استلام العهدة | assigned | 409 بعد `HandoverConfirmedByRep` |
| schedule | confirmed/assigned/accepted | postponed | `scheduled_at` في تقويم المندوب |
| cancel (قناة) | قبل خصم العهدة | cancelled | تحرير الحجز crit |
| rep accept | assigned | accepted | البطاقة تنتقل للتسليم بعد العهدة؛ قبلها تبقى إسناداً مقبولاً |
| rep reject | assigned | unassigned (عود لـ confirmed بلا مندوب) | تنبيه قناة |

`allowed_actions` في التفصيل = من الآلة + صلاحيات المستخدم لا قائمة ثابتة.

قائمة الالتقاط تُنشأ عند التأكيد حتى قبل الإسناد (الكتالوج: confirm يرجع `picking_list_id`).

### 2.2 جداول إضافية

```
sub_order_assignments   sub_order_id, rep_id, status ∈ pending|accepted|rejected, reason
rep_duty_states         في identity: on_duty bool, tracking_enabled, updated_at
```

### 2.3 مسارات القناة — 10

| كود | مسار | إذن |
|---|---|---|
| EP-SC-060 | `GET /channel/sub-orders` | `sc.orders.view` — فلاتر status/zone/retailer/rep/source/`waiting_over_minutes` |
| EP-SC-061 | `GET /channel/sub-orders/{id}` | view + timeline + allowed_actions |
| EP-SC-062 | `POST .../confirm` | `sc.orders.confirm` |
| EP-SC-063 | `POST .../bulk-confirm` | confirm |
| EP-SC-064 | `POST .../reject` | `sc.orders.reject` — سبب إلزامي |
| EP-SC-065 | `PATCH .../lines` | `sc.orders.edit_lines` |
| EP-SC-066 | `POST .../assign` | `sc.orders.assign` |
| EP-SC-067 | `POST .../reassign` | `sc.orders.reassign` |
| EP-SC-068 | `POST .../schedule` | `sc.orders.schedule` |
| EP-SC-069 | `POST .../cancel` | `sc.orders.cancel` crit |

### 2.4 مسارات المندوب — 5

| كود | مسار | موديول | سلوك |
|---|---|---|---|
| EP-RP-030 | `GET /app/rep/assignments` | ordering | بانتظار القبول؛ **يظهر اسم القناة للمندوب** |
| EP-RP-031 | `POST .../accept` | ordering | |
| EP-RP-032 | `POST .../reject` | ordering | سبب؛ يعود Unassigned |
| EP-RP-033 | `GET /app/rep/scheduled-orders` | ordering | `?date=` |
| EP-RP-034 | `PATCH /app/rep/status` | identity | `on_duty`؛ `tracking_enabled` فقط أثناء الخدمة (REQ-CM-054) |

### 2.5 اختبارات SP-10

- تأكيد بدون رصيد كافٍ: إما 409 مخزون أو تأكيد جزئي عبر edit — **افتراضي: 409 `insufficient_stock`** إن الحجز يفشل (أضف الكود إن غاب عن الكتالوج؛ لا تسكت).
- تأكيد ينقص `available` ويزيد `reserved` ويُنشئ picking list.
- تأكيد يكشف `supply_channel` في GET طلبات التاجر.
- إسناد لمندوب off-duty → 422.
- إعادة إسناد بعد تأكيد العهدة → 409.
- رفض المندوب يعيد الطلب بلا `rep_id`.
- SOD: من يؤكد الطلب ليس من يسجّل دفعة (الدفعة SP-13 — بذر الصلاحيات موجود من SP-02).

---

## 3. SP-11 — مستودع + عهدة + وارد + جرد (19 API)

موديول `fulfillment` (+ `inventory` عبر `StockLedger`). حارس `warehouse` + tenant مستودع الجهاز.

### 3.1 جداول `fulfillment`

```
picking_lists           sub_order_id unique, warehouse_id, status ∈ to_pick|picking|to_pack|packed|cancelled
                        due_at, pick_path_version
picking_lines           product_id, variant_id, qty_required, qty_picked, location_id, barcode, manual bool
packing_jobs            picking_list_id, status, packages_count, weight_gram, flags JSON, mismatches JSON
packages                packing_job_id, package_no, qr_token
handovers               warehouse_id, rep_id, status ∈ awaiting_rep_confirm|confirmed|cancelled
                        temp_code (4 أرقام، TTL)، opened_at, confirmed_at
handover_items          handover_id, sub_order_id, package_ids
goods_receipts          warehouse_id, source ∈ purchase|transfer|field_return, reference_no, status ∈ pending_qc|posted
goods_receipt_lines     product_id, variant_id, qty_expected, qty_received, lot_no, expiry_date, location_id
stocktakes              warehouse_id, scope ∈ full|partial, status ∈ counting|pending_approval|posted, counted_by, approved_by
stocktake_lines         product_id, variant_id, location_id, counted_qty — **بدون book_qty في الـ Resource للعداد**
```

مسار الالتقاط: رتّب الأسطر حسب `warehouse_locations` (aisle, shelf).

نقص الالتقاط: `reason` ∈ `out_of_stock|damaged|not_in_location`. حدث فوري للمبيعات. لا يكمل الكمية المطلوبة.

إنهاء التقاط → `to_pack`؛ حالة التاجر `processing`.

تغليف: مطابقة باركود؛ `mismatches[]`. وزن يُقبل من العميل كرقم عشري في JSON **يُحوَّل فوراً لغرام صحيح**.

### 3.2 REQ-IN-02 العهدة (حرج)

```
EP-WH-019  ينشئ handover = awaiting_rep_confirm
           لا يخصم مخزوناً، لا on_the_way
EP-RP-041  confirm + temp_code
           عند وجود الاثنين فقط: StockLedger.pick_deduct (reserved → يخرج من المستودع)،
           الحالة on_the_way، tracking_enabled إن on_duty
```

كسر الترتيب (تأكيد مندوب بلا WH-019) → 409. تكرار التأكيد → idempotent.

عودة المندوب EP-WH-020: غير المسلَّم `restock` عبر الدفتر؛ `wallet_matched` في L3 **true ثابت أو null** حتى محفظة SP-13 — لا تختلق مطابقة نقدية.

وارد: QC `accept` يُرحِّل `StockLedger.receive`. `photos` media ids.

جرد: العداد **لا يرى** `on_hand`. الاعتماد: dual + crit + SOD-03 (المعتمد ≠ العداد) → حركات `stocktake` بعدد الفروقات.

### 3.3 مسارات المستودع — 17 + مندوب 2

| كود | مسار | إذن |
|---|---|---|
| EP-WH-010 | `GET /warehouse/queues` | `wh.queue.view` |
| EP-WH-011 | `GET /warehouse/picking-lists/{id}` | `wh.picking.execute` |
| EP-WH-012 | `POST .../scan` | execute — 422 `barcode_not_in_order` |
| EP-WH-013 | `POST .../lines/{lineId}/manual` | execute — `manual=true` |
| EP-WH-014 | `POST .../shortage` | `wh.picking.shortage` |
| EP-WH-015 | `POST .../complete` | execute → to_pack |
| EP-WH-016 | `POST /warehouse/packing/{id}/verify` | `wh.packing.execute` |
| EP-WH-017 | `POST .../complete` | ملصقات QR |
| EP-WH-018 | `GET /warehouse/handovers/pending` | `wh.handover.execute` |
| EP-WH-019 | `POST /warehouse/handovers` | execute |
| EP-WH-020 | `POST .../return-trip` | `wh.handover.return_trip` |
| EP-WH-021 | `POST /warehouse/receiving` | `wh.receiving.execute` |
| EP-WH-022 | `POST .../qc` | `wh.receiving.qc` |
| EP-WH-023A/B/C | stocktakes start/lines/submit | `wh.stocktake.execute` |
| EP-WH-024 | `POST .../approve` | `wh.stocktake.approve` dual |
| EP-RP-040 | `GET /app/rep/warehouse-receipts` | `rp.warehouse.receive` |
| EP-RP-041 | `POST .../{handoverId}/confirm` | receive + `temp_code` |

`packing/{id}` = id قائمة التقاط أو packing_job — وحّد في التنفيذ: نفس id الـ picking list بعد complete pick.

### 3.4 اختبارات SP-11

- مسح باركود ليس في القائمة → 422 `barcode_not_in_order`.
- WH-019 وحده يبقي `reserved` ولا `on_the_way`.
- WH-019 ثم RP-041: `reserved`↓ `on_hand`↓ (أو reserved يُصفَّر ويخرج من المستودع) و`on_the_way`.
- جرد: استجابة العداد بلا `book_qty` / `on_hand`.
- اعتماد الجرد بنفس المستخدم العداد → 403 SOD.
- صفوف الطوابير تطابق الحالات الفعلية.

---

## 4. SP-12 — تسليم واستلام ومرتجع (17 API)

موديولات: `delivery` · `returns` · رأس فاتورة `finance`. REQ-IN-05: **نفس شكل البنود** للمندوب والتاجر؛ تعديل بند في معاملة واحدة يحدّث الكمية المالية للجهتين.

### 4.1 تسليم — جداول `delivery`

```
deliveries              sub_order_id unique, rep_id, status ∈ accepted|in_progress|delivered|postponed|undelivered
delivery_lines          line ↔ sub_order_line, qty_expected, qty_delivered, action ∈ pending|accept|adjust|return|exchange
delivery_completions    signature media, delivered_at, invoice_id
rep_location_pings      rep_id, lat, lng, at, accuracy — append, معدل 1/30ث (REQ-CM-044)
rep_ratings             retailer_id, rep_id, sub_order_id, stars, note, tags JSON
```

`GET` تسليم المندوب يجمع `on_the_way` + `accepted` بعد العهدة. ألوان الحدود من الحالة (UI hint في JSON كما الكتالوج).

إنهاء التسليم:

1. طبّق `action` على كل بند.
2. `IssuesInvoice` برأس `INV-*` والمبلغ النهائي (بعد التعديلات).
3. حالة sub-order `delivered`.
4. دفع فعلي **ليس** هنا (SP-13) لكن الرد قد يتضمّن `receipt_no` إن حُجز في SP-13 — في L3: `receipt_no` null أو تخطَّ إن العقد لم يُنفَّذ؛ الكتالوج يظهر رقماً — **أضف `ReceiptNumberReserver` أدنى** (عداد قناة) حتى لا يُكسر العقد، بلا دفتر دفعات.

تأجيل: `postponed` + يظهر في scheduled-orders. تعذر: `undelivered` — الكمية تبقى مع المندوب حتى return-trip.

نبضات: مرفوضة إن `!on_duty`؛ دفعة `pings[]`؛ 429 فوق 1/30ث.

تقييم المندوب: بعد تسليم ذلك التاجر لذلك المندوب فقط.

### 4.2 استلام التاجر — نفس أسطر التسليم

لا جدول منفصل إن أمكن: `delivery_lines` هي المصدر (REQ-IN-05).  
`GET /app/retailer/receipts/{subOrderId}` يقرأ نفس الأسطر.  
`PATCH` تاجر أو مندوب يحدّث نفس الصف + إعادة حساب إجمالي الفاتورة.

تأكيد الاستلام يصدر/يثبّت الفاتورة إن لم يُكمل المندوب بعد — **الأول يثبّت، الثاني idempotent** (سباق تاجر/مندوب).

### 4.3 مرتجعات — جداول `returns`

```
return_requests         channel_id, sub_order_id, requester morph (retailer|rep),
                        type ∈ return|exchange, status ∈ pending|approved|rejected|sorted,
                        request_no
return_lines            line_id, qty, reason, photos JSON
return_decisions        decision ∈ approve|reject, reason, actor
return_sorts            warehouse: condition ∈ resalable|damaged|expired per line
```

قرار القناة (REQ-IN-04, BR-13):

- `approve` + `resalable` لاحقاً في الفرز → `StockLedger.return_in` على `on_hand`.
- تالف/منتهٍ → `damaged` لا يُباع.
- أثر مالي: ملاحظة ائتمان على الفاتورة عبر `IssuesInvoice`/`CreditNoteWriter` أدنى (مبلغ سالب أو credit_note_id). لا محفظة كاملة.

الفرز **قرار حالة الصنف للمستودع لا لمقدّم الطلب**.

إنشاء مرتجع تاجر/مندوب: `photos` media. حالة `pending` حتى EP-SC-071.

### 4.4 مسارات — 17

| كود | مسار | موديول |
|---|---|---|
| EP-RP-050…055 | قائمة/تفصيل/بند/إكمال/تأجيل/تعذر تسليم | delivery |
| EP-RP-056 | نبضات موقع | delivery |
| EP-RP-057 | مرتجع ميداني | returns |
| EP-RT-040…042 | استلام تاجر | delivery (نفس الأسطر) |
| EP-RT-043/044 | مرتجعات تاجر | returns |
| EP-RT-045 | تقييم مندوب | delivery |
| EP-SC-070/071 | قائمة/قرار مرتجع قناة | returns |
| EP-WH-030 | فرز مرتجع مستودع | returns + StockLedger |

### 4.5 اختبارات SP-12

- PATCH بند المندوب يغيّر `new_invoice_total` الذي يراه التاجر فوراً.
- تأكيد استلام مرتين → نفس `INV`.
- `on_the_way` يظهر اسم المندوب للتاجر؛ قبله `rep=null`.
- ping والمندوب off-duty → 422/403.
- مرتجع موافق + فرز resalable يزيد `on_hand`.
- فرز تالف لا يزيد المتاح للبيع.
- إكمال تسليم بلا عهدة مؤكدة → 409.

---

## 5. توجيه الطبقة

```
/api/v1/channel/inventory/*          inventory
/api/v1/channel/sub-orders*          ordering
/api/v1/channel/return-requests*     returns
/api/v1/app/retailer/cart|orders*    ordering
/api/v1/app/retailer/receipts*       delivery
/api/v1/app/retailer/return-requests*  returns
/api/v1/app/rep/cart*                ordering
/api/v1/app/rep/zones/{id}/shops     identity
/api/v1/app/rep/assignments|scheduled-orders   ordering
/api/v1/app/rep/status               identity
/api/v1/app/rep/warehouse-receipts*  fulfillment
/api/v1/app/rep/deliveries*|locations  delivery
/api/v1/app/rep/return-requests      returns
/api/v1/warehouse/*                  fulfillment  (returns/sort → returns)
```

وسيطات L1. كتابات: `X-Idempotency-Key`. تأكيد جماعي: مفتاح لكل طلب أو مفتاح واحد للجسم كاملاً — **مفتاح واحد للجسم** كما ADR-05.

---

## 6. ترتيب البناء داخل الطبقة

1. دفتر المخزون + أرصدة + تسوية/تحويل (بدون سلة).
2. سلة + submit + شق قنوات + قائمة طلبات تاجر (pending فقط).
3. آلة التأكيد/الرفض/الإلغاء + حجز مخزون + قائمة التقاط فارغة.
4. إسناد + on_duty + قبول/رفض المندوب.
5. التقاط/تغليف/عهدة الثنائية (اختبار REQ-IN-02 أحمر حتى يمر).
6. وارد + جرد + SOD.
7. تسليم/استلام متماثلان + فاتورة أدنى.
8. مرتجع + قرار + فرز.

لا تسليم قبل العهدة الثنائية. لا رقم مخزون في تطبيق التاجر.

---

## 7. ما يُمنع

- خصم مخزون عند إنشاء العهدة فقط (كسر REQ-IN-02).
- كشف القناة للتاجر قبل `confirmed`.
- كشف المندوب للتاجر قبل `on_the_way`.
- جدولان لبنود الاستلام/التسليم غير متزامنين.
- تحديث صف `stock_movements`.
- `float` للمال؛ وزن يُخزَّن غرامات.
- استيراد Eloquent بين ordering وfulfillment.
- محفظة/تحصيل كامل (SP-13) ما عدا رأس فاتورة وعداد وصل أدنى.
- إشعارات واتساب حقيقية — أحداث فقط.

---

## 8. تعريف «منتهٍ»

| سبرنت | يُغلق عند |
|---|---|
| SP-09 | 20 مساراً؛ شق قنوات؛ سلة مجهولة القناة؛ دفتر حركات |
| SP-10 | 15 مساراً؛ حجز عند التأكيد؛ 422 off-duty؛ كشف القناة بعد التأكيد |
| SP-11 | 19 مساراً؛ اختبار العهدة الثنائية؛ جرد بلا كشف الرصيد للعداد |
| SP-12 | 17 مساراً؛ بند واحد للجهتين؛ فاتورة عند الإكمال؛ فرز يخزّن الشرط |

اختبار تكاملي إلزامي (رحلة واحدة): تاجر يضيف للسلة → submit → قناة confirm → التقاط → تغليف → handover → تأكيد مندوب → تسليم → تأكيد تاجر. الأرصدة والحالات تطابق الجدول أعلاه في كل خطوة.

المرجع الشكلي: `05-channel-ops.php`, `06-warehouse.php`, `07-retailer.php`, `08-rep.php`.
