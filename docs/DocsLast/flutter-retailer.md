# تطبيق التاجر — عقد API واحد لـ Flutter

| | |
|---|---|
| هذا الملف | العقد الوحيد الذي يبني عليه تطبيق التاجر. لا تعتمد على ملخص أقدم بجانبه |
| النطاق | كل مسار يستدعيه التطبيق: عام بلا حارس، مشترك `app`، ومسارات `/app/retailer/*` |
| الحارس | بعد الدخول: `Authorization: Bearer` · `auth:app` · `app.kind:retailer` |
| العميل | `X-Client: retailer-android` أو `retailer-ios` |
| الأساس | `/api/v1` |
| التاريخ | 2026-11-17 — 37 مسار تاجر حي + المشترك والعام الذي يخصه |
| الحالة الحية | `docs/status/04-retailer-app.md` · `06-shared-app.md` · `07-public.md` |
| الكتالوج | `docs/api/catalog/00-public.php` · `07-retailer.php` · `09-shared-app.php` |

عند التعارض: الكود الحي، ثم ملفات `status`، ثم الكتالوج، ثم هذا الملف.

---

## 0. قواعد العميل

1. عميل HTTP واحد. النجاح `{ "data", "meta" }`. الخطأ `{ "error": { "code", "message", "details" } }`. الفرع على `code`.
2. القوائم: `data` مصفوفة و`meta` فيه `page` `per_page` `total` `last_page`. قد يُضاف `status_counts` أو `unread_count`.
3. المال `int` بأصغر وحدة. ل.س بلا منازل: `12000` تُعرض `12,000 ل.س`. لا قسمة على 100 ولا `double` للمال.
4. الوقت `Asia/Damascus` كما يرجع الخادم.
5. كل كتابة (POST / PUT / PATCH / DELETE) تحمل `X-Idempotency-Key` جديداً لكل نقرة تأكيد. الاستثناء: OTP العام. إعادة نفس المفتاح تعيد نفس الجسم. `409 operation_in_progress` يعني أعد **نفس** المفتاح لا مفتاحاً جديداً.
6. ترويسات كل طلب بعد الدخول:

| ترويسة | قيمة |
|---|---|
| `Authorization` | `Bearer {token}` |
| `X-Client` | `retailer-android` \| `retailer-ios` |
| `X-Device-Id` | UUID ثابت في التخزين الآمن |
| `X-App-Version` | مثل `1.4.2` |
| `Accept` | `application/json` |

7. ممنوع `X-Channel-Id`. التاجر متعدد القنوات. العزل بالملكية لا بمستأجر واحد.
8. الهاتف قبل الإرسال: `0931000001` → `+963931000001`. النمط `^\+9639\d{8}$`.
9. OTP المحلي (خارج الإنتاج) النص `"000000"`. الرقم `0` يُرفض.
10. `user_type` أو `user.user_type` إن لم يكن `retailer` أوقف التطبيق. لا مبدّل أدوار ولا استدعاء `/app/rep/*`.
11. اسم القناة يُعرض فقط من: `home.channels_slider`، `company` على المنتج والعرض، `orders[].supply_channel` بعد خروج الطلب من `pending` و`rejected`، و`debts.by_channel` لأن الفاتورة قائمة. السلة تستخدم `supply_channel_ref` مبهماً ولا يُفك.
12. `availability` على المنتج: `in_stock` \| `low` \| `out_of_stock`. لا رقم مخزون. `remaining_qty` على العرض كمية الباقة المتبقية لا مخزون المستودع.
13. بعض أرقام الواجهة (تقييم، مبيعات، سعر قبل الخصم، شروط العرض، إحصاءات الرئيسية ما عدا عداد النواقص) قيمة ثابتة يملؤها الخادم إن لم تدخلها لوحة القناة في `storefront` / `display`. اعرضها كما وصلت. لا تخترع رقماً ثانياً على الجهاز.
14. `header.pending_sync` يبقى `0`. زر المزامنة يقرأ `GET /app/sync/status` لا الرئيسية.

### أخطاء مشتركة

| HTTP | `code` | سلوك Flutter |
|---|---|---|
| 401 | `unauthenticated` `token_revoked` `otp_invalid` | مسح التوكن والعودة للدخول، أو خطأ الرمز |
| 403 | `wrong_guard` | إغلاق: تطبيق خاطئ |
| 403 | `insufficient_permission` | إخفاء زر الكتابة. الحقل `permission` يسمّي الرمز |
| 403 | `profile_incomplete` | شاشة إكمال التسجيل |
| 404 | `not_found` | غير موجود لهذا التاجر. لا تميّز «لقناة أخرى» |
| 409 | `illegal_transition` | الحالة تغيّرت. أعد تحميل الشاشة |
| 409 | `operation_in_progress` | أعد نفس مفتاح التكرار |
| 409 | `idempotency_key_conflict` | المفتاح استُخدم لجسم مختلف. مفتاح جديد |
| 422 | `validation_failed` | `details` تحت الحقول |
| 423 | `credit_limit_exceeded` | حوار حد الائتمان |
| 426 | `upgrade_required` | شاشة تحديث إجباري |
| 429 | `rate_limited` | انتظار `resend_after` أو رسالة حد |
| 503 | `maintenance_mode` | شاشة صيانة |

