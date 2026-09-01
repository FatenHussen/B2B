# طبقة 2 — توصيف بناء الباك (SP-06 … SP-08)

عقد تنفيذ. يُقرأ بعد `docs/sprints/L1-foundation-backend-spec.md`. قواعد الغلاف، الحراس، المال الصحيح، Idempotency، Deptrac، شكل الـ Action/Query، و**نمط Form Request / lang / InvalidFields في L1 §0.1** سارية كما هي. المصدر الملزم للأشكال: `docs/api/catalog/*.php`. كل كتابة جديدة = `ApiFormRequest` في الموديول المعني؛ لا `$request->validate()` داخل Action أو Controller.

**النطاق:** أول قيمة للتاجر والمندوب: كتالوج قناة + تصفح + تسعير + عروض. **لا سلة، لا طلب، لا مخزون تشغيلي، لا بنرات/ولاء كاملين (SP-15).**

**الموديولات التي تُملأ:** `catalog` `pricing` `promotion`.  
مسارات زبائن/مناطق المندوب في SP-06 تُبنى في `identity` (ليست منتجات).  
`content` `loyalty` `inventory` `ordering` تبقى فارغة.

**شرط الدخول:** طبقة 1 مغلقة (حراس أربعة، قناة `active`، مراجع، `sale_units` / `root_categories` / `activity_types` / `currencies`).

---

## 0. قواعد طبقة Domain (إضافية على L1)

`catalog` و`pricing` و`promotion` كلها طبقة **Domain**. Deptrac: Domain → Foundation فقط. **ممنوع** `use Modules\Pricing\...` من Catalog والعكس.

| عقد في `Modules\Core\Contracts` | ينفّذه | يستهلكه |
|---|---|---|
| `ProductPricingWriter` | `pricing` | `catalog` عند إنشاء/تحديث منتج (جسم الكتالوج يتضمّن `pricing`) |
| `PricingEngine` | `pricing` | `catalog` (بطاقة السعر)، `promotion` (price_before/after)، لاحقاً `ordering` |
| `CatalogProductLookup` | `catalog` | `pricing`, `promotion` (المنتج موجود؟ نشط؟ وحدة بيع؟) |
| `OfferFeed` | `promotion` | `catalog` (سلايدر الرئيسية، `has_offer`) |
| `AvailabilityClassifier` | في L2: تنفيذ فارغ في `catalog` يرجع `in_stock` إن المنتج `active`؛ يستبدله `inventory` في SP-09 | تصفح التاجر/المندوب |
| `RetailerShoppingContext` | `identity` | تصفح: `zone_id`, `activity_type_id`, `retailer_id`, `channel_ids` المرئية |
| `RepSellingContext` | `identity` | كتالوج المندوب: قنواته + منطقته |
| `RecordsAudit` | `core` (موجود) | كل كتابة كتالوج/تسعير/عرض |

معاملة واحدة لـ `POST /channel/products`: `DB::transaction` داخل Action الكتالوج يستدعي `ProductPricingWriter` — قاعدة واحدة، بلا توزيع خدمات.

`BelongsToChannel` على كل صف كتالوج/تسعير/عرض. تصفح التاجر **لا** يستخدم الـ trait عبر طلب التاجر (لا `supply_channel_id` على التوكن). Query التصفح يفلتر بالمنطقة/النشاط/التوفر عبر SQL صريح + `Tenant::as($channelId)` أو `withoutScope()` للقنوات التي تغطي منطقة التاجر. **لا تُرجع `supply_channel` للتاجر** (REQ-IN-06) حتى وجود طلب مؤكَّد (غير موجود في L2 → الحقل غائب دائماً في بطاقة التاجر). مندوب: يُسمح بـ `channel{id,name}` (TB-RP-050).

`price.label` يركّبه الخادم فقط. العميل لا يرسل أسعاراً. كل مبلغ `BIGINT` / `Money`.

وسائط: جدول `media` (Spatie) الموجود. في API الحقل `media_id_*` = id صف media. لا تخزّن URL خام في المنتجات — Resource يولّد URL من القرص/MinIO.

---

## 1. SP-06 — الكتالوج (26 API)

### 1.1 ملكية المسارات

