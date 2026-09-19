# تطبيق المندوب — مواصفة Flutter (GetX) + عقد الـ API

| | |
|---|---|
| المستند | **العقد الوحيد** لبناء وإكمال تطبيق المندوب الميداني (Flutter + GetX) على واجهة Laravel هذه |
| الجمهور | مطوّر Flutter، وCursor AI عند إكمال الجوانب الناقصة، ومراجع القبول |
| الحارس | `auth:app` + `app.kind:rep` · `X-Client: rep-android` أو `rep-ios` · **لا** `X-Channel-Id` |
| الأساس | `/api/v1` |
| التاريخ | 2026-09-19 — من الكنترولر الحي + الكتالوج + `docs/status/` + موجّه المنتج (لوحة الإدارة) |
| JSON الحي | `docs/DocsLast/flutter-rep.json` — يُولَّد بـ `php docs/api/generate-rep-live.php`. مسار `live:true` في JSON يفوز على جملة هنا؛ هذا الملف يفوز على الشكل والشاشات |

> **قاعدة الأولوية (لا تُكسر):** الكود الحي في `app-modules/` ← `docs/status/05-rep-app.md` + `06-shared-app.md` + `07-public.md` ← الكتالوج `docs/api/catalog/08-rep.php` + `09-shared-app.php` + `00-public.php` ← هذا الملف.
> جملة هنا تخالف الكنترولر → الكنترولر يفوز وهذا الملف يُصلَّح.
> شاشة في موجّه المنتج بلا مسار حي → ابنِ الواجهة، اربط المصدر بـ `RemoteNotReady`، **لا تخترع مساراً**.

هذا الملف كافٍ لـ Cursor لإكمال كل شاشة ناقصة في تطبيق المندوب عندما يصل مستودع Flutter لاحقاً. لا تعتمد على ذاكرة المحادثة — اعتمد على الجداول والعقود أدناه.

---

## المحتويات

| § | العنوان |
|---|---|
| 0 | كيف تقرأ الرموز والمال والبيئة |
| 1 | فهم المندوب — موجّه المنتج |
| 2 | المهام الرئيسية الاثنتا عشرة |
| 3 | معمارية GetX (مجلدات، حزم، حقن، تنقّل) |
| 4 | العقد العابر (غلاف، ترويسات، تكرار، أخطاء، اتصال) |
| 5 | الانترو والتسجيل والجلسة |
| 6 | الصدفة: الرئيسية + الشريط السفلي |
| 7 | تسجيل الطلب (السلة كمحل) |
| 8 | التسليم / القبول / المجدولة / المستودع / المرتجع |
| 9 | التحصيل والمحفظة |
| 10 | المنتجات · العملاء · المناطق · الطلبات · الحساب · الإشعارات |
| 11 | كتالوج المسارات — عيّنات JSON كما يعيدها الخادم اليوم |
| 12 | نماذج Dart + عميل Dio |
| 13 | دون اتصال والطابور المحلي |
| 14 | فجوات المنتج مقابل الخادم — ماذا تبني / ماذا تؤجّل |
| 15 | قائمة قبول Cursor |
| 16 | بذور QA |

---

## 0. كيف تقرأ هذا الملف

### 0.1 الرموز

| رمز | المعنى | ماذا تفعل في Flutter |
|---|---|---|
| ✅ | حي على المسار المتعاقد | Dio حقيقي، لا mock |
| 🟡 | حي بتحفّظ مكتوب | ابنِ، واقرأ التحفّظ |
| ⛔ | غير مبني (404) | **لا تستدعِه.** UI + `RemoteNotReady` أو كاش محلي |
| 🔁 | يحتاج `X-Idempotency-Key` | UUID واحد لكل ضغطة تأكيد |
| 🧩 | شاشة المنتج موجودة والخادم ناقص | ابنِ الواجهة؛ أغلق الأزرار التي تكتب |

لا تفحص `rp.*` لإخفاء شاشات. `session.permissions` قائمة نوع المستخدم. بعد اكتمال الملف **كل شاشات المندوب ظاهرة**.

### 0.2 المال

كل مبلغ `int` بأصغر وحدة. الليرة السورية `decimals = 0`:

- الخادم: `12000`
- العرض: `12,000 ل.س`
- **لا تقسم على 100.** لا `double`. لا `num` في نماذج المال — `int`.
- التنسيق: `NumberFormat.decimalPattern('ar')` + لاحقة `ل.س`، أرقام غربية 0–9، `FontFeature.tabularFigures()`.

### 0.3 الوقت

`Asia/Damascus` (`+03:00`). اعرض `server_time` و`scheduled_at` و`paid_at` كما هي. لا تحوّل لمنطقة الجهاز في المال والتسليم.

### 0.4 البيئة

| مفتاح | قيمة محلية |
|---|---|
| `BASE_URL` | `http://127.0.0.1:8000` (محاكي Android: `http://10.0.2.2:8000`) |
| `X-Client` | `rep-android` / `rep-ios` |
| `X-Device-Id` | UUID ثابت لكل تثبيت (FlutterSecureStorage) |
| `X-App-Version` | مثال `1.0.0 (1)` |
| OTP محلي | `"0000"` كنص من 4 خانات. الإنتاج 6 خانات |
| لا إيميل | لا كلمة سر |

`--dart-define=BASE_URL=...` و`--dart-define=ENV=dev|staging|prod`.

### 0.5 الهاتف السوري

حقل واحد. طبّع قبل الإرسال (الخادم يطبّع أيضاً):

| إدخال المستخدم | يُرسل |
|---|---|
| `0932000001` | `+963932000001` |
| `932000001` | `+963932000001` |
| `+963932000001` | كما هو |

القاعدة: `^\+9639\d{8}$`. لوحة مفاتيح رقمية، اتجاه LTR داخل الحقل.

---

## 1. فهم المندوب — موجّه المنتج

المندوب **عامل ميداني** وليس «مستخدم تكنولوجيا». يقضي ساعات 8–10 في الشارع، يحمل البضاعة، ويتعامل يومياً مع عشرات التجار. لا وقت للتعلّم ولا للتجربة.

التطبيق يجب أن يكون:

1. **سريعاً جداً** — الطلب يُسجَّل في أقل من 30 ثانية. الجولة تُدار بـ 3 نقرات.
2. **يعمل بدون إنترنت** — السوق السوري يعاني انقطاعاً مستمراً. أونلاين-أول اليوم (المزامنة ⛔)، مع طابور محلي وإعادة عند عودة الشبكة. لا تَعِد بمزامنة خادم حتى يُبنى `AP-03`.
3. **بسيطاً جداً** — لا شاشة «تعلّم». المندوب يستخدمه أول مرة وينجح.
4. **يحاكي الواقع** — يحسّن الـ workflow ولا يغيّره.
5. **لا يقلّل من قيمة المندوب** — يظهر كأداة تمكين لا كأداة استبدال. المندوب يخشى أن يصبح التطبيق بديلاً عنه.

### 1.1 من هو المستخدم تقنياً

- حساب تطبيق `app` نوع `rep`.
- بعد التسجيل ينتمي إلى **قناة توريد واحدة** (`rep_profiles.channel_id`).
- هاتف واحد = نوع واحد. إن `user_type === "retailer"` هذا تطبيق خاطئ — اخرج فوراً. لا مبدّل أدوار.
- ملف جديد: `status = pending_review`. الخادم **لا يحجب** المسارات التشغيلية بانتظار الموافقة. أظهر شارة «قيد المراجعة» في الحساب فقط.

### 1.2 حسابات البذرة (بعد `php artisan db:seed`)

| هاتف | الاسم | ملاحظة |
|---|---|---|
| `+963932000001` / `0932000001` | عمر الشامي | ملف مكتمل، في الخدمة، سقف خصم 10٪، سقف نقد 5,000,000 |
| `+963932000002` | ياسر حمود | في الخدمة |
| `+963932000003` | نور الدين حلبي | خارج الخدمة |

OTP المحلي: أرسل `code` **String** `"0000"`. الرقم `0000` كـ `int` يصبح `0` ويُرفض.

تجار بذرة للسلة: `+963931000001` سوبر ماركت الأمانة (المزة)، `+963931000002` بقالية النور.

---

## 2. المهام الرئيسية (موجّه المنتج → شاشة GetX → مسار حي)

| # | مهمة المنتج | شاشة GetX | مصدر حي |
|---|---|---|---|
| 1 | تسجيل طلب مبيعات | `OrderCaptureView` + `CartView` | ✅ سلة + quote + منتجات |
| 2 | تسليم طلب | `DeliveriesView` + `DeliveryDetailView` | ✅ `/app/rep/deliveries*` |
| 3 | استلام من المستودع | `WarehouseReceiptsView` | ✅ `/app/rep/warehouse-receipts*` |
| 4 | إضافة محل جديد | `AddCustomerView` | ✅ `POST /app/rep/customers` |
| 5 | طلبات مجدولة | `ScheduledOrdersView` | ✅ `GET /app/rep/scheduled-orders` |
| 6 | قبول الطلب | `AssignmentsView` | ✅ `/app/rep/assignments*` |
| 7 | استلام دفعة | `CollectPaymentView` | ✅ `POST /app/rep/payments` + حجز وصل |
| 8 | تسليم مبلغ للمحاسب | `WithdrawView` | ✅ `POST /app/rep/wallet/withdrawals` |
| 9 | قائمة المنتجات | `ProductsView` | ✅ `GET /app/rep/products` + عروض |
| 10 | قائمة العملاء | `CustomersView` | ✅ `GET /app/rep/customers` |
| 11 | قائمة المناطق | `ZonesView` | 🟡 مناطق من التسجيل/refs + `GET .../zones/{id}/shops` — **لا** `GET /app/rep/zones` |
| 12 | شريط النقاط والجوائز | `LoyaltyBar` | ⛔ `loyalty: null` على `GET /app/rep/home` — أخفِ الشريط |

---

## 3. معمارية GetX

### 3.1 الحزم (`pubspec.yaml`)

ثبّت هذه فقط. لا GetX + Bloc معاً. لا `http` بجانب Dio.

```yaml
dependencies:
  flutter:
    sdk: flutter
  get: ^4.6.6
  get_storage: ^2.1.1
  dio: ^5.7.0
  flutter_secure_storage: ^9.2.2
  connectivity_plus: ^6.1.0
  uuid: ^4.5.1
  intl: ^0.19.0
  cached_network_image: ^3.4.1
  pin_code_fields: ^8.0.1
  mobile_scanner: ^6.0.2
  geolocator: ^13.0.2
  google_maps_flutter: ^2.10.0   # أو flutter_map إن رُفض Google Play
  url_launcher: ^6.3.1
  permission_handler: ^11.3.1
  share_plus: ^10.1.4
  pdf: ^3.11.1
  printing: ^5.13.4
  image_picker: ^1.1.2
  speech_to_text: ^7.0.0
  package_info_plus: ^8.1.2
  flutter_svg: ^2.0.16
  video_player: ^2.9.2          # انترو إن media_type=video و media_id يبدأ بـ http

flutter:
  uses-material-design: true
  generate: true
```

RTL: `locale: Locale('ar')` + `Directionality` من `GetMaterialApp`. لا `flutter_localizations` بدون `delegate`.

### 3.2 هيكل المجلدات — لا تحد عنه