`pending_review` بعد التسجيل: مسموح `GET /app/session` و`POST /app/auth/logout` فقط. أي مسار تشغيلي يرجع `403`.

### ما لا يستدعيه التاجر

| مسار | السبب |
|---|---|
| `/app/rep/*` | تطبيق المندوب |
| `/channel/*` `/platform/*` `/warehouse/*` | لوحات أخرى |
| `POST /app/receipts/reserve` | صلاحية `rp.payment.collect` — المندوب يحجز رقم الوصل. التاجر يسجّل الدفعة برقمه |

---

## 1. فهرس المسارات

الأساس `/api/v1`. عمود التكرار = يلزم `X-Idempotency-Key`.

### عام — بلا توكن

| EP | طريقة | مسار | تكرار |
|---|---|---|---|
| EP-PB-010 | GET | `/public/app-config` | |
| EP-PB-011 | GET | `/public/content/intro` | |
| EP-PB-001 | GET | `/public/refs` | |
| EP-CM-001 | POST | `/public/auth/request-otp` | |
| EP-CM-002 | POST | `/public/auth/verify-otp` | |
| EP-CM-003 | POST | `/public/auth/resend-otp` | |

### جلسة ومحتوى وولاء ومزامنة — توكن `app`

| EP | طريقة | مسار | تكرار | صلاحية |
|---|---|---|---|---|
| EP-CM-004 | GET | `/app/session` | | |
| EP-CM-005 | POST | `/app/auth/logout` | نعم | |
| EP-APP-030 | POST | `/app/pricing/quote` | نعم | |
| EP-APP-040 | GET | `/app/offers` | | |
| EP-APP-041 | GET | `/app/offers/{id}` | | |
| EP-APP-042 | POST | `/app/offers/{id}/favorite` | نعم | |
| EP-CM-060 | GET | `/app/notifications` | | |
| EP-CM-061 | POST | `/app/notifications/read-all` | نعم | |
| EP-CM-062 | DELETE | `/app/notifications` | نعم | |
| EP-CM-063 | POST | `/app/devices/push-token` | نعم | |
| EP-SY-001 | GET | `/app/sync/pull` | | |
| EP-SY-002 | POST | `/app/sync/push` | نعم | |
| EP-SY-003 | GET | `/app/sync/status` | | |
| EP-SY-004 | POST | `/app/sync/resolve-conflict` | نعم | |
| EP-APP-100 | GET | `/app/content/home-blocks` | | |
| EP-APP-101 | POST | `/app/content/banners/{id}/click` | نعم | |
| EP-APP-110 | GET | `/app/loyalty` | | |
| EP-APP-111 | POST | `/app/loyalty/redeem` | نعم | |

### التاجر — 37

| EP | طريقة | مسار | تكرار | صلاحية |
|---|---|---|---|---|
| EP-RT-001 | POST | `/app/retailer/register` | نعم | توكن تسجيل |
| EP-RT-010 | GET | `/app/retailer/home` | | |
| EP-RT-011 | GET | `/app/retailer/categories` | | |
| EP-RT-012 | GET | `/app/retailer/products` | | |
| EP-RT-013 | GET | `/app/retailer/products/{id}` | | |
| EP-RT-014 | POST | `/app/retailer/products/{id}/favorite` | نعم | |
| EP-RT-014B | GET | `/app/retailer/favorites` | | |
| EP-RT-015A | GET | `/app/retailer/brands` | | |
| EP-RT-015B | GET | `/app/retailer/brands/{id}` | | |
| EP-RT-016 | PUT | `/app/retailer/presence` | نعم | |
| EP-RT-070A | GET | `/app/retailer/shortages` | | |
| EP-RT-070B | POST | `/app/retailer/shortages` | نعم | |
| EP-RT-070C | DELETE | `/app/retailer/shortages/{id}` | نعم | |
| EP-RT-020 | GET | `/app/retailer/cart` | | |
| EP-RT-021 | POST | `/app/retailer/cart/lines` | نعم | |
| EP-RT-022 | PATCH | `/app/retailer/cart/lines/{id}` | نعم | |
| EP-RT-023 | DELETE | `/app/retailer/cart/lines/{id}` | نعم | |
| EP-RT-024 | PATCH | `/app/retailer/cart/sections/{ref}` | نعم | |
| EP-RT-025 | POST | `/app/retailer/cart/submit` | نعم | |
| EP-RT-030 | GET | `/app/retailer/orders` | | |
| EP-RT-031 | GET | `/app/retailer/orders/{id}` | | |
| EP-RT-032 | POST | `/app/retailer/orders/{id}/cancel` | نعم | |
| EP-RT-033 | POST | `/app/retailer/orders/{id}/reorder` | نعم | |
| EP-RT-034 | GET | `/app/retailer/orders/{id}/tracking` | | |
| EP-RT-035 | GET | `/app/retailer/orders/{id}/shortage` | | |
| EP-RT-036 | POST | `/app/retailer/orders/{id}/shortage-decision` | نعم | |
| EP-RT-040 | GET | `/app/retailer/receipts/{subOrderId}` | | `rt.receive.confirm` |
| EP-RT-041 | PATCH | `/app/retailer/receipts/{id}/lines/{lineId}` | نعم | `rt.receive.confirm` |
| EP-RT-042 | POST | `/app/retailer/receipts/{id}/confirm` | نعم | `rt.receive.confirm` |
| EP-RT-043 | POST | `/app/retailer/return-requests` | نعم | `rt.receive.return_request` |
| EP-RT-044 | GET | `/app/retailer/return-requests` | | |
| EP-RT-045 | POST | `/app/retailer/reps/{id}/rate` | نعم | |
| EP-RT-050 | POST | `/app/retailer/payments` | نعم | `rt.payment.record` |
| EP-RT-051 | GET | `/app/retailer/account/summary` | | |
| EP-RT-052 | GET | `/app/retailer/account/statement` | | `rt.account.statement` |
| EP-RT-053 | POST | `/app/retailer/account/statement/export` | نعم | `rt.account.statement` |
| EP-RT-054 | GET | `/app/retailer/debts` | | |