| مجموعة | موديول | عدد |
|---|---|---|
| علامات/فئات/منتجات/استيراد/تصدير القناة | `catalog` | 12 |
| تصفح تاجر + نواقص | `catalog` | 10 |
| منتجات المندوب | `catalog` | 1 |
| زبائن المندوب + طلب منطقة | `identity` | 3 |

### 1.2 جداول `catalog` (كلها فيها `supply_channel_id` إلا ما يُذكر)

```
brands
  name_ar, name_en, description, logo_media_id, banner_media_id,
  order, status ∈ active|disabled
brand_activity_types     (brand_id, activity_type_id)     — ids فقط من reference
brand_sliders            brand_id, name, source ∈ algorithm|manual, source_id nullable, count, order

categories               — فئات القناة تحت جذر المنصة
  name, parent_id nullable, root_category_id (جذر reference عندما parent مستوى 1),
  image_media_id, icon, order, status, level UNSIGNED 1..5
category_activity_types  (category_id, activity_type_id)

products
  name_ar, name_en, sku, brand_id, category_id, model_no, barcode,
  status ∈ draft|active|disabled, short_description, long_description,
  sale_unit_id, min_order_qty, order_multiple, weight_gram,
  tracked bool, reorder_point unsigned, allow_backorder bool,  -- سياسة مخزون لا كميات
  lead_time_days, priority, tags JSON
  UNIQUE (supply_channel_id, sku)
product_specs            product_id, key, value, order
product_media            product_id, media_id, role ∈ image|primary|video, order
product_unit_factors     product_id, from_unit_id, to_unit_id, factor
product_zones            product_id, zone_id
product_activity_types   product_id, activity_type_id
product_retailer_groups  product_id, group_id          -- المجموعات تُنشأ لاحقاً؛ الجدول جاهز فارغ
product_slider_tags      product_id, slider_key        -- new|best_selling|…

product_variant_axes     product_id, name, order
product_variant_axis_values  axis_id, value, order
product_variants
  product_id, sku, barcode, image_media_id, combination JSON (محور→قيمة),
  status, UNIQUE (product_id, sku)

retailer_product_favorites  retailer_id, product_id, timestamps  — بلا channel على الصف؛ المنتج يحدد القناة
retailer_shortages          retailer_id, product_id, note, timestamps
```

**لا جدول كميات مخزون.** `AvailabilityClassifier` في L2: `active` → `in_stock`، غير ذلك → `out_of_stock`. `low` يأتي من SP-09. **ممنوع إرجاع رقم مخزون** للتاجر (REQ-IN-06, BR-02, BR-14).

مستوى الفئة:

- المستوى 1 = `root_categories` في `reference` (لا تُنسخ إلى `categories`).
- `POST /channel/categories` بـ `parent_id` يشير إمّا لجذر منصة (يُمرَّر id الجذر؛ ميّز بنطاق: إن وُجد في `root_categories` فـ `level=2` و`root_category_id`، و`parent_id` على صف القناة null مع `root_category_id`) أو لفئة قناة.
- أبسط تنفيذ نظيف: فئات القناة دائماً صفوف في `categories`. عند الربط بجذر منصة: `parent_id` null و`root_category_id` و`level=2`. فئة تحت فئة قناة: `parent_id` + `level = parent.level+1`.
- `level > 5` → 422 `validation_failed` («422 at the 6th level»).
- الشجرة للوحة القناة: ادمج جذور reference كعقد مستوى 1 في الـ Resource (id الجذر، `direct_products`/`total_products` محسوبة). لا تخزّن جذر المنصة كنسخة قناة.

`sold_count` / `rating`: 0 حتى توجد طلبات/تقييمات.

حدود القناة `skus` من SP-04: عند إنشاء منتج إن `count >= limit` → 422 `channel_limit_exceeded`.

### 1.3 تسعير داخل إنشاء المنتج (جسر إلى SP-07)

جسم EP-SC-015 يتضمّن `pricing`. كتالوج لا يخزّن شرائح في جدول المنتجات. Action يستدعي:

```
ProductPricingWriter::replace(channelId, productId, PricingDraft{type, base_price, currency_id, tiers})
```

تنفيذ الكاتب في `pricing` على جداول §2.1 (`product_base_prices` + `product_qty_tiers`). إن نُفِّذ SP-06 قبل جداول التسعير: **أنشئ تلك الجداول في هجرة pricing ضمن SP-06** (محرك القوائم يبقى SP-07).