```
lib/
  main.dart
  app.dart
  core/
    env.dart
    theme/
      app_theme.dart
      tokens.dart
    money/money.dart
    phone/syrian_phone.dart
    errors/api_exception.dart
    errors/error_messages.dart      # map error.code → عربي
    network/
      api_client.dart               # GetxService + Dio
      interceptors.dart             # auth, device, idempotency, envelope
      connectivity_service.dart
    storage/
      secure_store.dart             # token, device_id
      prefs.dart                    # GetStorage: session, refs, outbox
    widgets/                        # أزرار إبهام، بطاقة، هيكل عظمي، مال، شارة حالة
  data/
    models/                         # fromJson يطابق الخادم حرفياً
    dto/                            # أجسام الطلب
    sources/
      remote/                       # *Remote: استدعاءات Dio فقط
      local/                        # *Local: GetStorage / outbox
    repositories/                   # واجهة واحدة لكل مجال؛ الشاشة لا ترى Dio
  modules/
    splash/
    intro/                          # GET /public/content/intro — ليس /platform
    auth/                           # هاتف + OTP
    register/
    shell/                          # BottomNav + AppBar مشترك
    home/
    order_capture/
    products/
    customers/
    zones/
    cart/
    orders/                         # طلبات المندوب المسجَّلة (كاش محلي + تسليم)
    assignments/
    scheduled/
    warehouse/
    deliveries/
    payments/
    wallet/
    account/
    notifications/                  # UI جاهز، remote ⛔
    loyalty/                        # ويدجت شريط فقط، remote ⛔
  routes/
    app_pages.dart
    app_routes.dart
    auth_middleware.dart
    guest_middleware.dart
```

كل وحدة: `*_binding.dart` + `*_controller.dart` + `*_view.dart` + اختياري `widgets/`.

### 3.3 قواعد GetX

1. **Controller واحد لكل شاشة.** لا `Get.find` داخل `build` بلا `GetBuilder`/`Obx`.
2. الحالة الظاهرة `Rx*`. لا `setState`.
3. المستودعات `GetxService` دائمة (`permanent: true`) تُسجَّل في `main()` قبل `runApp`.
4. الـ Binding يحقن كنترولر الشاشة فقط. لا Binding يخلق Dio ثانٍ.
5. التنقّل بأسماء فقط: `Get.toNamed(Routes.deliveryDetail, arguments: id)`.
6. حوارات وأخطاء: `Get.snackbar` للنجاح القصير، `Get.dialog` للتأكيد الخطير (تعذّر، سحب نقد)، `Get.bottomSheet` للمتغيرات (علي بابا).
7. بعد 401 (ما عدا `otp_invalid` / `otp_expired`): امسح التوكن → `Get.offAllNamed(Routes.phone)`.
8. الضيف (`prefs.guest`): `AuthMiddleware` يسمح بالصدفة بلا توكن. أي خدمة (طلب/سلة/تسليم/محفظة) → «يجب أن تسجّل حساباً لاستخدام هذه الخدمة».

### 3.4 خريطة المسارات

| `Routes.` | المسار | وسطاء |
|---|---|---|
| `splash` | `/` | — |
| `intro` | `/intro` | — |
| `phone` | `/auth/phone` | Guest |
| `otp` | `/auth/otp` | Guest |
| `register` | `/auth/register` | Token + `profile_completed == false` |
| `shell` | `/app` | Auth |
| `orderCapture` | `/app/order-capture` | Auth |
| `addCustomer` | `/app/customers/new` | Auth |
| `customerDetail` | `/app/customers/:id` | Auth |
| `addZone` | `/app/zones/new` | Auth |
| `zoneShops` | `/app/zones/:id/shops` | Auth |
| `productDetail` | `/app/products/:id` | Auth |
| `assignments` | `/app/assignments` | Auth |
| `scheduled` | `/app/scheduled` | Auth |
| `warehouse` | `/app/warehouse` | Auth |
| `deliveries` | `/app/deliveries` | Auth |
| `deliveryDetail` | `/app/deliveries/:id` | Auth |
| `collect` | `/app/collect` | Auth |
| `withdraw` | `/app/wallet/withdraw` | Auth |
| `withdrawals` | `/app/wallet/withdrawals` | Auth |
| `receivables` | `/app/wallet/receivables` | Auth |
| `notifications` | `/app/notifications` | Auth |
| `settings` | `/app/settings` | Auth |

الشريط السفلي **لا يدفع مسارات جديدة** للتبويبات الثلاثة — `IndexedStack` داخل `ShellView`.

### 3.5 الشريط السفلي (ثلاثة تبويبات)

موجّه المنتج: **السلة — الطلبات — الرئيسية.** لا تبويب رابع.

من اليمين لليسار في RTL (الأوسط هو الرئيسية):

| فهرس | التبويب | الشاشة |
|---|---|---|
| 0 | السلة | `CartTab` |
| 1 | الطلبات | `OrdersTab` (إسنادات + مجدولة + تسليم — لا `GET /app/rep/orders`) |
| 2 | **الرئيسية** | `HomeTab` |

`initialIndex = 2`. أيقونة الرئيسية أكبر قليلاً.

الحساب والمحفظة: من رأس الرئيسية (الإعدادات) ومن أزرار المهام (استلام دفعة)، لا من الشريط.

اختصارات المهام تفتح مسارات فوق الصدفة (`Get.toNamed`) ثم `Get.back()`.

زائر: التبويبات ظاهرة؛ أي خدمة → §5.2.

### 3.6 نظام التصميم (مختصر)

إبهام أولًا: أزرار ≥ 48dp. بطاقة = نقرة واحدة. الإجراء الخطير تأكيد بخطوة ثانية.

| الرمز | فاتح | استعمال |
|---|---|---|
| `--primary` | `#0F766E` | إجراء أساسي |
| `--success` | `#15803D` | مسلَّم، في الخدمة |
| `--warning` | `#B45309` | مؤجَّل |
| `--danger` | `#B91C1C` | ملغى / تعذّر / دين |
| `--info` | `#1D4ED8` | في الطريق / قبول |
| `--money` | `#111827` | مبلغ — بلا لون إلا الدين والرصيد |

حدود التسليم من الخادم `border_color`: `green` مسلَّم في الطريق · `blue` مقبول · `gray` مسلَّم اليوم · `red` تعذّر · المجدولة `amber`.

RTL أصيل. الهواتف والمعرّفات والأرقام LTR داخل النص العربي. خط عربي: IBM Plex Sans Arabic أو Noto Sans Arabic.

---

## 4. العقد العابر

### 4.1 الغلاف

نجاح:

```json
{
  "data": {},
  "meta": { "server_time": "2026-09-19T13:00:00+03:00" }
}
```

قائمة صفحات (`products`, `customers`, `offers`, `zones/{id}/shops`):

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 412,
    "last_page": 17,
    "server_time": "..."
  }
}
```

`per_page` افتراضي 25، سقف 100 يُقصّ بصمت. لا ترسل `sort`.

قوائم **بلا صفحات** (كائن أو مصفوفة داخل `data`): `assignments`, `scheduled-orders`, `deliveries`, `cart`, `wallet`, `receivables`, `warehouse-receipts`, `session`.

خطأ — **لا** payload تحقق Laravel النيء:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "...",
    "details": { "phone": ["..."] }
  }
}
```

`ApiClient` يرمي `ApiException(code, message, details, httpStatus)`. الواجهة تعرض الترجمة من `error.code` لا `message` الإنجليزي.

### 4.2 الترويسات — على كل طلب

```
Accept: application/json
Accept-Language: ar
Content-Type: application/json          # الكتابات فقط
X-Client: rep-android
X-Device-Id: <UUID التثبيت>
X-App-Version: 1.0.0 (1)
Authorization: Bearer <token>           # ما عدا /health و /public/*
X-Idempotency-Key: <uuid>               # كل كتابة ما عدا OTP الثلاثة
```

**لا ترسل** `X-Channel-Id`. مسارات `/app/rep` لا تضبط مستأجراً.

مسارات OTP الثلاثة **معفاة** من مفتاح التكرار. إن أرسلته قد يُرفض أو يُخزَّن خطأً — لا ترسله.

### 4.3 مفتاح التكرار 🔁

ولّده عند ضغط المستخدم «تأكيد»، وأعده حرفياً مع كل إعادة لنفس النيّة، واستبدله فقط عندما يبدأ من جديد.

| الخادم | المعنى |
|---|---|
| نفس المفتاح + نفس الجسم | يعيد الرد المخزَّن (24 ساعة) — اعتبره نجاحاً |
| نفس المفتاح + جسم مختلف | `409 idempotency_key_conflict` |
| معروف وقيد التنفيذ | `409 operation_in_progress` |

`client_op_id` في التحصيل وإضافة محل **مستقل** عن مفتاح التكرار. ولّدهما معاً واحفظهما حتى ينجح الرد.

### 4.4 خريطة الأخطاء

| HTTP | `code` | عربي | سلوك GetX |
|---|---|---|---|
| 401 | `unauthenticated` `token_revoked` | انتهت الجلسة | اخرج للهاتف |
| 401 | `otp_invalid` `otp_expired` | الرمز غير صحيح / انتهت صلاحيته | ابقَ على OTP |
| 403 | `wrong_guard` | توكن لوحة على تطبيق | اخرج |
| 403 | `insufficient_permission` | نوع/منطقة لا تطابق | رسالة |
| 403 | `discount_cap_exceeded` | الخصم أعلى من سقفه | رسالة على حقل الخصم |
| 403 | `cash_cap_exceeded` | تجاوزت سقف حيازة النقد | 🟡 **403 لا 423** |
| 404 | `not_found` | غير موجود — أو ليس لك | لا تكشف 403 |
| 409 | `illegal_transition` | الخطوة غير مسموحة الآن | حوار |
| 409 | `duplicate_receipt_no` | رقم الوصل مستخدم | حوار |
| 409 | `idempotency_key_conflict` | مفتاح مكرر بجسم مختلف | ولّد مفتاحاً جديداً فقط إن غيّر المستخدم النية |
| 409 | `operation_in_progress` | العملية ما زالت تجري | انتظر وأعد بنفس المفتاح |
| 422 | `validation_failed` | تفاصيل الحقول | أبرز `error.details` |
| 429 | `rate_limited` | انتظر | OTP أو نبضة &lt; 30ث |
| 426 | `upgrade_required` | حدّث التطبيق | ⛔ المسار غير حي — لا تعتمد عليه |
| 503 | `maintenance_mode` | صيانة | شاشة ثابتة |

### 4.5 حالة الاتصال (شريط الرئيسية)

| لون | معنى محلي | متى |
|---|---|---|
| أخضر | متصل | `connectivity` + آخر طلب 2xx |
| رمادي | غير متصل | لا شبكة |
| أصفر | مزامنة معلّقة | outbox غير فارغ |

زر «مزامنة يدوية» يظهر فقط إن outbox غير فارغ. اليوم يفرّغ الطابور بإعادة POST للمسارات الحيّة (تحصيل، محل، سلة). لا تستدعِ `/app/sync/*` (⛔).

### 4.6 `RemoteNotReady`

كلاس واحد:

```dart
class RemoteNotReady implements Exception {
  RemoteNotReady(this.endpoint, this.ticket);
  final String endpoint; // e.g. GET /app/notifications
  final String ticket;   // AP-02
}
```

الكنترولر يمسكها ويعرض حالة فارغة صادقة: «قريباً من الخادم» لا بيانات مختلقة.

---

## 5. الانترو · التسجيل · الجلسة

### 5.1 الانترو — من لوحة التحكم، على التطبيق ✅