ترتيب الإقلاع: `app-config` → `intro` → `request-otp` → `verify-otp` → `session` → `register` إن `profile_completed` كاذبة أو `is_new_user` → `home`.

بذرة QA: `php artisan migrate:fresh --seed` ثم هاتف `0931000001` ورمز `"000000"`.

---

## 2. عام

### EP-PB-010 `GET /public/app-config`

استعلام: `app=retailer` · `platform=android|ios` · `version`.

`data`: `min_supported_version` · `latest_version` · `store_url` · `force_update` · `release_notes` · `feature_flags` · `maintenance{enabled,message_ar,until}`.

`426 upgrade_required` إن النسخة أقدم من `min_supported_version`. `503` أثناء الصيانة. استدعِه قبل أي شاشة.

### EP-PB-011 `GET /public/content/intro`

`data`: `enabled` · `text` · `media_type` · `media_id` · `duration` · `targeting`.

`enabled: false` يتجاوز الانترو. `media_id` معرّف وسائط لا رابط جاهز دائماً. لا تستدعِ مسارات انترو المنصة أو القناة.

### EP-PB-001 `GET /public/refs`

استعلام اختياري `since` (مؤشر مزامنة).

`data`: `governorates[]` · `zones[]{id,name,governorate_id}` · `activity_types[]` · `root_categories[]` · `sale_units[]` · `equipments[]` · `sync_cursor`.

خزّن محلياً لشاشات التسجيل. **لا قنوات توريد** في هذا الرد. قائمة المنطقة تُصفّى بـ `governorate_id` على الجهاز.

### EP-CM-001 `POST /public/auth/request-otp`

```json
{ "phone": "+963931000001", "purpose": "login", "client": "retailer-android" }
```

`purpose`: `login` | `register`.

`data`: `otp_id` · `channel_used` (`whatsapp`|`sms`) · `expires_in` · `resend_after` (ثوانٍ).

`422` هاتف غير سوري. `429` تجاوز 3/ساعة للهاتف. احفظ `otp_id`.

### EP-CM-002 `POST /public/auth/verify-otp`

```json
{
  "otp_id": "otp_9f2a71",
  "code": "000000",
  "device_id": "uuid",
  "device_name": "Redmi Note 13",
  "platform": "android"
}
```

`data`: `token` · `is_new_user` · `user_type` · `profile_completed` · `user{id,name,phone,shop_name,zone,activity_type,points,tier}`.

احفظ التوكن. `user_type != retailer` يوقف التدفق. `is_new_user` أو `profile_completed: false` يفتح التسجيل. `401 otp_invalid` حتى 5 محاولات ثم يُحرق الرمز.

### EP-CM-003 `POST /public/auth/resend-otp`

```json
{ "otp_id": "otp_9f2a71", "prefer_channel": "sms" }
```

`prefer_channel`: `whatsapp` | `sms`. الرد `{ channel_used, resend_after }` **بدون** `otp_id` جديد. أعد استخدام القديم. لا تستدعِ قبل انتهاء `resend_after`.

---

## 3. الجلسة والإشعارات والمزامنة

### EP-CM-004 `GET /app/session`

كل إقلاع بعد التوكن.

`data.user`: `id` · `name` · `user_type` · `profile_completed` · `avatar` (دائماً `null` حتى يتوفر رفع صورة).

أيضاً: `permissions[]` · `feature_flags` · `sync_cursor` · `server_time` · `requires_legal_accept` · `legal{privacy_version,terms_version}`.

`requires_legal_accept: true` يحجب الاستخدام حتى قبول النسخة. أخفِ أزرار الكتابة التي لا رمزها في `permissions`. التصفح لا يُخفى بفحص صلاحية.

### EP-CM-005 `POST /app/auth/logout`

جسم فارغ `{}`. `data.success`. امسح التوكن والسلة المحلية وصندوق المزامنة.

### EP-CM-060 `GET /app/notifications`

`filter[read]=0|1`. الصف: `id` · `icon` · `title` · `body` · `at` · `read_at` · `action{type,target}`.