`price.label` للتصفح: إن وُجدت شرائح → «السعر حسب الكمية»؛ وإلا قيمة منسّقة. `value` = سعر الوحدة لكمية 1 عبر `PricingEngine::quoteLine` (حتى لو المحرك في SP-06 يدعم فقط `qty_tier ← base_price`).

### 1.4 مسارات القناة — 12

حارس `channel` + `tenant` + إذن الكتالوج.

| كود | مسار | إذن | سلوك |
|---|---|---|---|
| EP-SC-010A | `GET /channel/brands` | `sc.catalog.view` | قائمة + `filter[status]` |
| EP-SC-010B | `POST /channel/brands` | `sc.catalog.create` | علامة + نشاطات + سلايدر خوارزمي (مصدر `algorithm` لا يحسب مبيعاً في L2 — تخزين التعريف فقط) |
| EP-SC-011 | `GET /channel/categories/tree` | view | شجرة 5 مستويات + عدّادات منتجات |
| EP-SC-012 | `POST /channel/categories` | create | مستوى 6 → 422 |
| EP-SC-013 | `POST /channel/categories/reorder` | `sc.catalog.update` | `moves[]` id/parent_id/order داخل معاملة؛ ارفض دورة وlevel>5 |
| EP-SC-014 | `GET /channel/products` | view | بحث + فلتر brand/category/status/zone |
| EP-SC-015 | `POST /channel/products` | create | الجسم الكامل بما فيه pricing/media/availability. SKU مكرر في القناة → 422 |
| EP-SC-016 | `PUT /channel/products/{id}` | update | استبدال كامل للحقول القابلة للكتابة + إعادة `ProductPricingWriter` |
| EP-SC-017 | `POST .../variants/generate` | `sc.catalog.variants` | جداء ديكارتي للمحاور. لا تمسح تباينات يدوية لها طلبات (لا طلبات في L2 → يُسمح بإعادة التوليد مع إبقاء sku المطابق). سعر/مخزون التباين في الرد: سعر المحرك، `stock` **لا يُرجع للتاجر**؛ للوحة القناة يُسمح بعدد داخلي 0 حتى SP-09 |
| EP-SC-018 | `POST /channel/products/bulk` | update | `action` ∈ `activate\|disable\|delete_draft`. حد معقول (مثلاً 200 id) |
| EP-SC-019 | `POST /channel/catalog/import` | `sc.catalog.import` | multipart `type=products`, `dry_run`. Job إن ليس dry_run. أخطاء صف + `duplicates[]` |
| EP-SC-020 | `GET /channel/catalog/export` | view | Job على `exports` → `job_id` |

استيراد/تصدير: PhpSpreadsheet في `catalog/Infrastructure`. لا معالجة ملف ثقيل داخل الـ request إلا `dry_run` بحد صفوف (مثلاً 500).

### 1.5 تصفح التاجر — 10

حارس `app` + `kind=retailer` + ملف `active` أو `pending_review` (المنتظر يرى كتالوج منطقته إن المنتج يغطيها — لا تفرّق في L2 إلا إن وُجد قرار منتج؛ الافتراضي: **active فقط**).

فلتر إلزامي من `RetailerShoppingContext`: `activity_type_id`، `zone_id`، فئات التاجر المختارة عند التسجيل (`category_ids`). منتج بلا صف في `product_zones` = غير ظاهر في تلك المنطقة. منتج بلا `product_activity_types` = غير ظاهر لذلك النشاط.

| كود | مسار | سلوك |
|---|---|---|
| EP-RT-010 | `GET /app/retailer/home` | **رحلة واحدة** (TB-RT-020). `header` من Identity (نقاط/مستوى = 0 حتى SP-15؛ إشعارات/سلة/ديون/طلبات = 0 حتى سبرنتاتها). `banner` / `offers_slider` فارغان حتى SP-08/15 ثم `OfferFeed`. `categories` = جذور ظاهرة. `brands_slider` من علامات القنوات التي تغطي المنطقة |
| EP-RT-011 | `GET /app/retailer/categories` | `parent_id` / `level` — أطفال المستوى التالي بعد فلتر النشاط |
| EP-RT-012 | `GET /app/retailer/products` | بطاقة `$productCard`. `barcode` يبحث تطابق. `filter[offer_only]` بعد SP-08. `availability` تعداد لا رقم |
| EP-RT-013 | `GET /app/retailer/products/{id}` | تفصيل + تباينات بأسعار المحرك. `sliders` فارغة أو منتجات نفس العلامة/الفئة بدون كشف القناة. 404 إن خارج الفلتر (لا 403 يكشف الوجود) |
| EP-RT-014 | `POST .../favorite` | تبديل. `{is_favorite}` |
| EP-RT-015A | `GET /app/retailer/brands` | علامات ظاهرة بعد الفلتر |
| EP-RT-015B | `GET /app/retailer/brands/{id}` | صفحة علامة؛ سلايدر `best_selling` فارغ حتى توجد مبيعات |
| EP-RT-070A/B/C | نواقص | قائمة/إضافة/حذف. `product_id` يجب أن يكون مرئياً للتاجر |