موجّه المنتج: انترو **قصير وسريع** (شعار التطبيق + رسالة ترحيب، فيديو اختياري). يُحرَّر من السنترال (إضافة/تعديل النص والفيديو). **حساب سابق → الرئيسية مباشرة، بلا انترو.** لا حساب → انترو ثم شاشة الهاتف/التسجيل.

**مسار التطبيق (هذا ما تستدعيه Flutter):**

`GET /api/v1/public/content/intro` — 🔓 بلا Bearer، بلا مفتاح تكرار. **EP-PB-011 · حي.** نفس الصف الذي يكتبه الأدمن في `PUT /platform/content/intro`.

```
Splash ──GET /health + GET /public/content/intro──▶
   توكن؟ ─نعم─▶ GET /app/session ─ profile مكتمل ─▶ الرئيسية (تجاوز الانترو)
                 └ profile ناقص ─▶ Register
   لا توكن ─ enabled==true ─▶ IntroView (duration ثوانٍ) ─▶ Phone
            └ enabled==false ─▶ Phone (ومضة شعار ≤2ث على الـ splash فقط)
```

```json
{
  "enabled": true,
  "text": "مرحباً بك في شبكة التوزيع",
  "media_type": "video",
  "media_id": "media_intro_default",
  "duration": 8,
  "targeting": { "activity_type_ids": [], "zone_ids": [] }
}
```

مخزن فارغ (بعد `migrate:fresh` وقبل أن يحفظ الأدمن): `enabled: false`, `text`/`media_*` = `null`, `duration: 0`. هذا **صحيح** — لا تُحاكِ فيديو.

| المفتاح | ماذا يعرض التطبيق |
|---|---|
| `enabled` | `false` → لا شاشة انترو. `true` → اعرض ثم انتقل تلقائياً بعد `duration` (سقف 8 ث) أو لمسة «تخطي» |
| `text` | جملة الترحيب تحت الشعار. `null` → الشعار فقط |
| `media_type` | `image` \| `video` \| `null` |
| `media_id` | نص غامض **ليس URL**. إن بدأ بـ `http` شغّله كرابط؛ وإلا ابحث `assets/intro/{media_id}`؛ وإلا الشعار المحلي فقط. لا رفع وسائط على الخادم اليوم |
| `duration` | ثوانٍ صحيحة. `0` مع `enabled=true` → اعتبر 3 |
| `targeting` | **تجاهله** في أول تشغيل — لا منطقة بعد |

```dart
class IntroContent {
  final bool enabled;
  final String? text, mediaType, mediaId;
  final int duration;
  factory IntroContent.fromJson(Map<String, dynamic> j) => IntroContent(
    enabled: j['enabled'] == true,
    text: j['text'] as String?,
    mediaType: j['media_type'] as String?,
    mediaId: j['media_id'] as String?,
    duration: (j['duration'] as int?) ?? 0,
  );
}

class ContentService extends BaseService {
  Future<IntroContent> intro() => guard(() async =>
      (await api.get('/public/content/intro', (d) => IntroContent.fromJson(d as Map<String, dynamic>))).data);
}
```

كاش في `LocalStore` بعد نجاح الجلب؛ إن فشل الشبكة بلا كاش: ومضة الشعار المحلي ≤2ث ثم الهاتف — لا علّق الإقلاع.

**ممنوع من هذا التطبيق:** `GET/PUT /platform/content/intro` و`GET/PUT /channel/content/intro` → 403 `wrong_guard`. الأدمن يحرّر هناك؛ التطبيق **يقرأ** `/public/content/intro` فقط. `GET /public/app-config` ما زال ⛔ (تحديث إجباري) — ليس مصدر الانترو.

`SplashController`:

1. بالتوازي: `GET /health` + إن **لا** توكن `GET /public/content/intro`.
2. توكن → `GET /app/session` — تجاوز الانترو.
   - `user_type == retailer` → اخرج («هذا الحساب تاجر»).
   - `profile_completed == false` → `RegisterView`.
   - وإلا → `ShellView` (المهام والجولة والتحصيل — §6).
3. لا توكن + `enabled` → `IntroView` ثم `PhoneView`.
4. شبكة مع توكن: صدفة من كاش الجلسة إن وُجد.

لا شاشة تسويقية طويلة. شعار + جملة واحدة + فيديو اختياري.

### 5.2 تخطّي التسجيل (زائر) 🧩

موجّه المنتج: يمكن تخطّي التسجيل والتصفح **كزائر** للاطلاع على الرئيسية؛ عند طلب أي خدمة تظهر **«يجب أن تسجّل حساباً لاستخدام هذه الخدمة»**.

الخادم **لا يعرف الضيف.** لا توكن زائر. `GET /app/rep/home` بلا Bearer → 401 `unauthenticated`. كل `/app/rep/*` و`/app/session` تطلب حساباً.

التنفيذ المحلي:

- زر «تصفّح كزائر» على شاشة الهاتف يضبط `prefs.guest = true` ويفتح الصدفة (الرئيسية بعلامات صفر ليتعلّم المهام والجولة والتحصيل).
- لا تستدعِ أي `/app/*` وأنت زائر.
- أي خدمة (طلب، سلة، تسليم، محفظة، إضافة محل، داخل الخدمة، الإشعارات): حوار **«يجب أن تسجّل حساباً لاستخدام هذه الخدمة»** ثم `Routes.phone`.
- الدوائر الثلاث (منتجات / عملاء / مناطق) نفس القفل.
- لا `X-Idempotency-Key` بلا جلسة.

### 5.3 طلب OTP ✅ — معفى من التكرار

`POST /api/v1/public/auth/request-otp`

```http
POST /api/v1/public/auth/request-otp
X-Client: rep-android
X-Device-Id: 11111111-1111-1111-1111-111111111111
```

```json
{
  "phone": "+963932000001",
  "purpose": "login",
  "client": "rep-android"
}
```

| | |
|---|---|
| رقم جديد | `purpose: "register"` |
| عائد | `purpose: "login"` |
| إن 422 على login لرقم مجهول | أعد بـ `register` |

رد:

```json
{
  "data": {
    "otp_id": "otp_9f2a71",
    "channel_used": "whatsapp",
    "expires_in": 300,
    "resend_after": 60
  },
  "meta": { "server_time": "..." }
}
```

احفظ `otp_id`. مؤقّت إعادة الإرسال = `resend_after` من الرد لا 60 ثابتة. تنويه الواجهة: «سيصلك الرمز على واتساب».

429: 3/ساعة للهاتف. أظهر العدّاد.

شاشة الهاتف: حقل واحد، بادئة ظاهرة `+963` غير قابلة للحذف، لا إيميل.

### 5.4 تحقق OTP ✅ — معفى

`POST /api/v1/public/auth/verify-otp`

```json
{
  "otp_id": "<من الرد السابق>",
  "code": "0000",
  "device_id": "11111111-1111-1111-1111-111111111111",
  "device_name": "Redmi Note 13",
  "platform": "android"
}
```

`code` **String**. UI محلي: **4 خانات**. إنتاج: 6. `keyboardType: number`، `textDirection: ltr`.

مستخدم جديد:

```json
{
  "data": {
    "token": "12|xxxxx",
    "is_new_user": true,
    "user_type": null,
    "profile_completed": false,
    "user": { "id": 99, "name": "", "phone": "+9639..." }
  }
}
```

مندوب مكتمل:

```json
{
  "data": {
    "token": "12|xxxxx",
    "is_new_user": false,
    "user_type": "rep",
    "profile_completed": true,
    "user": { "id": 70, "name": "عمر الشامي", "phone": "+963932000001" }
  }
}
```

🟡 `user` للمندوب **بلا مناطق**. احفظ التوكن فوراً في SecureStorage.

| بعد النجاح | |
|---|---|
| `user_type === "retailer"` | تطبيق خاطئ — اخرج |
| `is_new_user` أو `profile_completed === false` | `RegisterView` — التوكن قدرة `registration` فقط |
| `user_type === "rep"` وملف مكتمل | `ShellView` |

`otp_invalid` / `otp_expired`: ابقَ هنا. 5 محاولات لكل `otp_id`. لا «تسجيل خروج».

### 5.5 إعادة الإرسال ✅ — معفى

`POST /api/v1/public/auth/resend-otp`

```json
{ "otp_id": "...", "prefer_channel": "whatsapp" }
```

`prefer_channel`: `whatsapp` \| `sms`. رد: `channel_used`, `resend_after`.

### 5.6 المرجعيات العامة ✅ (كان ⛔ في JSON القديم)

`GET /api/v1/public/refs?since=`

بلا Bearer. استدعِه على شاشة التسجيل وقبل إضافة محل/منطقة. كاشّه في GetStorage. أعده بـ `since=<data.sync_cursor>` لاحقاً.

```json
{
  "data": {
    "governorates": [
      { "id": 1, "name": "دمشق", "order": 1, "status": "active" },
      { "id": 2, "name": "ريف دمشق", "order": 2, "status": "active" }
    ],
    "zones": [
      { "id": 12, "name": "المزة", "governorate_id": 1, "district": "المزة", "order": 1, "status": "active" },
      { "id": 13, "name": "المالكي", "governorate_id": 1, "district": "المالكي", "order": 2, "status": "active" },
      { "id": 21, "name": "جرمانا", "governorate_id": 2, "district": "جرمانا", "order": 1, "status": "active" }
    ],
    "activity_types": [
      { "id": 3, "name": "بقالة", "icon": "grocery", "order": 1, "status": "active", "suggested_category_ids": [10] },
      { "id": 2, "name": "سوبر ماركت", "icon": "cart", "order": 2, "status": "active", "suggested_category_ids": [10] }
    ],
    "root_categories": [{ "id": 10, "name": "مواد غذائية", "icon": null, "image": null, "order": 1, "status": "active" }],
    "sale_units": [{ "id": 3, "name": "قطعة", "abbr": "pcs", "default_factor": 1, "status": "active" }],
    "equipments": [{ "id": 1, "name": "ثلاجة عرض", "icon": null, "order": 1, "status": "active" }],
    "sync_cursor": "c_20260919100000"
  },
  "meta": { "sync_cursor": "c_20260919100000", "server_time": "..." }
}
```

**القنوات ليست هنا** (قاعدة إخفاء الشركة حتى تأكيد الطلب). لا تختلق `GET /public/channels`.

**قوائم منسدلة في Flutter — من هذه المصفوفات المسطّحة:**

| الحقل في الواجهة | المصدر | نوع الاختيار | ما يُرسل |
|---|---|---|---|
| المحافظة | `governorates` | قائمة واحدة (فلتر فقط) | **لا شيء** — ليست في جسم التسجيل |
| المناطق | `zones` حيث `governorate_id` = المحافظة المختارة، أو الكل | **متعدد** (`MultiSelect`) · `value = id` · `label = name` | `zone_ids: [12, 13]` أعداد صحيحة، min 1 |
| نوع النشاط | `activity_types` | قائمة واحدة | `activity_type_id: 3` |

جمّع المناطق بالمحافظة على الجهاز:

```dart
Map<int, List<Map<String, dynamic>>> zonesByGovernorate(List zones) {
  final map = <int, List<Map<String, dynamic>>>{};
  for (final z in zones) {
    if (z['status'] != 'active') continue;
    map.putIfAbsent(z['governorate_id'] as int, () => []).add(z);
  }
  return map;
}
```