`meta.unread_count` لشارة الجرس. `action.type = order` و`target` هو معرّف الطلب الفرعي لفتح التفاصيل.

### EP-CM-061 `POST /app/notifications/read-all`

جسم `{}`. `data.success`. صفّر الشارة.

### EP-CM-062 `DELETE /app/notifications`

يمسح عرض الصندوق على الجهاز لا سجل الخادم. `data.success`.

### EP-CM-063 `POST /app/devices/push-token`

```json
{ "token": "fcm:…", "platform": "android" }
```

بعد كل تجديد لرمز FCM/APNs. `data.success`.

### EP-SY-001 `GET /app/sync/pull`

`cursor` · `scopes[]` · `limit` (حتى 200).

نطاقات التاجر: `catalog` `pricing` `offers` `zones` `orders` `refs`.

`data`: `changes.{scope}.{upserts,deletes}` · `next_cursor` · `has_more` · `full_resync_required`.

إن `full_resync_required` امسح الكاش وأعد السحب بمؤشر فارغ. كرر ما دام `has_more`.

### EP-SY-002 `POST /app/sync/push`

```json
{
  "operations": [{
    "client_op_id": "op_local_88",
    "type": "cart.submit",
    "payload": {},
    "created_at": "2026-03-01T09:05:00+03:00"
  }]
}
```

حتى 500 عملية، 20 دفعة/دقيقة للجهاز. الأنواع المسموحة للتاجر تشمل الطلب والدفع والاستلام والنقص والمفضلة والتقييم. **ممنوع** دفع كتالوج أو أسعار أو مخزون.

`results[].status`: `applied` | `duplicate` | `conflict` | `failed`. لا تحذف العملية من الصندوق إلا بعد `applied` أو `duplicate`.

### EP-SY-003 `GET /app/sync/status`

`pending_server_side` · `last_pull_at` · `last_push_at` · `conflicts[]`. هذا مصدر زر «مزامنة يدوية» لا `home.header.pending_sync`.

### EP-SY-004 `POST /app/sync/resolve-conflict`

```json
{ "conflict_id": "cf_12", "resolution": "server_wins" }
```

`resolution`: `server_wins` | `client_wins` حسب ما يعيده التعارض. `data.success`.

### EP-APP-100 `GET /app/content/home-blocks`

بلوكات إضافية فوق `GET /app/retailer/home`: `banners[]{id,image,link{type,target}}` · `sliders[]{key,title,items,show_all}`.

عرض بانر يزيد المشاهدات على الخادم. النقرة مسار منفصل.

### EP-APP-101 `POST /app/content/banners/{id}/click`

جسم `{}`. `data.clicks`. `404` إن البانر غير ظاهر لهذا التاجر.

### EP-APP-110 `GET /app/loyalty`

`points` · `tier` · `next_tier{name,remaining}` · `history[]{at,delta,reason}` · `rewards[]{id,name,points_cost}`.

شارة الرئيسية `header.points` و`header.tier` تكفي للشريط. هذه الشاشة للتاريخ والاستبدال.

### EP-APP-111 `POST /app/loyalty/redeem`

```json
{ "reward_id": 3 }
```

`data.redemption_no`. `422` نقاط غير كافية أو مكافأة غير متاحة.

---

## 4. التسجيل والحضور

### EP-RT-001 `POST /app/retailer/register`

توكن القدرة `registration` بعد OTP لحساب غير مكتمل.

```json
{
  "owner_name": "أبو خالد",
  "shop_name": "بقالية النور",
  "activity_type_id": 3,
  "category_ids": [10, 11],
  "equipment_ids": [1],
  "governorate_id": 1,
  "zone_id": 12,
  "lat": 33.5031,
  "lng": 36.2765,
  "address": "المزة فيلات شرقية"
}
```

لا بريد ولا كلمة سر. `zone_id` تابع للمحافظة وإلا `422`.

`data.retailer`: `id` · `shop_name` · `zone` · `activity_type` · `status` (`pending_review` عادة). `data.token` يستبدل توكن التسجيل.

### EP-RT-016 `PUT /app/retailer/presence`

```json
{ "is_online": true }
```

`data.is_online`. هذا مفتاح «متصل» على الرئيسية (رغبة المحل) لا حالة شبكة الجهاز. بعد النجاح حدّث المفتاح من الرد.

---

## 5. الرئيسية والكتالوج

### EP-RT-010 `GET /app/retailer/home`

نداء واحد للشاشة. لا تجمعها من خمسة طلبات.

`header`:

| حقل | واجهة |
|---|---|
| `owner_name` | أهلاً بك |
| `shop_name` | اسم المحل |
| `zone.id` `zone.name` | المنطقة |
| `points` `tier` | الشارة |
| `unread_notifications` | الجرس. الصفر يخفي الرقم |
| `pending_sync` | تجاهله. استخدم المزامنة |
| `is_online` | المفتاح |

`banner`: `{id,image}` أو `null`.

`quick_actions[]`: `{key,count}`. المفاتيح: `order_products` · `reorder` · `shortages` · `payments`. عداد النواقص حقيقي. البقية قد تكون أرقام واجهة.