### 1.6 مندوب — 1 + 3 في identity

| كود | مسار | موديول | سلوك |
|---|---|---|---|
| EP-RP-010 | `GET /app/rep/products` | catalog | اتحاد منتجات **قنوات المندوب**، كل SKU يحمل `channel`. فلتر `channel_id` اختياري. سعر عبر المحرك بسياق أول تاجر؟ لا — سعر منطقة المندوب الافتراضية أو `zone` من الاستعلام إن وُجد؛ وإلا `base_price` |
| EP-RP-070A | `GET /app/rep/customers` | identity | محلات أضافها المندوب + تجّار منطقته المعتمدين (إن وُجدوا) |
| EP-RP-070B | `POST /app/rep/customers` | identity | تسجيل محل أوفلاين. `client_op_id` فريد لكل مندوب → Idempotency أعمال (إضافةً للهيدر). `pending_sync` حتى يكتمل ملف تاجر OTP لاحقاً. هاتف سوري. لا تنشئ `app_users` مكتمل في L2 إن لم يوجد OTP — صف `rep_sourced_shops` بانتظار SP-01 مكتمل: إن طبقة 1 موجودة، أنشئ `retailer_profiles` بحالة `pending_review` مربوطاً بالمندوب |
| EP-RP-071 | `POST /app/rep/zones` | identity | طلب منطقة إضافية `pending_approval`. يجب أن تكون المنطقة داخل تغطية **قناة** المندوب (`ChannelDirectory`). لا تُفعَّل فوراً |

جداول identity لـ 070/071:

```
rep_sourced_shops   rep_id, shop_name, owner_name, phone, zone_id, activity_type_id,
                    lat, lng, client_op_id UNIQUE(rep_id, client_op_id), status, retailer_id nullable
rep_zone_requests   rep_id, zone_id, note, status ∈ pending_approval|approved|rejected
```

### 1.7 اختبارات إلزامية SP-06

- فئة مستوى 6 → 422.
- SKU مكرر داخل القناة → 422؛ نفس SKU في قناة أخرى → مسموح.
- تاجر في منطقة 12 لا يرى منتجاً مغطى لمنطقة 13 فقط.
- بطاقة تاجر لا تحتوي `supply_channel` / اسم شركة.
- مندوب يرى `channel` على المنتج.
- `POST` منتج يكتب سعراً عبر الكاتب؛ بطاقة التاجر `price.value` عدد صحيح.
- مفضلة/ناقص لتاجر أ لا يراها تاجر ب.
- `client_op_id` مكرر لنفس المندوب لا ينشئ محلّين.

---

## 2. SP-07 — محرك التسعير (8 API)

موديول: `pricing`. يكمل ما بدأ في SP-06 (سعر أساسي + شرائح).

### 2.1 جداول

```
product_base_prices     product_id UNIQUE, currency_id, type ∈ simple|tiered, base_price BIGINT
product_qty_tiers       product_id, from_qty, to_qty nullable (=∞), price BIGINT
                        CHECK from_qty >= 1؛ لا تداخل شرائح

price_lists
  name, type ∈ base|zone|group|retailer, status ∈ active|scheduled|expired|disabled,
  group_id nullable, retailer_id nullable,
  adjustment_mode ∈ percent|fixed, adjustment_value (percent: signed int basis points أو percent صحيح؟ الكتالوج value: -5 → خزن INT -5 معنى ٪)،
  effective_from, effective_to, reason
price_list_zones        price_list_id, zone_id
price_list_items        price_list_id, product_id, override_price BIGINT nullable
                        — إن null تُطبَّق adjustment على ناتج الطبقة الأدنى

price_list_schedules    price_list_id, effective_from, payload JSON (changes[]), job_id, applied_at
price_change_logs       product_id, actor_user_id, before BIGINT, after BIGINT, reason, at
                        append-only (مثل audit)

rep_commercial_limits   channel_id, rep_id UNIQUE(channel), max_discount_percent unsigned,
                        max_cash_hold BIGINT  -- يُستخدم عند submit في SP-09 → 403 discount_cap_exceeded
```