- لا تُعشّش الخادم المناطق داخل المحافظة — الربط بـ `governorate_id`.
- أخفِ `status != 'active'`. إن اختيرت منطقة ثم عادت `inactive` في `since` احذفها من الاختيار.
- إن 422 `zone_ids` بعد التسجيل: أزل المعرّفات المرفوضة واشرح «خارج تغطية القناة».
- إضافة منطقة لاحقاً (`POST /app/rep/zones`) ترسل `zone_id` واحداً لا مصفوفة.

منتقي قناة التوريد: موجّه المنتج يطلب قائمة منسدلة. **`GET /public/refs` لا يحتوي قنوات** (REQ-IN-06) ولا يوجد `GET /channels`. لا تختلق دليلاً.

الويدجت: `DropdownButton` بعنصر واحد أو أكثر من مصادر حقيقية فقط:

1. رابط دعوة `b2b-rep://join?channel_id=1`.
2. `--dart-define=DEFAULT_CHANNEL_ID=1` للتجربة بعد الزرع (قناة `demo-channel`).
3. آخر `supply_channel_id` نجح، محفوظ محلياً.

إن القائمة فارغة: حقل «رمز الانضمام» رقمي يُرسل كما هو. 409 `conflict` → «القناة غير متاحة». **اختيار واحد** (`supply_channel_id` int) — ليست متعددة.

### 5.7 إكمال التسجيل ✅ 🔁 → 201

تظهر فقط بتوكن قدرة `registration`. الهدف: **أقل من 60 ثانية**، بلا تعقيد ثانٍ. لا بريد إلكتروني. لا كلمة سر.

**الحقول — قوائم منسدلة حيث يوجد مصدر حي:**

| الشاشة | ويدجت | مصدر | يُرسل | اختيار |
|---|---|---|---|---|
| اسم المندوب | حقل نص | — | `name` | — |
| قناة التوريد | قائمة منسدلة **واحدة** | دعوة / define / آخر نجاح — **ليست** في refs | `supply_channel_id` | واحد |
| نوع النشاط | قائمة منسدلة **واحدة** | `refs.activity_types` · `id` / `name` | `activity_type_id` | واحد |
| المحافظة | قائمة منسدلة **واحدة** | `refs.governorates` | لا يُرسل | فلتر للمناطق فقط |
| المناطق التي يغطيها | قائمة منسدلة **متعددة** (chips) | `refs.zones` مجمّعة بـ `governorate_id` | `zone_ids: [12, 13]` | متعدد، min 1 |
| ملاحظة | حقل نص اختياري | — | `note` | — |

لا `CheckboxList` لكل سوريا. القيمة دائماً `int` id لا الاسم. زر **«إنهاء التسجيل والدخول»** يستدعي المسار ثم يستبدل التوكن ويدخل الرئيسية.

`POST /api/v1/app/rep/register`

```json
{
  "name": "أحمد العلي",
  "supply_channel_id": 1,
  "activity_type_id": 3,
  "zone_ids": [12, 13],
  "note": "خبرة سنتين في المزة"
}
```

| الحقل | إلزامي | قواعد |
|---|---|---|
| `name` | ✔ | max 120 |
| `supply_channel_id` | ✔ | قناة نشطة وإلا 409 |
| `activity_type_id` | ✔ | من refs وإلا 422 |
| `zone_ids` | ✔ | min 1، كلها ضمن تغطية القناة |
| `note` | ○ | max 500 |

رد:

```json
{
  "data": {
    "rep": {
      "id": 70,
      "name": "أحمد العلي",
      "channel": { "id": 1, "name": "شركة النور" },
      "zones": [{ "id": 12, "name": null }],
      "status": "pending_review"
    },
    "token": "50|rep_xxxxx"
  }
}
```

🟡 **استبدل التوكن المخزَّن فوراً.** توكن التسجيل يفشل على `/app/rep/*`. `rep.id` = معرّف مستخدم التطبيق. `zones[].name` غالباً **null** — اربط الاسم من كاش refs. احفظ `zone_ids` محلياً — لا `GET /app/rep/zones` لاحقاً.

ثم `GET /app/session`.

### 5.8 الجلسة ✅ — عند كل فتح بارد

`GET /api/v1/app/session`

مندوب:

```json
{
  "data": {
    "user": {
      "id": 70,
      "name": "عمر الشامي",
      "user_type": "rep",
      "profile_completed": true,
      "avatar": null
    },
    "permissions": [
      "rp.delivery.accept", "rp.delivery.deliver", "rp.delivery.postpone",
      "rp.delivery.return_request", "rp.payment.collect", "rp.payment.withdraw",
      "rp.wallet.view", "rp.warehouse.receive"
    ],
    "feature_flags": { "offline_orders": false, "loyalty": false },
    "sync_cursor": "",
    "server_time": "2026-09-19T13:00:00+03:00",
    "requires_legal_accept": false,
    "legal": { "privacy_version": "2026-03", "terms_version": "2026-01" },
    "commercial_limits": {
      "max_discount_percent": 10,
      "max_cash_hold": 5000000
    },
    "duty": { "on_duty": true, "tracking_enabled": true }
  }
}
```

| حقل | استعمال |
|---|---|
| `commercial_limits.max_discount_percent` | شريط الخصم في السلة. **0 = أخفِ الحقل** |
| `max_cash_hold` | سقف التحصيل. **0 = لا سقف** (أخفِ التحذير) |
| `duty.on_duty` | مفتاح داخل/خارج الخدمة في رأس الرئيسية |
| `user.avatar` | دائماً `null` — حرف من الاسم |
| `feature_flags.offline_orders` | اليوم `false` — الطابور المحلي إعادة طلبات حيّة لا sync API |
| `feature_flags.loyalty` | إن false أخفِ شريط النقاط |
| `sync_cursor` | سلسلة فارغة — لا شريط مزامنة خادم |
| `permissions` | لا تخفِ شاشات |

🟡 الجلسة **لا تُرجع المناطق**. الاسم من هنا. الصورة حرف. المناطق من التسجيل/refs.

### 5.9 المناوبة ✅ 🔁

`PATCH /api/v1/app/rep/status`

```json
{ "on_duty": true }
```

```json
{ "data": { "on_duty": true, "tracking_enabled": true } }
```

مفتاح في الـ AppBar. `--success` إن في الخدمة. `on_duty=false` يوقف النبضات (وإلا 422). لا تبدأ مسار التسليم الميداني قبل `true`.

### 5.10 الخروج ✅ 🔁

`POST /api/v1/app/auth/logout` جسم `{}` → `{ "success": true }`. امسح SecureStorage + GetStorage. إن فشلت الشبكة امسح محلياً أيضاً.

### 5.11 الصحة ✅

`GET /api/v1/health` بلا مصادقة.

```json
{
  "data": {
    "status": "ok",
    "app": "...",
    "env": "local",
    "checks": { "database": "ok", "cache": "ok", "queue": "ok" }
  }
}
```

`status` قد يكون `degraded` مع 200. لا تبنِ شاشة؛ فشل النقل: «تعذّر الوصول للخدمة» + إعادة.

---

## 6. الرئيسية (موجّه المنتج)

الهدف: الشاشة التي يفتحها المندوب كل صباح. تعطي كل ما يحتاجه: مهامه، جولته، وتحصيلاته (طلبات التجار النقدية وتسجيلها وتسليمها).

**مسار التطبيق:** `GET /api/v1/app/rep/home` — **EP-RP-002 · حي.** توكن `app.kind:rep`. بلا مفتاح تكرار. **لا** تستدعِ `GET /deliveries` من هنا (ذلك المسار يخلق صفوف تسليم).

زائر: لا تستدعِ هذا المسار. اعرض الهيكل بعلامات صفر وقفل §5.2.

```json
{
  "greeting": { "name": "عمر الشامي", "avatar": null },
  "server_time": "2026-09-19T09:12:44+03:00",
  "on_duty": true,
  "tracking_enabled": true,
  "tasks": {
    "orders_today": 0,
    "deliveries_pending": 3,
    "collected_today": 48000,
    "assignments": 2,
    "scheduled": 1,
    "warehouse_receipts": 2
  },
  "loyalty": null,
  "unread_notifications": 0
}
```

`collected_today` عدد صحيح بأصغر وحدة (ل.س). `loyalty: null` → أخفِ شريط النقاط. `unread_notifications` اليوم 0 حتى صندوق الإشعارات (AP-02).

اسحب-للتحديث يعيد `GET /home` فقط.

فارغ: «لا إسنادات ولا عهدة. ابدأ بزيارة محل.»

### 6.1 الرأس (Header)

| عنصر المنتج | المصدر |
|---|---|
| أهلاً بك + اسم + صورة البروفايل | `greeting.name` — `avatar` دائماً `null`: حرف من الاسم بجانب الترحيب |
| اليوم والتاريخ | `server_time` أو ساعة الجهاز إن انقطع |
| زر الإشعارات | الشارة = `unread_notifications` (0). الشاشة: ⛔ صندوق 404 فارغ صادق |
| حالة الاتصال | §4.5 أخضر متصل / رمادي غير متصل / أصفر مزامنة معلّقة |
| مزامنة يدوية | يظهر فقط إن outbox > 0 — يفرّغ الطابور الحيّ. لا `/app/sync/*` |
| داخل/خارج الخدمة | مفتاح من `on_duty`. الكتابة: `PATCH /app/rep/status` `{on_duty}`. جاهز للعمل أو خارج الخدمة |
| شريط النقاط والجوائز | ⛔ `loyalty == null` — أخفِ الشريط. لا `GET /app/loyalty` |

### 6.2 بلوك المهام — ستة أزرار

كل زر: أيقونة + كتابة + رقم من `data.tasks`. لا تُختلق الأرقام. زائر: أرقام 0 والقفل عند النقر.

| الزر | العدّاد | النقر |
|---|---|---|
| تسجيل طلب | `tasks.orders_today` | `OrderCaptureView` |
| تسليم طلبات | `tasks.deliveries_pending` (ينقص بعد كل تسليم عند إعادة الجلب) | `DeliveriesView` |
| استلام دفعة | `tasks.collected_today` (مال) | `CollectPaymentView` |
| قبول الطلبات | `tasks.assignments` | `AssignmentsView` |
| طلبات مجدولة | `tasks.scheduled` | `ScheduledOrdersView` |
| استلام مستودع | `tasks.warehouse_receipts` | `WarehouseReceiptsView` |

شاشات التفاصيل ما زالت تستدعي مساراتها (`GET /assignments`، `GET /deliveries` عند فتح التسليم فقط، …).

### 6.3 ثلاث دوائر — صورة مخصّصة + اسم

| الدائرة | النقر |
|---|---|
| المنتجات | `ProductsView` |
| العملاء | `CustomersView` |
| المناطق | `ZonesView` |

زائر: نفس رسالة القفل. لا بنرات ولا سلايدر من الخادم حتى `AP-04` (`home-blocks`).

### 6.4 الشريط السفلي

§3.5: السلة — الطلبات — الرئيسية.

---

## 7. تسجيل طلب مبيعات

أهم وظيفة. مسار المنتج:

1. اختيار المنطقة  
2. اختيار اسم المحل  
3. بحث منتجات المندوب  
4. شريط فئات إن وُجد  
5. سلايدر الأكثر طلباً  
6. سلايدر العروض  
7. كل المنتجات  
8. متغيرات كالـ AliExpress/Alibaba: كل متغير له عدّاد فوقه، إضافة مستمرة للسلة  
9. شريط عائم «قائمة المنتجات والعروض المختارة» → مراجعة باسم المحل قبل الإرسال  

### 7.1 المنطقة والمحل