`stats`: `active_orders` · `total_debt` · `delivering_today`. أرقام واجهة حتى تُربط بالمالية. اعرضها.

`categories[]`: `{id,name,image}` جذور مناسبة لنشاط التاجر.

`offers_slider[]` شكل بطاقة العرض (§7). `brands_slider[]`: `{id,name,logo}`. `dynamic_sliders[]` قد يفرغ.

`channels_slider[]`:

| حقل | واجهة |
|---|---|
| `id` `name` | البطاقة |
| `logo` | شعار أو عنصر نائب |
| `description` | سطر الوصف |
| `is_verified` | شارة موثّق |
| `rating` | منزلة واحدة |
| `brand_count` | عدد البراندات |
| `delivery_label` | مثل «توصيل سريع» |

لا يوجد `filter[channel_id]` على قائمة المنتجات. البطاقة للعرض لا لفتح كتالوج قناة مستقل.

### EP-RT-011 `GET /app/retailer/categories`

`parent_id` · `level`. الصف: `id` · `name` · `image` · `children_count` · `products_count`.

`children_count > 0` مستوى آخر. وإلا `GET /products?filter[category_id]=`.

### بطاقة المنتج

تُستخدم في الشبكة والمفضلة وشرائح التفاصيل وصفحة البراند.

| حقل | نوع | ملاحظة Flutter |
|---|---|---|
| `id` | int | |
| `name` | string | |
| `brand` | `{id,name}` \| null | |
| `company` | `{id,name}` \| null | شارة الشركة. `null` يخفيها |
| `image` | string \| null | |
| `sale_unit` | string \| null | |
| `sale_unit_label` | string \| null | المفضّل للعرض: «كرتون (12 قطعة)» |
| `pieces_per_sale_unit` | int \| null | |
| `min_order_qty` | int | |
| `price` | `{type,value,from,to,label}` | `value` سعر الوحدة. `label` جاهز من الخادم |
| `price_before` | int | يتوسطه خط إن `discount > 0` |
| `discount` | int | |
| `discount_percent` | int | أخفِ الشارة عند 0 |
| `price_after` | int | السعر المعروض بعد الخصم |
| `sold_count` | int | |
| `rating` | number | |
| `rating_count` | int | |
| `availability` | string | لون فقط |
| `lead_time_days` | int \| null | |
| `lead_time_label` | string \| null | نص التوصيل |
| `is_favorite` | bool | |
| `has_offer` | bool | شارة. تفاصيل العرض من §7 |
| `variants_count` | int | |

### EP-RT-012 `GET /app/retailer/products`

| استعلام | معنى |
|---|---|
| `search` | اسم أو SKU |
| `filter[category_id]` | |
| `filter[brand_id]` | |
| `filter[available_only]=1` | النشط فقط |
| `filter[favorites]=1` | مفضلة هذا التاجر |
| `filter[offer_only]=1` | **يعيد فارغاً اليوم**. تبويب العروض = `GET /app/offers` |
| `barcode` | مطابقة حرفية |
| `page` `per_page` | `per_page` حتى 100 |

`data[]` بطاقة منتج.

### EP-RT-013 `GET /app/retailer/products/{id}`

البطاقة زائد:

| حقل | واجهة |
|---|---|
| `images[]` | المعرض. إن فرغ استخدم `image` |
| `model_no` | |
| `short_description` | |
| `long_description` | HTML معقّم. لا WebView كامل |
| `features[].text` | قائمة الميزات |
| `specs[].key` `value` | جدول المواصفات |
| `dimensions` | `net_weight_gram` `gross_weight_gram` `height_mm` `width_mm` `length_mm` |
| `packaging` | `container_type` `capacity` `pieces_per_carton` `carton_dimensions` `carton_weight_gram` |
| `variant_axes[].key` `values[]` | أزرار الخصائص |
| `variants[]` | `id` `combination` `price` `availability` `barcode` |
| `sliders.same_brand[]` | بطاقات |
| `sliders.similar[]` `suggested[]` | بطاقات |
| `sliders.price_comparison` | قد يفرغ |

اختيار المحور يطابق `combination`. سعر الإضافة `variants[].price`. `404` خارج التغطية.

الأبعاد تبقى بالمليمتر والغرام في النموذج. التحويل للعرض فقط.

### EP-RT-014 `POST /app/retailer/products/{id}/favorite`

جسم `{}`. `data.is_favorite` ينقلب. `200` لا `201`. تحديث تفاؤلي ثم تراجع عند الفشل.

### EP-RT-014B `GET /app/retailer/favorites`

نفس بطاقة المنتجات، مفضلة هذا التاجر فقط. مكافئ `products?filter[favorites]=1`.

### EP-RT-015A `GET /app/retailer/brands`

`filter[activity_type_id]` · `filter[root_category_id]`.

الصف: `id` · `name` · `logo` · `banner` · `description` · `categories[{id,name}]`.

### EP-RT-015B `GET /app/retailer/brands/{id}`

`id` · `banner` · `logo` · `name` · `description` · `activities[{id,name}]` · `categories[]` · `offers[]` (بطاقة عرض) · `sliders[{key,items}]`.

`key = best_selling` و`items` بطاقات منتج. القلب على المنتج مفضلة منتج. لا مفضلة براند.