`type=base` على قائمة: نادر إن السعر على المنتج؛ إن وُجدت قائمة `base` فهي طبقة تحت الشرائح أو بديل؟ الكتالوج: الأسبقية

```
retailer_price  ←  group_list  ←  zone_list  ←  qty_tier  ←  base_price
```

المحرك `PricingEngine::quote(QuoteRequest): QuoteResult`:

1. لكل سطر: `product_id` + `variant_id` اختياري + `qty`.
2. `base_price` من `product_base_prices` (تباين بلا سعر خاص → سعر المنتج).
3. إن `type=tiered` اختر شريحة الكمية.
4. إن وُجدت قائمة `zone` تغطي `zone_id` وفيها المنتج أو adjustment عام — طبّق.
5. قائمة `group` إن التاجر في مجموعة (L2: غالباً لا مجموعات → تخطَّ).
6. قائمة `retailer` لـ `retailer_id` إن وُجد في السياق (مندوب يقتبس لتاجر معيّن لاحقاً؛ quote الحالي جسمه `zone_id` فقط — **لا retailer_id في EP-APP-030**. طبّق retailer list فقط إن الحارس تاجر فيُؤخذ id من التوكن).
7. العروض **ليست** في quote SP-07 (SP-08 يوسّع المحرك أو طبقة فوقه). في SP-07 `discount` على السطر = 0 إلا فرق الشريحة (الشريحة تخفّض `unit_price` لا حقل discount منفصل إلا إذا عرّفت الفرق عن base). الكتالوج: `unit_price` 11500 مع `applied_rule.type=qty_tier` و`discount: 0` — **الشريحة تغيّر سعر الوحدة، والخصم الظاهر 0**. نفّذ هكذا حرفياً.
8. لا `float`. الضرب: `unit_price * qty` كأعداد صحيحة.
9. عملة الرد: ISO من `currencies` (`SYP`).

قوائم منتهية الصلاحية لا تدخل المحرك. جدولة EP-SC-032: Job يطبّق `changes` في `effective_from` (Horizon). حتى الموعد القائمة `scheduled`.

سجل التغيير: كل `PUT .../pricing` و`bulk-update` يدرج `price_change_logs` + `RecordsAudit`.

سقف المندوب: تخزين فقط في SP-07. لا endpoint تطبيق يستخدمه قبل السلة.

### 2.2 مسارات — 8

| كود | مسار | إذن | سلوك |
|---|---|---|---|
| EP-SC-030A | `GET /channel/price-lists` | `sc.pricing.view` | `filter[type]` |
| EP-SC-030B | `POST /channel/price-lists` | `sc.pricing.update` | type إلزامي مع الأهداف المناسبة (zone_ids لـ zone، إلخ) |
| EP-SC-031 | `PUT /channel/products/{id}/pricing` | update | يستبدل شرائح المنتج + log |
| EP-SC-032 | `POST /channel/price-lists/{id}/schedule` | `sc.pricing.schedule` | Job |
| EP-SC-033 | `POST /channel/pricing/bulk-update` | update | `mode=percent\|fixed` على `base_price` لمجموعة منتجات + سبب |
| EP-SC-034 | `GET /channel/pricing/change-log` | view | فلاتر product/user/date |
| EP-SC-035 | `PUT /channel/reps/{id}/discount-cap` | `sc.reps.update` | المندوب يجب أن ينتمي للقناة (عقد identity). `max_cash_hold` integer money |
| EP-APP-030 | `POST /app/pricing/quote` | حارس `app` | تاجر أو مندوب. **الجسم بلا أسعار.** 404 صامت لمنتج غير مرئي. عملة + أسطر + `applied_rule` |

### 2.3 اختبارات إلزامية SP-07