المناطق: المحفوظة من التسجيل. المحلات: `GET /api/v1/app/rep/zones/{id}/shops?search=&page=1&per_page=25`

🟡 `search` **علوي** لا `filter[search]`. النتيجة صفحات.

```json
{
  "data": [{
    "id": 481,
    "shop_name": "بقالية النور",
    "address": "المزة فيلات شرقية",
    "is_open": true,
    "is_active": true,
    "last_order_at": null
  }],
  "meta": { "page": 1, "per_page": 25, "total": 1, "last_page": 1 }
}
```

🟡 `is_open` **دائماً true** — لا شارة إغلاق صادقة. `last_order_at` **دائماً null** — أخفِ الصف. `id` = `retailer_id` في السلة والدفع.

403 `insufficient_permission`: «ليست ضمن تغطيتك».

بديل: ابدأ من `GET /app/rep/customers` ثم ثبّت `retailer_id` + `zone_id`.

### 7.2 المنتجات ✅

`GET /api/v1/app/rep/products`

| استعلام | مثال | ملاحظة |
|---|---|---|
| `page` `per_page` | 1 / 25 | سقف 100 |
| `filter[search]` | زيت | اسم عربي أو SKU — **ليس** `search` العلوي |
| `filter[category_id]` | | |
| `filter[brand_id]` | | |
| `filter[channel_id]` | | قناة المندوب |
| `barcode` | | **علوي** — بعد مسح QR |
| `zone` | 12 | تسعير الكمية 1 **لمنطقة المحل**. لا `filter[zone_id]` |

⛔ لا ترسل `sort` ولا `filter[offer_only]` ولا `filter[available_only]`.

بطاقة كما يعيدها الخادم — **بلا صورة ولا SKU ولا متغيرات**:

```json
{
  "id": 880,
  "name": "زيت دوار الشمس 1 لتر",
  "channel": { "id": 1, "name": "شركة النور" },
  "price": { "type": "tiered", "value": 12000, "label": "السعر حسب الكمية" },
  "availability": "in_stock"
}
```

اعرض `availability` نصاً. Placeholder للصورة (حرف الاسم).

⛔ لا `GET /app/rep/products/{id}`. لا تستدعِ `GET /app/retailer/products/{id}` (حارس تاجر → 403).

شريط الفئات: ⛔ لا شجرة فئات للمندوب. أخفِ الشريط أو ابنِه من `root_categories` في refs للفلترة اليدوية بـ `filter[category_id]` إن عُرف المعرّف.

الأكثر طلباً / منتجات جديدة / سلايدرات القناة: ⛔ `home-blocks`. أخفِ السلايدر أو كرّر أول صفحة منتجات بعنوان «المنتجات» فقط — لا تختلق «الأكثر مبيعاً».

بحث صوتي: `speech_to_text` محلي → يملأ `filter[search]`. QR: `mobile_scanner` → `barcode=`.

### 7.3 المتغيرات (علي بابا) 🧩

موجّه المنتج يريد عدّاداً فوق كل متغير وإضافة مستمرة.

الخادم يقبل `variant_id` على `POST /cart/lines` و`POST /pricing/quote`، لكن قائمة المنتجات **لا تُرجع المتغيرات**.

UI:

- بطاقة منتج بكمية +/- وزر إضافة → `variant_id` يُحذف (null).
- إن ظهرت متغيرات لاحقاً: `Get.bottomSheet` شبكة متغيرات، كل خلية `RxInt qty`، الزر العائم يجمع الإضافات.
- لا تخترع قائمة متغيرات.

### 7.4 تسعير الخادم ✅ 🔁 قبل تثبيت الكمية

`POST /api/v1/app/pricing/quote`

```json
{
  "lines": [{ "product_id": 880, "variant_id": 1, "qty": 6 }],
  "zone_id": 12
}
```

`zone_id` = **منطقة المحل** لا منطقة المندوب الافتراضية إن اختلفا. **لا ترسل** `unit_price`.

```json
{
  "data": {
    "lines": [{
      "product_id": 880,
      "unit_price": 11500,
      "applied_rule": { "type": "qty_tier", "id": 3, "label": "شريحة 5–9" },
      "tier": { "from": 5, "to": 9 },
      "discount": 0,
      "line_total": 69000
    }],
    "subtotal": 69000,
    "currency": "SYP"
  }
}
```

### 7.5 العروض ✅

`GET /api/v1/app/offers?filter[zone_id]=12&filter[activity_type_id]=3` — صفحات.

`company` دائماً `null` حتى تأكيد طلب. أضف للسلة بـ `product_id` من `components` بعد quote.

`GET /app/offers/{id}` للتفاصيل. قائمة فارغة = لا عروض مطابقة — هذا صحيح.

### 7.6 إضافة للسلة ✅ 🔁

`POST /api/v1/app/rep/cart/lines`

```json
{
  "retailer_id": 481,
  "product_id": 880,
  "variant_id": 1,
  "qty": 4
}
```

`variant_id` اختياري. `qty` min 1.

🟡 الكمية **تُضاف** إلى سطر موجود لنفس المنتج/المتغير. لا PATCH ولا DELETE. إن أخطأ المندوب: «لا يمكن الإنقاص من الخادم. أرسل الطلب أو تواصل مع القناة.» لا حذف محلي يضلّل.

الرد = شكل السلة كاملاً (§7.7).

### 7.7 قراءة السلة ✅

`GET /api/v1/app/rep/cart`

```json
{
  "data": {
    "sections": [{
      "retailer": { "id": 481, "shop_name": "بقالية النور" },
      "lines": [{
        "id": 11,
        "product_id": 880,
        "qty": 4,
        "unit_price": 12000
      }],
      "total": 48000,
      "discount": 0
    }]
  }
}
```

🟡 البنود **بلا اسم ولا صورة ولا line_total**. اربط الاسم من كاش المنتجات. الشريط العائم يجمع `sections` حيث `retailer.id ==` المحل الحالي. تبويب السلة يعرض **كل** المحلات.

### 7.8 إرسال طلب محل ✅ 🔁

`POST /api/v1/app/rep/cart/sections/{retailer_id}/submit`

```json
{ "note": "توصيل صباحي", "discount_percent": 2 }
```

`discount_percent` 0–100. إن &gt; سقف الجلسة → 403 `discount_cap_exceeded`. سقف 0 → أخفِ الحقل. أي خصم &gt; 0 يفشل.

```json
{
  "data": {
    "sub_order": {
      "id": 9001,
      "sub_order_no": "SO-9001",
      "status": "pending",
      "total": 47040
    }
  }
}
```

الخادم يحذف قسم هذا المحل. احفظ `sub_order` في كاش «طلباتي» المحلي (لا `GET /app/rep/orders`). 422 قسم فارغ. 404 محل مجهول.

---

## 8. القبول · المستودع · التسليم · المجدولة · المرتجع

### 8.1 قبول الطلبات ✅

`GET /api/v1/app/rep/assignments` — مصفوفة بلا صفحات. `invoice_no` دائماً null.

```json
{
  "data": [{
    "id": 9001,
    "sub_order_no": "SO-9001",
    "shop": "بقالية النور",
    "zone": "المزة",
    "channel": "شركة النور",
    "invoice_no": null,
    "created_at": "2026-03-01T10:00:00+03:00"
  }]
}
```

تجميع الواجهة حسب `zone`. `id` = `sub_order_id`.

قبول 🔁: `POST /assignments/{id}/accept` جسم `{}` → `{ "status": "accepted" }`.

رفض 🔁: `POST /assignments/{id}/reject` `{ "reason": "خارج مساري اليوم" }` max 255 → `{ "status": "unassigned" }`.

409 إن غادر `assigned`. 404 إن ليس لك.

### 8.2 استلام مستودع ✅ — بوابة التسليم

بلا تأكيد عهدة، `complete` يرجع 409 `illegal_transition`.

`GET /api/v1/app/rep/warehouse-receipts?date=2026-03-01`

```json
{
  "data": {
    "date": "2026-03-01",
    "rep_name": "عمر الشامي",
    "count": 2,
    "orders": [{
      "sub_order_id": 9001,
      "order_no": "SO-9001",
      "shop": "بقالية النور",
      "zone": "المزة",
      "handover_id": 44
    }]
  }
}
```

`count` = عدد بنود الطلبات لا عدد الحوالات. تجميع حسب `handover_id`. حقل رمز **4 خانات** + تأكيد.

`POST /app/rep/warehouse-receipts/{handoverId}/confirm` 🔁

```json
{ "temp_code": "7391" }
```

`temp_code` size 4. نجاح: `{ "status": "on_the_way", "tracking_enabled": true }` — ابدأ النبضات إن المناوبة شغّالة.

| خطأ | |
|---|---|
| 422 | رمز خاطئ |
| 409 `illegal_transition` | 🟡 حوالة مجهولة أو ليست لك — **ليس 404** |
| إعادة تأكيد | حوالة مؤكَّدة تعيد نفس النجاح حتى لو الرمز خاطئ |

### 8.3 قائمة التسليم ✅

`GET /api/v1/app/rep/deliveries?filter[zone_id]=12`

🟡 **أثر كتابة.** اسحب-للتحديث فقط.

```json
{
  "data": {
    "zones": [{
      "name": "المزة",
      "total": 4,
      "delivered": 1,
      "cards": [{
        "id": 9001,
        "shop": "بقالية النور",
        "zone": "المزة",
        "channel": "شركة النور",
        "invoice_no": "INV-501",
        "ordered_at": "2026-03-01T09:10:00+03:00",
        "status": "accepted",
        "border_color": "blue"
      }]
    }]
  }
}
```

`status`: `accepted` (أزرق) · `on_the_way` (أخضر) · `delivered` اليوم (رمادي). المسلَّم في أيام سابقة **لا يظهر**.

موجّه المنتج يريد أيضاً إلغاء (أحمر) وتأجيل (أزرق إطار). بعد `fail`/`postpone` البطاقة تغادر هذه القائمة إلى المجدولة أو تختفي — حدّث من الخادم ولا تلوّن محلياً ضد `border_color`.

### 8.4 تفاصيل التسليم ✅

`GET /api/v1/app/rep/deliveries/{id}` — `{id}` = `sub_order_id`.

```json
{
  "data": {
    "lines": [{
      "id": 1,
      "image": null,
      "name": "زيت دوار الشمس 1 لتر",
      "brand": "نور",
      "variant": null,
      "qty": 4,
      "qty_delivered": 0,
      "price": 12000,
      "status": "pending"
    }],
    "invoice_total": 48000
  }
}
```

`lines[].id` = بند **التسليم** لـ PATCH وcomplete. `qty` متوقع. `status` ثم `accept`/`adjust`/`return`/`exchange`.

أزرار المنتج على البطاقة:

| زر المنتج | مسار |
|---|---|
| تعديل كمية | `PATCH .../lines/{lineId}` `action=adjust` |
| إرجاع | `action=return` + `POST /return-requests` نوع `return` |
| استبدال | `action=exchange` + `POST /return-requests` نوع `exchange` |
| تسليم / تم الاستلام | `POST .../complete` |
| استلام دفعة | يظهر بعد complete إن `ask_payment` |

تعديل بند 🔁:

```json
{ "qty_delivered": 3, "action": "return", "reason": "رفض التاجر عبوة" }
```

`action` ✔: `accept` \| `adjust` \| `return` \| `exchange`. رد: `{ "new_invoice_total": 36000 }`.

إن الإرجاع/الاستبدال يحتاج قرار القناة: أنشئ `return-requests` واعرض «بانتظار القناة». لا تغيّر لون السعر محلياً إلا بعد رد الخادم.