### EP-RT-070A `GET /app/retailer/shortages`

صف: `id` · `product_id` · `name` · `note`. هذه رغبات المحل **ليست** نقص تجهيز الطلب.

### EP-RT-070B `POST /app/retailer/shortages`

```json
{ "product_id": 880, "note": "نفد من الرف" }
```

`data.id`.

### EP-RT-070C `DELETE /app/retailer/shortages/{id}`

`data.success`. معرّف صف النقص لا معرّف المنتج.

---

## 6. التسعير والسلة

### EP-APP-030 `POST /app/pricing/quote`

مصدر السعر قبل الإرسال. لا ترسل سعراً من عندك.

```json
{
  "lines": [{ "product_id": 880, "variant_id": 1, "qty": 6 }],
  "zone_id": 12
}
```

`data.lines[]`: `product_id` · `unit_price` · `applied_rule{type,id,label}` · `tier{from,to}` · `discount` · `line_total`.

`gross` مجموع السعر. `discount` مجموع الخصم. `subtotal = gross - discount`. الثلاثة `int`. `currency` مثل `SYP`.

الأولوية على الخادم: سعر التاجر، ثم مجموعة، ثم منطقة، ثم شريحة كمية، ثم الأساس. لا تتراكم.

### EP-RT-020 `GET /app/retailer/cart`

```text
sections[].supply_channel_ref   مبهم. عنوان داخلي فقط
sections[].temp_order_no
sections[].note
sections[].lines[]               id, product_id, name, qty, unit_price, line_total
sections[].subtotal
sections[].discount
sections[].total
summary.grand_total              قبل الخصم
summary.total_discount
summary.final_total              المبلغ الذي يُعرض على زر الإرسال
summary.estimated_delivery
applied_offers[]
```

### EP-RT-021 `POST /app/retailer/cart/lines`

```json
{ "product_id": 880, "variant_id": 1, "qty": 4, "source": "browse" }
```

`source`: `browse` | `shortage` | `favorite` | `reorder` | `offer`.

يرد السلة كاملة بعد إعادة الحساب. إضافة نفس `(product, variant)` تجمع الكمية.

### EP-RT-022 `PATCH /app/retailer/cart/lines/{id}`

```json
{ "qty": 2 }
```

السلة زائد `removed_offers[]` للعروض التي سقط شرطها. اعرضها.

### EP-RT-023 `DELETE /app/retailer/cart/lines/{id}`

حذف بند داخل عرض قد يسقط العرض كله. الرد سلة.

### EP-RT-024 `PATCH /app/retailer/cart/sections/{ref}`

`{ref}` هو `supply_channel_ref`.

```json
{ "note": "الاتصال قبل الوصول", "scheduled_at": "2026-03-02T10:00:00+03:00" }
```

`scheduled_at` اختياري. حذف المفتاح يُبقي القيمة. الرد سلة.

### EP-RT-025 `POST /app/retailer/cart/submit`

```json
{
  "sections": [{ "ref": "ch_a1", "note": "الاتصال قبل الوصول", "scheduled_at": null }],
  "client_created_at": "2026-03-01T09:10:00+03:00",
  "offline_created": false
}
```

`data.order`: `id` · `order_no` · `sub_orders[]{sub_order_no,status,total}`. `data.repricing_diff` عند `offline_created: true` (يُقبل ثم يُعاد تسعيره).

| رمز | واجهة |
|---|---|
| `423 credit_limit_exceeded` | حد الائتمان. لا إعادة صامتة |
| `409 offer_no_longer_valid` | أعد تحميل السلة |
| `409 operation_in_progress` | نفس مفتاح التكرار |

التقسيم إلى طلبات فرعية يتم على الخادم. لا تفترض قناة واحدة.

---

## 7. العروض

### EP-APP-040 `GET /app/offers`

`filter[activity_type_id]` · `filter[zone_id]`. للتاجر يُستكمل الفلتر من ملفه إن تُرك.

| حقل | واجهة |
|---|---|
| `id` `image` `name` | البطاقة |
| `company{id,name}` | الشارة. قد تكون `null` |
| `rating` `rating_count` | |
| `components[]` | `product_id` · `name` · `qty` |
| `price_before` `discount` `price_after` | |
| `ends_at` `days_left` | |
| `remaining_qty` `sold_count` | |
| `is_favorite` | |

### EP-APP-041 `GET /app/offers/{id}`

البطاقة زائد `discount_percent` · `terms[]` (قائمة الشروط) · `images[]` · `long_description` · `icons[]` · `same_company_offers[]` · `same_company_products[]{id,name,image}` · `is_favorite`.

`404` إن العرض خارج المنطقة أو متوقف. قبل الإرسال أعد `quote`. الإضافة للسلة بنود `source: "offer"` لا مسار سلة خاص بالعرض.

### EP-APP-042 `POST /app/offers/{id}/favorite`

جسم `{}`. `data.is_favorite`. `404` إن العرض غير ظاهر.

---

## 8. الطلبات والتتبع ونقص التجهيز

حالات `status`: `pending` `confirmed` `rejected` `cancelled` `assigned` `accepted` `processing` `awaiting_handover` `on_the_way` `delivered` `undelivered` `postponed`.