- كمية 6 على شرائح 1–4 / 5–9 / 10+ → `unit_price=11500` و`applied_rule.type=qty_tier`.
- قائمة منطقة ٪ −5 فوق الشريحة: طبّق بعد الشريحة (وثّق في اختبار الرقم المتوقع بـ integers فقط).
- تاجر المنطقة 12 لا يحصل على قائمة المنطقة 13.
- Quote بمنتج قناة أخرى غير ظاهرة → السطر يُسقط أو 422 `product_not_available` (اختر 422 بتفاصيل per line؛ لا تسعّر سراً).
- `bulk-update` percent يكتب log لكل منتج.
- عميل يرسل `unit_price` في الجسم → يُتجاهل (لا حقل في Request).

---

## 3. SP-08 — العروض (6 API)

موديول: `promotion`.

### 3.1 جداول

```
offers
  name, type ∈ product_discount|invoice_discount|buy_x_get_y|bundle|tiered_discount|gift,
  description, status ∈ draft|active|stopped|expired,
  stackable bool, priority int, starts_at, ends_at,
  total_qty nullable, per_retailer_max, per_order_max, min_invoice_value BIGINT, min_items,
  targeting_scope ∈ all|zones|groups|retailers
offer_media            offer_id, media_id, order
offer_components       offer_id, product_id, qty     — BR-08: حذف آخر مكوّن يلغي العرض بالكامل (status=stopped) لا يترك عرضاً فارغاً
offer_rewards          offer_id, product_id nullable, qty, discount_percent nullable, discount_amount BIGINT nullable
offer_rules            JSON أو أعمدة: buy_qty, get_qty حسب النوع
offer_activity_types   offer_id, activity_type_id
offer_zones            offer_id, zone_id
offer_groups           offer_id, group_id
offer_retailers        offer_id, retailer_id
offer_redemptions      — عدّادات: applied_count, qty_consumed؛ تفاصيل الطلبات تُملأ من SP-09. في L2 تبقى 0
```

أنواع مختصرة للتخزين:

| type | قواعد | مكافأة |
|---|---|---|
| `buy_x_get_y` | `buy_qty`, `get_qty` | `rewards.product_id` + qty |
| `product_discount` | — | ٪ أو مبلغ على المكوّنات |
| `invoice_discount` | `min_invoice_value` | ٪/مبلغ على السلة (يُطبَّق في SP-09) |
| `bundle` | مكوّنات متعددة | سعر حزمة في rewards |
| `tiered_discount` | شرائح JSON | — |
| `gift` | min_items / min_invoice | منتج هدية |

`stackable=false`: في السلة لاحقاً عرض واحد أعلى `priority`. في L2 التصفح يعرض الكل المؤهَّل؛ quote **بعد** SP-08: إن وُسِّع المحرك طبّق أعلى أولوية غير stackable. إن لم يُوسَّع quote في SP-08، وثّق أن `EP-APP-030` يبقى بلا عروض حتى السلة — والكتالوج يقول «Re-validate via quote before submit» على تفاصيل العرض. **وسّع `PricingEngine` أو أضف `OfferApplicator` يستدعيه quote** في SP-08 حتى التاجر لا يرى سعراً في العرض يختلف عن quote. `price_before` / `price_after` في قائمة العروض = محاكاة كمية المكوّنات عبر quote ثم طرح قيمة المكافأة (مثال: 10×12000 = 120000، قطعة مجانية 12000 → 108000).

`company` في قائمة التطبيق = `null` (REQ-IN-06).

إيقاف: `PATCH .../stop` → `stopped` + سبب + audit. لا حذف فيزيائي.

أداء EP-SC-042: أصفار في L2 (`applied_count=0`, …) — إطار JSON كما في الكتالوج لا أرقام وهمية.

### 3.2 مسارات — 6

| كود | مسار | إذن | سلوك |
|---|---|---|---|
| EP-SC-040A | `GET /channel/offers` | `sc.offers.view` | فلتر status/type |
| EP-SC-040B | `POST /channel/offers` | `sc.offers.create` | الجسم الكامل |
| EP-SC-041 | `PATCH /channel/offers/{id}/stop` | `sc.offers.stop` | سبب إلزامي |
| EP-SC-042 | `GET /channel/offers/{id}/performance` | view | أصفار صادقة في L2 |
| EP-APP-040 | `GET /app/offers` | `app` | فلتر نشاط/منطقة؛ إخفاء الشركة؛ `days_left` من `ends_at` بتوقيت دمشق |
| EP-APP-041 | `GET /app/offers/{id}` | `app` | 404 إن خارج الاستهداف. `same_company_*` بلا كشف اسم القناة (قائمة عروض/منتجات ids فقط أو أخفِ إن كشفت الهوية) |