### 8.5 إنهاء التسليم ✅ 🔁

`POST /app/rep/deliveries/{id}/complete`

```json
{
  "lines": [{ "line_id": 1, "qty_delivered": 4, "action": "accept" }],
  "delivered_at": "2026-03-01T12:05:00+03:00",
  "signature": "data:image/png;base64,..."
}
```

كل الحقول اختيارية ما عدا أن الإنهاء بلا عهدة → 409.

```json
{
  "data": {
    "invoice": { "no": "INV-501", "total": 48000 },
    "receipt_no": "RCPT-10041",
    "ask_payment": true
  }
}
```

إن `ask_payment` افتح التحصيل فوراً بـ `invoice.no` + `receipt_no` + `total` مقترحاً. **لا تحجز وصلاً ثانياً** — الوصل موجود.

إعادة الإنهاء بعد التسليم تعيد نفس الفاتورة والوصل.

### 8.6 تأجيل ✅ 🔁

`POST .../postpone`

```json
{ "scheduled_at": "2026-03-02T10:00:00+03:00", "reason": "المحل مغلق" }
```

كلاهما إلزامي. → `{ "status": "postponed" }`. يظهر في المجدولة.

### 8.7 تعذّر / إلغاء تسليم ✅ 🔁

`POST .../fail` `{ "reason": "رفض الاستلام" }` → `{ "status": "undelivered", "border_color": "red" }`.

تأكيد مزدوج: «لا يمكن التراجع من التطبيق.»

### 8.8 نبضات الموقع ✅ 🔁

`POST /api/v1/app/rep/locations/ping`

```json
{
  "pings": [{
    "lat": 33.5112,
    "lng": 36.2781,
    "at": "2026-03-01T11:41:00+03:00",
    "accuracy": 12
  }]
}
```

في الخدمة فقط وإلا 422. أقل من 30ث → 429. دفعة واحدة لكل نداء. مفتاح تكرار **جديد** لكل دفعة. رد: `{ "accepted": 1 }`. شغّل مؤقّتاً من `ShellController` طالما `on_duty && tracking_enabled`.

### 8.9 المجدولة ✅

`GET /api/v1/app/rep/scheduled-orders?date=2026-03-02` — بلا `date` = كل المؤجَّل.

```json
{
  "data": [{
    "id": 9001,
    "shop_logo": null,
    "shop": "بقالية النور",
    "address": "المزة فيلات شرقية",
    "phone": "+963933000000",
    "scheduled_at": "2026-03-02T10:00:00+03:00",
    "status": "postponed",
    "color": "amber"
  }]
}
```

`id` يفتح تفاصيل التسليم. `shop_logo` دائماً null. ألوان الأيقونة حسب المنتج: أخضر مسلّم / رمادي غير مسلّم / أزرق مؤجّل / أحمر ملغى — نفّذها من `status` بعد إجراء المستخدم عبر fail/postpone/complete لا من حقل غير موجود.

موجّه المنتج يريد تغيير الحالة من هذه الشاشة (تم التسليم، لم يتم + سبب، مؤجّلة + موعد جديد، ملغى + سبب). نفّذها باستدعاء مسارات التسليم على `id` ثم احذف البطاقة بعد نجاح يزيل `postponed`.

### 8.10 مرتجع ميداني ✅ 🔁

`POST /api/v1/app/rep/return-requests`

```json
{
  "sub_order_id": 9001,
  "type": "exchange",
  "lines": [{
    "line_id": 1,
    "qty": 1,
    "reason": "خطأ صنف",
    "photos": []
  }]
}
```

`type`: `return` \| `exchange`. `photos` أرسل `[]` — ⛔ لا رفع وسائط.

```json
{ "data": { "request_no": "RR-201", "status": "pending" } }
```

⛔ لا قائمة لاحقة. اعرض الرقم واحفظه محلياً. 404 إن الطلب ليس لهذا المندوب.

---

## 9. التحصيل والمحفظة

### 9.1 حجز وصل ✅ 🔁

`POST /api/v1/app/receipts/reserve` جسم `{}` → `{ "receipt_no": "RCPT-10041" }`. صلاحية 24 ساعة.

بعد `complete` الوصل موجود — لا تحجز إلا لتحصيل بلا إنهاء. المستخدم **لا يكتب** رقم الوصل إلا للصق.

موجّه المنتج: «رقم وصل الاستلام مرتبط بتطبيق المحل». على الخادم الرقم **يولَّد هنا** ثم يُستخدم في الدفعة. اعرضه للطباعة/المشاركة.

### 9.2 تحصيل دفعة ✅ 🔁

موجّه المنتج يجعل `invoice_no` اختيارياً (دفعة على الحساب). **الخادم يفرضه.** لا دفعة على الحساب بلا رقم فاتورة. إن لم يختر فاتورة: افتح الذمم أو امنع الإرسال.

`POST /api/v1/app/rep/payments`

```json
{
  "receipt_no": "RCPT-10041",
  "retailer_id": 481,
  "invoice_no": "INV-501",
  "amount": 48000,
  "paid_at": "2026-03-01T12:08:00+03:00",
  "client_op_id": "op_rep_pay_10041"
}
```

`amount` int ≥ 1. `client_op_id` max 80. إعادة بنفس القيمة = نفس الدفعة.

```json
{
  "data": {
    "payment": { "id": 301 },
    "wallet_balance": 210000,
    "retailer_receivable": 0
  }
}
```

| خطأ | |
|---|---|
| 409 `duplicate_receipt_no` | الوصل مستخدم |
| 422 `receipt_not_reserved` / `receipt_expired` | احجز وصلاً |
| 403 `cash_cap_exceeded` | اعرض سقف الجلسة |
| 404 | فاتورة/محل ليس لك |

المبلغ الزائد يُوزَّع FIFO على فواتير المحل — أظهر `retailer_receivable`.

شاشة المنتج: اسم المندوب + تاريخ (من الجلسة/الساعة، للعرض)، اختيار محل، اختيار فاتورة، مبلغ، تأكيد → رسالة + تحديث المحفظة.

### 9.3 لوحة المحفظة ✅

`GET /api/v1/app/rep/wallet`

```json
{
  "data": {
    "net_balance": 210000,
    "stats": {
      "invoices_delivered": 18,
      "collected_total": 860000,
      "receivables": 120000
    },
    "today": {
      "invoices": 4,
      "collected": 180000,
      "receivables": 30000
    }
  }
}
```

`net_balance = SUM(collected) − SUM(settled)`. لا تختلق فرقاً محلياً.

تخطيط المنتج:

1. بلوك الصافي (ما لم يُسلَّم للمحاسب) = `net_balance`
2. بلوك إحصائي: فواتير مسلَّمة + محصّلة + ذمة — النقر يفتح التفصيل (الذمم / السجل)
3. مستحقات اليوم مع منتقي تاريخ — 🟡 الخادم يعيد `today` ليوم دمشق فقط. منتقي تاريخ آخر: اعرض «اليوم فقط من الخادم» أو صفّر إن التاريخ ≠ اليوم
4. سحب مبلغ
5. كشف سحوبات بين تاريخين + PDF
6. إجمالي ديون غير محصّلة + PDF حسب محل

### 9.4 سحب / تسليم للمحاسب ✅ 🔁

`POST /api/v1/app/rep/wallet/withdrawals`

```json
{
  "amount": 1500000,
  "operation_no": "OP-7781",
  "operated_at": "2026-03-01T16:00:00+03:00"
}
```

`operation_no` **من المحاسب** max 32، فريد على القناة. امنع محلياً إن `amount > net_balance`.

```json
{ "data": { "remaining_balance": 250000 } }
```

### 9.5 سجل السحوبات ✅

`GET /app/rep/wallet/withdrawals?date_from=2026-02-01&date_to=2026-02-28`

```json
{
  "data": {
    "rows": [{ "operation_no": "OP-7700", "amount": 900000, "operated_at": "2026-02-20" }],
    "total": 900000
  }
}
```

`operated_at` تاريخ يوم دمشق. صدّر PDF محلياً (`pdf` + `share_plus`): الأعمدة + الإجمالي. ⛔ لا مسار تصدير خادم للمندوب.

### 9.6 الذمم ✅

`GET /api/v1/app/rep/receivables`

```json
{
  "data": {
    "by_shop": [{
      "retailer_id": 481,
      "shop": "بقالية النور",
      "total": 48000,
      "invoices": [{ "no": "INV-501", "total": 48000, "paid": 0, "remaining": 48000 }]
    }]
  }
}
```

✅ `retailer_id` موجود (تصحيح لمواصفة 09-16). النقر على فاتورة → تحصيل بـ `remaining` مقترحاً.

---

## 10. العملاء · المناطق · الطلبات · الحساب · الإشعارات

### 10.1 العملاء ✅

`GET /api/v1/app/rep/customers?filter[search]=النور&page=1&per_page=25`

🟡 البحث `filter[search]` على `shop_name` فقط.

```json
{
  "data": [{
    "id": 481,
    "shop_name": "بقالية النور",
    "zone_id": 12,
    "is_active": true
  }]
}
```

القائمة تضم: محلات سجّلها المندوب **أو** تجاراً نشطين في مناطقه. `id` = `retailer_id`. `is_active=false` قيد المراجعة — اعرض؛ السلة قد تفشل لاحقاً.

موجّه المنتج يريد على البطاقة: لوغو، منطقة، اتصال، خريطة، فاتح/مغلق، مفعّل. الحيّ يعطي الاسم + `zone_id` + `is_active` فقط. من `GET zones/{id}/shops` أضف `address`. اخفِ اللوغو/المغلق/آخر طلب. زر اتصال يظهر إن حصلت على الهاتف من شاشة أخرى (مجدولة) وإلا أخفه. خريطة: إن `lat/lng` غير موجودين في القائمة لا تعرض دبوساً.

تفاصيل العميل: ⛔ لا `GET /customers/{id}`. ابنِ صفحة من كائن القائمة + عنوان المحلات إن وُجد.

فلاتر متقدمة (الأكثر شراء، جديد): نفّذ ما يوجد (`search` + منطقة محلية). الباقي 🧩.

### 10.2 إضافة محل ✅ 🔁

موجّه المنتج يطلب أيضاً: فئات متعددة، تجهيزات، عنوان تفصيلي. **الخادم لا يستقبلها.** أرسل العقد الحي فقط. لا تضع الفئات في JSON إضافي يُرفض بـ 422.

`POST /api/v1/app/rep/customers`

```json
{
  "shop_name": "ميني ماركت الشام",
  "owner_name": "أبو سامر",
  "phone": "+963988000000",
  "zone_id": 12,
  "activity_type_id": 3,
  "lat": 33.51,
  "lng": 36.27,
  "client_op_id": "op_shop_local_1"
}
```

`lat` `lng` من GPS اختياريان. `client_op_id` ✔ ولّده قبل الإرسال.

قوائم منسدلة من كاش refs (نفس §5.6، اختيار **واحد** هنا لا متعدد):

| الحقل | ويدجت | يُرسل |
|---|---|---|
| المنطقة | قائمة واحدة من `zones` (فلتر محافظة اختياري) | `zone_id: 12` |
| نوع النشاط | قائمة واحدة من `activity_types` | `activity_type_id: 3` |

الفئات/التجهيزات من refs للعرض فقط حتى يتوسع العقد. خريطة اختيار الموقع تكتب `lat`/`lng`.