تسميات الواجهة تُحفظ في التطبيق كخريطة ثابتة. مسار التتبع يعيد `label` عربياً للمراحل التي حدثت. استعمله هناك.

`supply_channel` يساوي `null` في `pending` و`rejected`. غير ذلك `{id,name}`.

### EP-RT-030 `GET /app/retailer/orders`

| استعلام | قيم |
|---|---|
| `filter[status]` | `all` `active` `pending` `processing` `on_the_way` `delivered` `cancelled` `postponed` |
| `filter[channel_id]` | من طلب مكشوف سابق |
| `search` | يطابق `sub_order_no` |
| `date_from` `date_to` | `YYYY-MM-DD` |

الصف: `id` · `sub_order_no` · `created_at` · `supply_channel` · `status` · `total` · `can_cancel` · `can_reorder` · `can_track` · `can_view_invoice`.

`meta.status_counts`: `all` `pending` `processing` `on_the_way` `delivered` `cancelled` `postponed`. العداد لا يتبع رقاقة الحالة. يتبع البحث والقناة. لا تعدّ عناصر الصفحة.

`processing` في الفلتر يشمل `processing` و`awaiting_handover`. `active` يستثني المسلّم والملغى والمرفوض.

### EP-RT-031 `GET /app/retailer/orders/{id}`

| حقل | واجهة |
|---|---|
| `id` `sub_order_no` `status` `created_at` | الرأس |
| `eta_window.from` `to` | `null` يخفي النافذة |
| `products_total` `discount_total` `total` | المجاميع |
| `lines[]` | `id` `name` `image` `qty` `attrs` `price_before` `discount` `discount_percent` `price` `line_total` |
| `invoice` | `{id,no,total}` أو `null` |
| `timeline[].stage` `at` | |
| `rep` | `{name,phone}` فقط في `on_the_way` وإلا `null` |
| `can_track` `can_reorder` `can_cancel` | الأزرار |

`attrs` خريطة نص، مثل `{ "المتغير": "5L" }`.

### EP-RT-032 `POST /app/retailer/orders/{id}/cancel`

```json
{ "reason": "طلب بالخطأ" }
```

`data.status = cancelled`. `409 illegal_transition` إن الحالة ليست `pending`. الزر يظهر فقط مع `can_cancel`.

### EP-RT-033 `POST /app/retailer/orders/{id}/reorder`

```json
{ "modify": false }
```

`data.cart` سلة معاد تسعيرها بأسعار اليوم. افتح شاشة السلة.

### EP-RT-034 `GET /app/retailer/orders/{id}/tracking`

| حقل | واجهة |
|---|---|
| `id` `sub_order_no` `status` `created_at` | الرأس |
| `stages[].key` `label` `at` | `at: null` مرحلة لم تكتمل. اعرض `label` |
| `rep` | في `on_the_way`: `name` `phone` `lat` `lng`. غير ذلك `null` |
| `map_available` | نقطة واحدة. لا خط سير |
| `eta_minutes` | قد يكون `null` خارج الطريق |
| `eta_window` | أو `null` |
| `lines[]` | `id` `name` `image` `qty` `price` |
| `total` | |
| `can_rate_rep` | بعد `delivered` ومع مندوب |

`lat`/`lng` آخر نقطة حية أو تقدير داخل دمشق إن لم تصل نقطة. لا ترسم polyline.

### EP-RT-035 `GET /app/retailer/orders/{id}/shortage`

`{id}` معرّف الطلب الفرعي. موجود فقط بانتظار قرار المحل بعد نقص مستودع.

`data`: `sub_order_id` · `status` · `lines[]{sub_order_line_id,product_id,name,qty_requested,qty_allocated}` · `total_before` · `total_after`.

`404` لا نقص معلّق. أخفِ الدخول.

### EP-RT-036 `POST /app/retailer/orders/{id}/shortage-decision`

```json
{ "decision": "accept_partial" }
```

`accept_partial` يثبت الكميات المخصّصة. `cancel` يلغي الطلب الفرعي ويحرر الحجز.

`data.status` · `data.decision`. `409` إن القرار لم يعد متاحاً.

---

## 9. الاستلام والمرتجع والتقييم

### EP-RT-040 `GET /app/retailer/receipts/{subOrderId}`

صلاحية `rt.receive.confirm`.

`lines[]`: `id` · `image` · `name` · `brand` · `variant` · `qty` · `price` · `status`. `invoice_total`.

### EP-RT-041 `PATCH /app/retailer/receipts/{id}/lines/{lineId}`

```json
{ "qty_received": 3, "action": "return", "reason": "عبوة تالفة" }
```

`action`: `accept` | `return` حسب التنفيذ. `data.new_invoice_total`.

### EP-RT-042 `POST /app/retailer/receipts/{id}/confirm`

```json
{ "lines": [{ "line_id": 1, "qty_received": 4, "action": "accept" }] }
```

`data.invoice{no,total}` · `data.ask_payment`. إن `ask_payment` افتح تسجيل الدفعة.

### EP-RT-043 `POST /app/retailer/return-requests`