`remaining_qty` = `total_qty - consumed` (consumed=0 في L2). `sold_count`=0.

### 3.3 اختبارات إلزامية SP-08

- عرض موجّه لمنطقة 12 لا يظهر لتاجر المنطقة 13.
- `company` دائماً null في `/app/offers`.
- إيقاف عرض يخرجه من `/app/offers` (`filter` الافتراضي النشط).
- إنشاء `buy_x_get_y` ثم حساب `price_after` يطابق 10×base − 1×base عندما لا شرائح.
- حذف/تفريغ آخر component عبر تحديث لاحق → العرض `stopped` (BR-08) إن وُجد مسار تحديث؛ إلى أن يوجد، وثّق أن التعديل = stop+create.

---

## 4. توجيه الطبقة

```
/api/v1/channel/brands|categories|products|catalog/*     catalog
/api/v1/channel/price-lists|pricing|products/{id}/pricing|reps/{id}/discount-cap   pricing
/api/v1/channel/offers*                                  promotion
/api/v1/app/retailer/home|categories|products|brands|shortages   catalog
/api/v1/app/rep/products                                 catalog
/api/v1/app/rep/customers|/zones                         identity
/api/v1/app/pricing/quote                                pricing
/api/v1/app/offers*                                      promotion
```

وسيطات: كما في L1 (`auth:channel`+`tenant` للوحة؛ `auth:app` للتطبيق). كتابات: `X-Idempotency-Key` إلزامي.

Composer: تبقى الحزم معتمدة على `b2b/core` فقط. الربط في ServiceProviders.

---

## 5. ترتيب البناء داخل الطبقة

1. جداول `catalog` + CRUD القناة (علامات، فئات، منتج بلا تصفح).
2. جداول السعر الأساسي + `ProductPricingWriter` + ربطها بإنشاء المنتج.
3. `AvailabilityClassifier` الفارغ + تصفح تاجر/مندوب (فلتر منطقة/نشاط، إخفاء القناة).
4. نواقص/مفضلة + زبائن المندوب في identity.
5. SP-07: قوائم + أسبقية + `POST /app/pricing/quote` — **بدّل بطاقة التصفح لتستدعي المحرك الكامل**.
6. SP-08: عروض + تغذية الرئيسية/has_offer + توسيع quote أو applicator.

لا تضع سعر البطاقة في Resource بـ `if` محلي بعد وجود المحرك.

---

## 6. ما يُمنع في هذه الطبقة

- جدول حركات مخزون / كميات متاحة حقيقية / سلة / `submit`.
- إرجاع رقم المخزون للتاجر أو اسم القناة في تطبيق التاجر أو في `/app/offers`.
- `float` للسعر أو للخصم المئوي المخزون كـ double — النسبة INT (مثلاً −5 معناها −5٪).
- استيراد Eloquent بين catalog/pricing/promotion.
- ملء `sold_count` و`performance` و`quick_actions.cart` بأرقام تجميلية.
- بناء موديول `content` لبنرات الرئيسية (placeholder فارغ حتى SP-15).
- تسعير على العميل (قبول `unit_price` من التطبيق).

---

## 7. تعريف «منتهٍ»

| سبرنت | يُغلق عند |
|---|---|
| SP-06 | 26 مساراً أخضر؛ تاجر ومندوب يريان منتجات حقيقية بعد فلتر؛ لا تسريب قناة للتاجر |
| SP-07 | 8 مسارات؛ quote مصدر السعر الوحيد للبطاقة والتسعير |
| SP-08 | 6 مسارات؛ عرض مستهدف يظهر لمن يطابق فقط؛ أداء بصفر صادق |

Pest: مجموعة `tenancy` — منتج قناة أ لا يظهر في لوحة قناة ب. مجموعة تصفح: منطقتان مختلفتان.

المرجع الشكلي: `docs/api/catalog/04-channel-catalog.php`, `07-retailer.php`, `08-rep.php`, `09-shared-app.php`. هذا الملف يحدد البناء والحدود لا أن يستبدل الكتالوج.