```json
{ "data": { "id": 490, "status": "pending_sync" } }
```

🟡 `id` العائد = صف `RepSourcedShop` **لا** تستخدمه كـ `retailer_id`. أعد `GET /customers` وخذ `id` من القائمة. لا تُرسل فئات أو تجهيزات.

### 10.3 المناطق 🟡

لا `GET /app/rep/zones`. القائمة = `zone_ids` المحفوظة + أسماء refs. كل بطاقة: اسم، محافظة من `governorate_id`، وعدّاد محلات عبر `GET /zones/{id}/shops` (`meta.total`).

النقر → نفس بطاقات العملاء داخل المنطقة.

إضافة منطقة ✅ 🔁: `POST /api/v1/app/rep/zones`

```json
{ "zone_id": 14, "note": "طلب تغطية كفرسوسة" }
```

→ `{ "status": "pending_approval" }`. لا قائمة طلبات لاحقة. توست «طلبك قيد الموافقة».

منتقي الإضافة = نفس قوائم §5.6 لكن **منطقة واحدة**: محافظة (فلتر) ثم `zones` التي ليست في `zone_ids` المحفوظة. يُرسل `{ "zone_id": 14, "note": "…" }`. 422 خارج التغطية.

### 10.4 تبويب الطلبات 🧩

موجّه المنتج: بطاقات أفقية للطلبات المؤكدة والمرسَلة: رقم، تاريخ، حالة، محل، منطقة، مندوب، قناة، تفاصيل.

⛔ لا `GET /app/rep/orders`. ابنِ القائمة من:

1. كاش محلي لكل `submit` ناجح  
2. `assignments` (معلق قبول)  
3. `deliveries` (مقبول / في الطريق / مسلّم اليوم)  
4. `scheduled-orders` (مؤجّل)  

اربط الحالات:

| المنتج | مصدر تقريبي |
|---|---|
| معلّق | `submit` → `pending` محلي |
| تمت الموافقة / قيد التجهيز | لا عدّاد منفصل — لا تستدعِ قناة |
| جاهز للاستلام | `warehouse-receipts` |
| تم التسليم | `deliveries` `delivered` |
| مؤجّلة | `postponed` |
| ملغاة | بعد `fail` |

تفاصيل البطاقة → `DeliveryDetailView` إن وُجد `sub_order_id` في التسليم، وإلا عرض الكاش فقط.

### 10.5 الحساب

| عنصر المنتج | مصدر |
|---|---|
| اسم + أيقونة | جلسة — لا صورة |
| إحصاءات الشهر: طلبات منفّذة، محلات جدد، نسبة تنفيذ، نقاط | ⛔ لا مسار إحصاء مندوب. أخفِ البطاقة أو اعرض أرقام المحفظة `stats` كبديل صادق بعنوان «إجمالي غير شهري» |
| لغة / مظهر / عملة | محلي GetStorage. العملة `SYP` ثابتة |
| إشعارات تفعيل | محلي حتى FCM ⛔ |
| مساعدة وخصوصية | `legal.*` نسخ ثابتة في الأصول. ⛔ لا API قبول |
| تعديل هاتف/صورة | ⛔ لا مسار. أخفِ أو «تواصل مع القناة» |
| تعديل مناطق العمل | `POST /zones` فقط (طلب إضافي) |
| خروج | §5.10 |

### 10.6 الإشعارات ⛔ `AP-02`

ابنِ الشاشة: قائمة، مقروء/غير، تحديد الكل، مسح، تعليم كمقروء.

`NotificationsRemote` يرمي `RemoteNotReady`. لا جرس بعدد مختلق. أمثلة المنتج (جاهز للمستودع، تأكيد طلب، منتج غير متوفر) تُعرض كبيانات وهمية **في وضع التصميم فقط** (`kDebugMode && USE_FIXTURES`) — لا في الإنتاج.

العقد المستقبلي (لا تستدعِه):

```
GET    /app/notifications?filter[read]=0
POST   /app/notifications/read-all
DELETE /app/notifications
POST   /app/devices/push-token   { token, platform }
```

`meta.unread_count` سيأتي مع الصندوق.

### 10.7 الولاء ⛔ `AP-05`

أخفِ الشريط. عقد مستقبلي: `GET /app/loyalty` · `POST /app/loyalty/redeem { reward_id }`.

### 10.8 المزامنة ⛔ `AP-03`

لا `GET/POST /app/sync/*`. §13 للطابور المحلي.

---

## 11. كتالوج المسارات الحيّة — جدول سريع

الأساس `https://{host}/api/v1`. عمود 🔁 = مفتاح تكرار.

### 11.1 عام

| EP | طريقة | المسار | 🔁 | حالة |
|---|---|---|---|---|
| EP-CORE-001 | GET | `/health` | | ✅ |
| EP-PB-001 | GET | `/public/refs` | | ✅ |
| EP-PB-011 | GET | `/public/content/intro` | | ✅ انترو الإقلاع |
| EP-PB-010 | GET | `/public/app-config` | | ⛔ تحديث إجباري — ليس الانترو |
| EP-CM-001 | POST | `/public/auth/request-otp` | لا | ✅ |
| EP-CM-002 | POST | `/public/auth/verify-otp` | لا | ✅ |
| EP-CM-003 | POST | `/public/auth/resend-otp` | لا | ✅ |

### 11.2 مشترك تطبيق

| EP | طريقة | المسار | 🔁 | حالة |
|---|---|---|---|---|
| EP-CM-004 | GET | `/app/session` | | ✅ |
| EP-CM-005 | POST | `/app/auth/logout` | ✔ | ✅ |
| EP-APP-030 | POST | `/app/pricing/quote` | ✔ | ✅ |
| EP-APP-040 | GET | `/app/offers` | | ✅ |
| EP-APP-041 | GET | `/app/offers/{id}` | | ✅ |
| EP-CM-050 | POST | `/app/receipts/reserve` | ✔ | ✅ |
| EP-SY-001…004 | * | `/app/sync/*` | | ⛔ |
| EP-CM-060…063 | * | إشعارات + FCM | | ⛔ |
| EP-APP-100 | GET | `/app/content/home-blocks` | | ⛔ |
| EP-APP-110…111 | * | ولاء | | ⛔ |

### 11.3 مندوب — حسب `status/05-rep-app.md`

| EP | طريقة | المسار | 🔁 |
|---|---|---|---|
| EP-RP-001 | POST | `/app/rep/register` | ✔ |
| EP-RP-002 | GET | `/app/rep/home` | |
| EP-RP-034 | PATCH | `/app/rep/status` | ✔ |
| EP-RP-010 | GET | `/app/rep/products` | |
| EP-RP-070A | GET | `/app/rep/customers` | |
| EP-RP-070B | POST | `/app/rep/customers` | ✔ |
| EP-RP-071 | POST | `/app/rep/zones` | ✔ |
| EP-RP-020 | GET | `/app/rep/zones/{id}/shops` | |
| EP-RP-021 | POST | `/app/rep/cart/lines` | ✔ |
| EP-RP-022 | GET | `/app/rep/cart` | |
| EP-RP-023 | POST | `/app/rep/cart/sections/{retailer_id}/submit` | ✔ |
| EP-RP-030 | GET | `/app/rep/assignments` | |
| EP-RP-031 | POST | `/app/rep/assignments/{id}/accept` | ✔ |
| EP-RP-032 | POST | `/app/rep/assignments/{id}/reject` | ✔ |
| EP-RP-033 | GET | `/app/rep/scheduled-orders` | |
| EP-RP-040 | GET | `/app/rep/warehouse-receipts` | |
| EP-RP-041 | POST | `/app/rep/warehouse-receipts/{handoverId}/confirm` | ✔ |
| EP-RP-050 | GET | `/app/rep/deliveries` | |
| EP-RP-051 | GET | `/app/rep/deliveries/{id}` | |
| EP-RP-052 | PATCH | `/app/rep/deliveries/{id}/lines/{lineId}` | ✔ |
| EP-RP-053 | POST | `/app/rep/deliveries/{id}/complete` | ✔ |
| EP-RP-054 | POST | `/app/rep/deliveries/{id}/postpone` | ✔ |
| EP-RP-055 | POST | `/app/rep/deliveries/{id}/fail` | ✔ |
| EP-RP-056 | POST | `/app/rep/locations/ping` | ✔ |
| EP-RP-057 | POST | `/app/rep/return-requests` | ✔ |
| EP-RP-060 | POST | `/app/rep/payments` | ✔ |
| EP-RP-061 | GET | `/app/rep/wallet` | |
| EP-RP-062 | POST | `/app/rep/wallet/withdrawals` | ✔ |
| EP-RP-063 | GET | `/app/rep/wallet/withdrawals` | |
| EP-RP-064 | GET | `/app/rep/receivables` | |

### 11.4 ممنوع استدعاؤه من تطبيق المندوب

| المسار | السبب |
|---|---|
| أي `/channel/*` `/platform/*` `/warehouse/*` `/app/retailer/*` | حارس خاطئ → 403 `wrong_guard` |
| `GET/PUT /platform/content/intro` | كتابة/قراءة السنترال — التطبيق يقرأ `GET /public/content/intro` |
| `GET/PUT /channel/content/intro` | لوحة القناة فقط |
| `GET /app/rep/zones` | غير موجود |
| `PATCH/DELETE /app/rep/cart/lines/{id}` | غير موجود للمندوب (موجود للتاجر فقط) |
| `GET /app/rep/products/{id}` | غير موجود |
| `GET /app/rep/orders` | غير موجود |
| `GET /app/rep/return-requests` | غير موجود |
| `GET /app/rep/customers/{id}` | غير موجود |
| `auth:sanctum` | ليس حارساً في هذا التطبيق |

---

## 12. عميل Dio + نماذج Dart (انسخ إلى المشروع)

### 12.1 Envelope

```dart
class Envelope<T> {
  Envelope({required this.data, required this.meta});
  final T data;
  final Map<String, dynamic> meta;

  factory Envelope.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic) parse,
  ) =>
      Envelope(
        data: parse(json['data']),
        meta: Map<String, dynamic>.from(json['meta'] as Map? ?? {}),
      );
}
```

### 12.2 ApiClient (GetxService)

```dart
class ApiClient extends GetxService {
  late final Dio dio;

  Future<ApiClient> init() async {
    dio = Dio(BaseOptions(
      baseUrl: Env.baseUrl + '/api/v1',
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 20),
      headers: {
        'Accept': 'application/json',
        'Accept-Language': 'ar',
        'X-Client': Env.xClient,          // rep-android | rep-ios
        'X-App-Version': Env.appVersion,
      },
    ));
    dio.interceptors.addAll([
      DeviceInterceptor(),     // X-Device-Id
      AuthInterceptor(),       // Bearer
      IdempotencyInterceptor(), // POST/PATCH/PUT/DELETE إلا otp
      EnvelopeInterceptor(),   // 4xx/5xx → ApiException
    ]);
    return this;
  }
}
```

`IdempotencyInterceptor`: إن الكنترولر وضع `extra['idempotencyKey']` استخدمه؛ وإلا لا تولّد مفتاحاً تلقائياً لكل إعادة Dio — ذلك يكسر التكرار. الكنترولر يملك المفتاح.

### 12.3 مال

```dart
class Money {
  const Money(this.minor); // int
  final int minor;
  String get display =>
      '${NumberFormat.decimalPattern('ar').format(minor)} ل.س';
}
```

`fromJson`: `(json['value'] as num).toInt()` — لا `double.parse`.