صلاحية `rt.receive.return_request`.

```json
{
  "sub_order_id": 9001,
  "type": "return",
  "lines": [{ "line_id": 1, "qty": 1, "reason": "تالف", "photos": [] }]
}
```

`type`: `return` | `exchange`. `data.request_no` · `data.status`.

### EP-RT-044 `GET /app/retailer/return-requests`

`filter[status]` · `filter[channel_id]` · `date_from`. الصف: `request_no` · `type` · `status`.

### EP-RT-045 `POST /app/retailer/reps/{id}/rate`

`{id}` معرّف المندوب من الطلب وهو في الطريق أو بعد التسليم عندما `can_rate_rep`.

```json
{ "stars": 5, "note": "سريع ومهذب", "tags": ["fast", "polite"] }
```

`tags`: `fast` | `polite` | `intact` | `late` | `damaged`. `data.success`.

---

## 10. الدفع والحساب

### EP-RT-050 `POST /app/retailer/payments`

صلاحية `rt.payment.record`. رقم الوصل يأتي من تطبيق المندوب. لا تحجزه من هنا.

```json
{
  "receipt_no": "RCPT-10041",
  "rep_id": 70,
  "supply_channel_id": 1,
  "invoice_no": "INV-501",
  "amount": 48000
}
```

`amount` أصغر وحدة. `data.payment.id` · `data.retailer_receivable`.

`409 duplicate_receipt_no` دائماً عند تكرار الرقم. لا تعِد بمفتاح جديد لنفس الوصل.

### EP-RT-051 `GET /app/retailer/account/summary`

`month=YYYY-MM`.

`month` · `orders_total` · `purchases_total` · `orders_count` · `avg_order_value` · `debt_total` · `payments_total` · `points`.

من لقطات يومية لا من جمع حي. شهر بلا لقطة قد يرجع أصفاراً.

### EP-RT-052 `GET /app/retailer/account/statement`

صلاحية `rt.account.statement`. `date_from` · `date_to` · `channel_id`.

`opening_balance` · `rows[]{date,type,ref_no,debit,credit,balance}` · `closing_balance`.

الثابت: ذمة التاجر = الفواتير − الدفعات − إشعارات الدائن.

### EP-RT-053 `POST /app/retailer/account/statement/export`

```json
{
  "date_from": "2026-02-01",
  "date_to": "2026-02-28",
  "channel_id": 1,
  "format": "pdf"
}
```

`data.job_id`. استعلم عن مهمة التصدير حتى يظهر رابط.valid لمدة 24 ساعة.

### EP-RT-054 `GET /app/retailer/debts`

```text
by_channel[].channel{id,name}
by_channel[].total
by_channel[].overdue
by_channel[].invoices[]{no,total,remaining}
```

اسم القناة هنا مقصود.

---

## 11. نماذج Dart

```dart
class Envelope<T> {
  final T data;
  final Map<String, dynamic> meta;
  Envelope(this.data, this.meta);
}

class ApiError implements Exception {
  final String code;
  final String message;
  final Map<String, dynamic> details;
  ApiError(this.code, this.message, this.details);
}

class ProductCard {
  final int id;
  final String name;
  final ({int id, String name})? brand;
  final ({int id, String name})? company;
  final String? image;
  final String? saleUnitLabel;
  final int minOrderQty;
  final int priceValue;
  final int priceBefore;
  final int discount;
  final int discountPercent;
  final int priceAfter;
  final int soldCount;
  final double rating;
  final int ratingCount;
  final String availability;
  final String? leadTimeLabel;
  final bool isFavorite;
  final bool hasOffer;
}
```

حلّل المال كـ `int`. `rating` وحده `double`.

مفتاح التكرار: UUID في ترويسة الطلب، يُحفظ مع نيّة الكتابة في الصندوق حتى `applied` / نجاح مباشر / `duplicate`.

---

## 12. قبول

1. الإقلاع يتوقف عند `force_update` و`maintenance` و`user_type` غير `retailer`.
2. التسجيل بلا بريد، والمنطقة تابعة للمحافظة، و`pending_review` لا يفتح الرئيسية.
3. الرئيسية من `GET /home` وحده: ترحيب، حضور، قنوات، اختصارات، فئات، عروض، براندات.
4. `PUT /presence` ينعكس على `header.is_online`.
5. المفضلة منتجاً وعرضاً خاصة بالتاجر ولا تتسرّب لغيره.
6. `filter[offer_only]` لا يُستخدم كتبويب عروض. العروض `GET /app/offers`.
7. لا قسمة للمبالغ. `summary.final_total` هو زر الإرسال.
8. لا `X-Channel-Id`. السلة لا تعرض اسم قناة. الطلب المعلّق يخفي `supply_channel`.
9. عدادات الطلبات من `meta.status_counts`.
10. التتبع يعرض `label` ونقطة الخريطة فقط عند `map_available`.
11. نقص الرغبات `/shortages` منفصل عن `/orders/{id}/shortage`.
12. الدفعة برقم وصل المندوب. التكرار `409` لا يُعاد بمفتاح جديد.
13. `403 insufficient_permission` يخفي الزر ولا يعيد المحاولة.