### 12.4 أسماء النماذج ↔ JSON

| نموذج | حقول إلزامية من الخادم |
|---|---|
| `Session` | `user.id/name/user_type/profile_completed`, `permissions`, `feature_flags`, `legal`, `commercial_limits?` |
| `OtpRequestResult` | `otp_id`, `channel_used`, `expires_in`, `resend_after` |
| `VerifyResult` | `token`, `is_new_user`, `user_type`, `profile_completed`, `user` |
| `RepRegisterResult` | `rep`, `token` |
| `ProductCard` | `id`, `name`, `channel`, `price.value/type/label`, `availability` |
| `ShopCard` | `id`, `shop_name`, `address?`, `is_open`, `is_active`, `last_order_at?` |
| `CustomerCard` | `id`, `shop_name`, `zone_id`, `is_active` |
| `CartView` | `sections[].retailer.id/shop_name`, `lines[].id/product_id/qty/unit_price`, `total`, `discount` |
| `SubOrderBrief` | `id`, `sub_order_no`, `status`, `total` |
| `AssignmentCard` | `id`, `sub_order_no`, `shop`, `zone`, `channel`, `created_at` |
| `ScheduledCard` | `id`, `shop`, `address`, `phone`, `scheduled_at`, `status`, `color` |
| `WarehousePayload` | `date`, `rep_name`, `count`, `orders[].sub_order_id/order_no/shop/zone/handover_id` |
| `DeliveryList` | `zones[].name/total/delivered/cards[]` |
| `DeliveryCard` | `id`, `shop`, `zone`, `channel`, `invoice_no?`, `ordered_at`, `status`, `border_color` |
| `DeliveryDetail` | `lines[]`, `invoice_total` |
| `Wallet` | `net_balance`, `stats.*`, `today.*` |
| `Receivables` | `by_shop[].retailer_id/shop/total/invoices[]` |
| `PublicRefs` | الحاكمات، المناطق، الأنشطة، الفئات، الوحدات، التجهيزات، `sync_cursor` |
| `IntroContent` | `enabled`, `text`, `media_type`, `media_id`, `duration`, `targeting` — من `GET /public/content/intro` |

`explicitToJson` ليس ضرورياً. `fromJson` يدوي مفضّل على codegen إن اختلف الكتالوج عن الحيّ — هذا الملف يوثّق الحيّ.

---

## 13. دون اتصال — حتى يُبنى AP-03

`feature_flags.offline_orders === false` و`/app/sync/*` 404. ومع ذلك السوق ينقطع.

### 13.1 ما يُكاش فوراً بعد الدخول

`session`, `refs`, `customers` صفحة 1، `products` صفحة 1 لمنطقة افتراضية، `wallet`, `assignments`, قائمة `zone_ids`. TTL: حتى يسحب-للتحديث.

### 13.2 Outbox محلي (GetStorage قائمة JSON)

كل عملية كتابة إن `DioException.connectionError`:

```json
{
  "op_id": "uuid",
  "idempotency_key": "uuid",
  "method": "POST",
  "path": "/app/rep/payments",
  "body": {},
  "created_at": "ISO",
  "retries": 0
}
```

عند الأخضر: أعد بنفس المفتاح والجسم. نجاح 2xx → احذف. `idempotency_key_conflict` → لا تحذف حتى يراجع المندوب. الحد الأقصى 500. لا تدفع أسعاراً ولا كتالوجاً.

أظهر الشارة الصفراء وزر المزامنة اليدوية.

لا تُسجَّل في الـ outbox: OTP، session، GET.

### 13.3 GPS

طلب الإذن عند أول `on_duty=true`. إن رُفض: المناوبة تبقى والنبضات تتوقف مع تنويه غير مزعج.

---

## 14. فجوات المنتج مقابل الخادم

ابنِ عمود Flutter. لا تختلق عمود الخادم.

| حاجة المنتج | الخادم اليوم | Flutter |
|---|---|---|
| انترو من الأدمن (نص/فيديو/لوغو) | التطبيق ✅ `GET /public/content/intro` (نفس صف `PUT /platform/content/intro`) | `ContentService.intro()` — حساب سابق يتجاوز الشاشة |
| تخطّي كزائر | لا ضيف | قفل محلي على الكتابة |
| دليل القنوات عند التسجيل | ممنوع في `/public/refs` | دعوة / معرّف تجربة / قناة البذرة `1` |
| صور منتجات ومحلات | `image` null في قائمة المندوب | placeholder |
| تفاصيل منتج + متغيرات علي بابا | لا show للمندوب؛ `variant_id` مقبول في السلة | كمية على المنتج؛ sheet فارغ إن لا بيانات |
| فئات / الأكثر مبيعاً / سلايدرات | ⛔ home-blocks | أخفِ |
| تعديل/حذف بند سلة | الكمية تصعد فقط | رسالة صريحة |
| قائمة طلبات المندوب | لا GET orders | كاش submit + assignments + deliveries |
| تفاصيل عميل كاملة / هاتف / فاتح | قائمة مختزلة | أخفِ المفقود |
| إضافة محل: فئات وتجهيزات وعنوان | غير مقبولة | لا ترسلها |
| `GET /app/rep/zones` | غير موجود | كاش تسجيل + refs |
| دفعة بلا رقم فاتورة | `invoice_no` إلزامي | امنع الإرسال حتى اختيار فاتورة |
| إشعارات FCM | ⛔ AP-02 | شاشة فارغة |
| ولاء / شريط نقاط | ⛔ AP-05 | أخفِ |
| مزامنة pull/push | ⛔ AP-03 | outbox محلي |
| فرض تحديث / صيانة | ⛔ app-config | مراجعة متجر يدوية |
| إحصاءات شهر الحساب | لا مسار | أخفِ أو `wallet.stats` بوضوح أنه إجمالي |
| تعديل ملف المندوب (هاتف/صورة) | لا مسار | أخفِ |
| رفع صور مرتجع | `photos: []` فقط | لا picker |
| تصدير PDF ذمم/سحوبات | لا مسار مندوب | PDF على الجهاز |
| خريطة ETA للتسليم | لا مسار | إحداثيات المحل إن وُجدت فقط؛ لا وقت وصول مختلق |

عقود الكتالوج غير الحيّة (`notifications`, `sync`, `loyalty`, `home-blocks`, `app-config`) تُنفَّذ كـ `*Remote` جاهز بـ `fromJson` **مع** `enabled = false` حتى ينقلب `status/` إلى ✅. عندها يكفي فك الرابط لا إعادة الشاشة.

---

## 15. قائمة قبول — لـ Cursor عند وصول مستودع Flutter

اعمل الشاشات الناقصة بهذا الترتيب. لا تبدأ بموضوع ⛔. لا تضف مسارات مخترعة.

1. **العميل:** Dio + غلاف + مال int + هاتف سوري + ترويسات + تكرار على الكتابات فقط.
2. **Splash → `GET /public/content/intro` إن لا توكن → Phone → OTP `"0000"` → session.** توكن موجود يتجاوز الانترو إلى الرئيسية.
3. **Register:** `GET /public/refs` → نشاط قائمة واحدة + مناطق **متعددة** + قناة قائمة واحدة من الدعوة/define (لا دليل قنوات) + استبدال التوكن. هدف أقل من 60 ثانية. لا بريد.
4. **Shell** 5 تبويبات + مناوبة + شارة اتصال.
5. **Home** يجمع العدادات من 5 GET حيّة.
6. **Order capture:** منطقة → محل → منتجات → quote → cart lines → شريط عائم → submit.
7. **Cart tab** حسب المحل + إرسال.
8. **Assignments / Warehouse / Deliveries / Detail / complete / postpone / fail.**
9. **Payments + reserve + wallet + withdrawals + receivables.**
10. **Customers + add shop + zones shops + request zone.**
11. **Offers.**
12. **Location ping** عند on_duty.
13. **Outbox** لانقطاع الشبكة.
14. **Notifications / Loyalty / Sync remote:** UI أو إخفاء حسب الجدول، بلا استدعاء 404. الانترو **بعيد وحيّ** (`GET /public/content/intro`).
15. **زائر:** قفل الكتابة.
16. **خطأ 401** → الهاتف. `otp_*` يبقى. 404 «غير موجود». مال بلا كسور. RTL.

اربط كل `onPressed` بمسار من §11 أو بـ `RemoteNotReady`. إن وُجد في المشروع استدعاء لـ `/app/retailer/*` أو `/channel/*` احذفه.

اختبار يدوي ببذرة `0932000001` + `0000` ضد `http://127.0.0.1:8000/api/v1`.

---

## 16. رحلات QA

### 16.1 أول تشغيل لمندوب البذرة

`health` + إن لا توكن `GET /public/content/intro` → انترو إن `enabled` → هاتف `0932000001` `purpose=login` → OTP `0000` → الرئيسية (تجاوز الانترو في التشغيل التالي لأن التوكن موجود). المناوبة كما في البذرة (عمر: true).

### 16.2 رقم جديد

`purpose=register` → OTP → register (قناة 1 + قائمة نشاط واحدة + **مناطق متعددة** من refs تغطيها القناة) → استبدال توكن → session → شارة قيد المراجعة.

### 16.3 طلب محل

زبائن أو محلات المنطقة → منتجات `zone=` منطقة المحل → quote → `cart/lines` → مراجعة (اربط الأسماء) → submit خصم ≤ السقف.

### 16.4 يوم تسليم

إسناد accept → مستودع `temp_code` 4 خانات → deliveries → تفاصيل → بنود → complete → إن `ask_payment` حصّل بالوصل المولَّد → نبضات ≥ 30ث أثناء الخدمة.

### 16.5 نهاية اليوم

محفظة → ذمم → سحب برقم المحاسب → سجل + PDF محلي.

### 16.6 زائر

انترو → هاتف → «تصفّح كزائر» → الرئيسية بلا توكن. أي طلب/سلة/تسليم/محفظة: «يجب أن تسجّل حساباً لاستخدام هذه الخدمة» → شاشة الهاتف.

---

## 17. صلاحيات الكتالوج (مرجع — غير مفروضة على التطبيق)

`rp.delivery.accept` · `rp.delivery.deliver` · `rp.delivery.postpone` · `rp.delivery.return_request` · `rp.payment.collect` · `rp.payment.withdraw` · `rp.wallet.view` · `rp.warehouse.receive`

---

## 18. ما تغيّر منذ مواصفة 2026-09-16

- ✅ `GET /public/refs` حي ومُدرج في `flutter-rep.json` `endpoints` (ليس في `forbidden`).
- ✅ الذمم تُرجع `retailer_id`.
- ✅ الصحة تُرجع `app` `env` `checks` وقد تكون `degraded`.
- ✅ انترو التطبيق: `GET /public/content/intro` (EP-PB-011، بلا حارس). الأدمن يحرّر `PUT /platform/content/intro`. التطبيق **لا** يستدعي `/platform` ولا `/channel` (`wrong_guard`). حساب سابق يتجاوز الانترو.
- ⛔ الإشعارات والمزامنة والولاء و`home-blocks` و`app-config` ما زالت ناقصة (`plan/apps.md` AP-02…06). `app-config` للتحديث الإجباري فقط، ليس للانترو.
- هذا الملف يضيف: GetX، موجّه المنتج، زائر، انترو، outbox، علي بابا، PDF محلي، وخريطة صريحة للفجوات.

عندما يصل مستودع Flutter: راجع كل شاشة مقابل §15 وهذا الملف، وأكمل الناقص دون اختراع API.
