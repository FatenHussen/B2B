# تطبيق المندوب — مواصفة Flutter (GetX) + عقد الـ API

| | |
|---|---|
| المستند | **العقد الوحيد** لبناء وإكمال تطبيق المندوب الميداني (Flutter + GetX) على واجهة Laravel هذه |
| الجمهور | مطوّر Flutter، وCursor AI عند إكمال الجوانب الناقصة، ومراجع القبول |
| الحارس | `auth:app` + `app.kind:rep` · `X-Client: rep-android` أو `rep-ios` · **لا** `X-Channel-Id` |
| الأساس | `/api/v1` |
| التاريخ | 2026-09-20 — شاشات المندوب من موجّه المنتج للنسخة للعميل (OTP مؤجّل كما هو) · العقود من الكنترولر الحي + الكتالوج + `docs/status/` |
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
| 7 | تسجيل الطلب — واجهة موجّه المنتج حرفياً (9 بنود + مسودّة علي بابا) |
| 8 | التسليم / القبول / المجدولة / المستودع / المرتجع — بطاقات وحالات المنتج |
| 9 | استلام دفعة (نافذة المنتج) + المحفظة |
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
| 1 | تسجيل طلب مبيعات | `OrderCaptureView` + مسودّة + `CartView` | ✅ سلة + quote + منتجات + عروض |
| 2 | تسليم طلب | `DeliveriesView` + `DeliveryDetailView` | ✅ `/app/rep/deliveries*` |
| 3 | استلام طلب مستودع | `WarehouseReceiptsView` | ✅ `/app/rep/warehouse-receipts*` |
| 4 | إضافة محل جديد | `AddCustomerView` | ✅ `POST /app/rep/customers` |
| 5 | طلبات مجدولة | `ScheduledOrdersView` | ✅ `GET /app/rep/scheduled-orders` |
| 6 | قبول الطلب | `AssignmentsView` | ✅ `/app/rep/assignments*` |
| 7 | استلام دفعة | `CollectPaymentView` | ✅ `POST /app/rep/payments` + حجز وصل |
| 8 | تسليم مبلغ للمحاسب | `WithdrawView` | ✅ `POST /app/rep/wallet/withdrawals` |
| 9 | قائمة المنتجات | `ProductsView` + `ProductDetailView` | ✅ `GET /app/rep/products` + `GET /app/rep/products/{id}` + عروض |
| 10 | قائمة العملاء | `CustomersView` + `CustomerDetailView` | ✅ `GET /app/rep/customers` + `GET /app/rep/customers/{id}` |
| 11 | قائمة المناطق | `ZonesView` | ✅ `GET /app/rep/zones` + `GET .../zones/{id}/shops` |
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
6. حوارات وأخطاء: `Get.snackbar` للنجاح القصير، `Get.dialog` للتأكيد الخطير (تعذّر، سحب نقد، إلغاء تسليم)، `Get.bottomSheet` لمراجعة المسودّة (§7.6) ونافذة الإرجاع/الاستبدال. عدّادات علي بابا **على بطاقة المنتج** لا في sheet إلا إذا ضاقت البطاقة.
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

الشريط السفلي **لا يدفع مسارات جديدة** للتبويبات الخمسة — `IndexedStack` داخل `ShellView`.

### 3.5 الشريط السفلي (خمسة أزرار)

موجّه المنتج: **الرئيسية · الطلبات · السلة · المحفظة · حسابي.** لا تبويب سادس.

من اليمين لليسار في RTL (الأوسط هو الرئيسية):

| فهرس | التبويب | الشاشة | مصدر حي |
|---|---|---|---|
| 0 | السلة | `CartTab` | `GET /app/rep/cart` |
| 1 | الطلبات | `OrdersTab` | `GET /app/rep/orders` |
| 2 | **الرئيسية** | `HomeTab` | `GET /app/rep/home` |
| 3 | المحفظة | `WalletTab` | `GET /app/rep/wallet` |
| 4 | حسابي | `AccountTab` | `GET /app/session` + خروج |

`initialIndex = 2`. أيقونة الرئيسية أكبر قليلاً.

اختصارات المهام تفتح مسارات فوق الصدفة (`Get.toNamed`) ثم `Get.back()`.

زائر: الأزرار الخمسة ظاهرة؛ أي خدمة (بما فيها السلة والمحفظة والطلبات وحسابي) → §5.2.

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

حدود بطاقة التسليم — موجّه المنتج يفوز على الإطار، والخادم يفوز على الحالة:

| بعد الإجراء | إطار البطاقة | مصدر الخادم |
|---|---|---|
| قائمة اليوم (لم يُحسَم) | **أخضر** | `border_color=green` (`on_the_way`) أو `blue` (`accepted`) — القائمة دائماً بإطار أخضر كما في موجّه المنتج |
| تم التسليم | **رمادي** | `border_color=gray` |
| إلغاء / لم يتم التسليم | **أحمر** | `fail` → `border_color=red` |
| تأجيل | **أزرق** | البطاقة تغادر القائمة؛ المجدولة تعيد `color=amber` — أظهر الإطار **أزرق** كما طلب المنتج |

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

لا `CheckboxList` لكل سوريا. القيمة دائماً `int` id لا الاسم. زر **«إنهاء التسجيل والدخول»** يستدعي المسار ثم يستبدل التوكن ويدخل الرئيسية. **لا ترسل `email`** — الخادم يرفضه 422 `validation_failed`.

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

🟡 **استبدل التوكن المخزَّن فوراً.** توكن التسجيل يفشل على `/app/rep/*`. `rep.id` = معرّف مستخدم التطبيق. `zones[].name` غالباً **null** — اربط الاسم من كاش refs. بعد التفعيل أعد `GET /app/rep/zones` للقائمة الحيّة.

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

### 6.2 بلوك المهام الرئيسية — أربعة أزرار ثم صف ثانٍ

موجّه المنتج: البلوك **أربع مهام** بهذا الترتيب والنص. كل زر: أيقونة + الكتابة أدناه + رقم من `data.tasks`. لا تُختلق الأرقام. زائر: أرقام 0 والقفل عند النقر.

الصف الأول — **حرفياً من موجّه المنتج** (هذه هي الشاشات التي تُبنى كما في §7–§9):

| الزر | العدّاد | النقر | الواجهة |
|---|---|---|---|
| تسجيل طلب | `tasks.orders_today` | `OrderCaptureView` | §7 |
| تسليم الطلبات | `tasks.deliveries_pending` (ينقص بعد كل تسليم عند إعادة الجلب) | `DeliveriesView` | §8.3 |
| استلام دفعة | `tasks.collected_today` (مال) | `CollectPaymentView` | §9.2 |
| قبول الطلبات | `tasks.assignments` | `AssignmentsView` | §8.1 |

الصف الثاني — **حرفياً من تتمة موجّه المنتج** (طلبات مجدولة + استلام طلب مستودع):

| الزر | العدّاد | النقر | الواجهة |
|---|---|---|---|
| طلبات مجدولة | `tasks.scheduled` | `ScheduledOrdersView` | §8.9 |
| استلام مستودع | `tasks.warehouse_receipts` | `WarehouseReceiptsView` | §8.2 |

شاشات التفاصيل تستدعي مساراتها (`GET /assignments`، `GET /deliveries` عند فتح التسليم فقط، …). **لا** تستدعِ `GET /deliveries` من الرئيسية.

### 6.3 ثلاث دوائر — صورة مخصّصة + اسم

| الدائرة | النقر |
|---|---|
| المنتجات | `ProductsView` |
| العملاء | `CustomersView` |
| المناطق | `ZonesView` |

زائر: نفس رسالة القفل. لا بنرات ولا سلايدر من الخادم حتى `AP-04` (`home-blocks`).

### 6.4 الشريط السفلي

خمسة أزرار — §3.5: الرئيسية · الطلبات · السلة · المحفظة · حسابي.

---

## 7. تسجيل طلب مبيعات

أهم وظيفة للمندوب والأكثر استخداماً — يجب أن تكون **سريعة وسهلة ومفهومة**. ضغطة «تسجيل طلب» من الرئيسية تفتح **شاشة واحدة** (`OrderCaptureView`) بهذا الترتيب حرفياً. لا تقسّمها لمعالج خطوات ولا تُخفِ حقلاً من التسعة.

### 7.0 الواجهة — حرفياً من موجّه المنتج

```
┌─────────────────────────────────────────┐
│  تسجيل طلب                         ✕    │
├─────────────────────────────────────────┤
│  [ 1. المنطقة          ▼ ]              │  إلزامي أولاً
│  [ 2. اسم المحل        ▼ ]              │  يُفعَّل بعد المنطقة
│  [ 3. بحث المنتجات     🔍 🎤 📷 ]     │  اسم / صوت / باركود
│  [ 4. الكل | زيوت | منظفات | … ]      │  شريط فئات — يُخفى إن لا فئات
│  ── 5. الأكثر طلباً ──►  [■■][■■]     │  سلايدر أفقي
│  ── 6. العروض ──►        [■■][■■]     │  سلايدر أفقي
│  ── 7. كل منتجات المندوب ──            │
│  ┌ المنتج ──────────────┐               │
│  │ صورة/حرف  الاسم      │               │
│  │ السعر   التوفر       │               │
│  │ 8. شبكة المتغيرات:   │               │  علي بابا — §7.3
│  │  [حبة ١٢] [كرتون ٤]  │               │  العدد فوق كل متغير
│  └──────────────────────┘               │
│                                         │
│  ▓ 9. قائمة المنتجات والعروض المختارة ▓ │  شريط عائم ثابت
│    ن بنود · المجموع · اضغط للمراجعة     │
└─────────────────────────────────────────┘
```

قواعد الشاشة:

1. المنطقة ثم المحل **قبل** أي إضافة. بلا محل: المنتجات ظاهرة للتصفح والأزرار مقفلة — تلميح «اختر المحل أولاً».
2. الاختيار يذهب إلى **مسودّة محلية** باسم المحل (`stagedLines`) لا إلى السلة مباشرة. +/- على المسودّة حرّ.
3. الشريط العائم (البند 9) ثابت أسفل الشاشة: عدد البنود + مجموع تقديري + النص **«قائمة المنتجات والعروض المختارة»**. النقر يفتح ورقة المراجعة (§7.6).
4. بعد إنهاء الاختيارات تُفتح الورقة **باسم المحل** للتشبيك والتعديل، ثم «أضف للسلة باسم {المحل}» → `POST /cart/lines`. بعدها تبويب السلة يُرسل الطلب (§7.8).
5. هدف أقل من 30 ثانية من فتح الشاشة حتى الإرسال.

### 7.1 المنطقة والمحل — البندان 1 و 2 على نفس الشاشة

حقلا قائمة في أعلى `OrderCaptureView` (ليست شاشات منفصلة). المحل يُقفَل حتى تُختار المنطقة. تغيير المنطقة يصفّر المحل والمسودّة.

المناطق: `GET /app/rep/zones`. المحلات: `GET /api/v1/app/rep/zones/{id}/shops?search=&page=1&per_page=25`

🟡 `search` **علوي** لا `filter[search]`. النتيجة صفحات.

```json
{
  "data": [{
    "id": 481,
    "shop_name": "بقالية النور",
    "logo": null,
    "zone_id": 12,
    "zone": "المزة",
    "address": "المزة فيلات شرقية",
    "phone": "+963931000002",
    "lat": 33.51,
    "lng": 36.27,
    "is_open": true,
    "is_active": true,
    "last_order_at": null
  }],
  "meta": { "page": 1, "per_page": 25, "total": 1, "last_page": 1 }
}
```

🟡 `is_open` **دائماً true** — لا شارة إغلاق صادقة. `last_order_at` **دائماً null** — أخفِ الصف. `logo` دائماً null. `id` = `retailer_id` في السلة والدفع.

403 `insufficient_permission`: «ليست ضمن تغطيتك».

بديل: ابدأ من `GET /app/rep/customers` ثم ثبّت `retailer_id` + `zone_id`.

### 7.2 المنتجات ✅ — البنود 3 و 4 و 5 و 7

`GET /api/v1/app/rep/products`

| استعلام | مثال | ملاحظة |
|---|---|---|
| `page` `per_page` | 1 / 25 | سقف 100 |
| `filter[search]` | زيت | اسم عربي أو SKU — **ليس** `search` العلوي |
| `filter[category_id]` | | شريط الفئات (البند 4) |
| `filter[brand_id]` | | |
| `filter[channel_id]` | | قناة المندوب — «حسب إدخال قناة التوريد التابعة لها» |
| `barcode` | | **علوي** — بعد مسح QR |
| `zone` | 12 | تسعير الكمية 1 **لمنطقة المحل**. لا `filter[zone_id]` |

⛔ لا ترسل `sort` ولا `filter[offer_only]` ولا `filter[available_only]`.

بطاقة كما يعيدها الخادم:

```json
{
  "id": 880,
  "name": "زيت دوار الشمس 1 لتر",
  "image": null,
  "brand": { "id": 12, "name": "نور" },
  "channel": { "id": 1, "name": "شركة النور" },
  "price": { "type": "tiered", "value": 12000, "label": "السعر حسب الكمية" },
  "availability": "in_stock",
  "variants": [{ "id": 1, "label": "حبة", "barcode": null }]
}
```

`image` غالباً null → placeholder حرف الاسم. `brand` قد يكون null. `variants[]` فارغة إن لا تركيبات.

اعرض `availability` نصاً. نقر البطاقة → `GET /app/rep/products/{id}` (§7.2b).

**البند 3 — البحث:** حقل واحد أعلى الكتالوج. صوت: `speech_to_text` → يملأ `filter[search]`. باركود: `mobile_scanner` → `barcode=`.

**البند 4 — شريط الفئات:** شرائح أفقية من `root_categories` في كاش `GET /public/refs`، الشريحة الأولى «الكل» (بدون فلتر). النقر يضع `filter[category_id]`. إن القائمة فارغة **أخفِ الشريط بالكامل**.

**البند 5 — سلايدر الأحدث (بدل الأكثر طلباً):** ⛔ لا `home-blocks` ولا ترتيب مبيعات. **أخفِ عنوان «الأكثر طلباً».** سلايدر أفقي من أول عناصر نفس `GET /products` (`defaultSort -created_at` = الأحدث). لا تستدعِ مساراً ثانياً.

**البند 7 — كل منتجات المندوب:** قائمة رأسية تحت السلايدرات، صفحات، بطاقة كاملة مع المتغيرات (§7.3).

### 7.2b تفاصيل المنتج ✅

`GET /api/v1/app/rep/products/{id}` — نفس العزل. 404 خارج قناة المندوب. **لا** تستدعِ `GET /app/retailer/products/{id}`.

```json
{
  "id": 880,
  "name": "زيت دوار الشمس 1 لتر",
  "image": null,
  "brand": { "id": 12, "name": "نور" },
  "channel": { "id": 1, "name": "شركة النور" },
  "price": { "type": "tiered", "value": 12000, "label": "السعر حسب الكمية" },
  "availability": "in_stock",
  "variants": [{ "id": 1, "label": "حبة", "barcode": null }],
  "images": [],
  "long_description": null
}
```

شاشة التفاصيل: صور `images[]` (أو حرف الاسم)، الاسم، البراند، السعر، `long_description` إن وُجد، شبكة المتغيرات نفسها. أزرار الكمية تكتب المسودّة باسم المحل المختار في §7.1.

### 7.3 المتغيرات — علي بابا (البند 8) ✅

موجّه المنتج حرفياً: ربط المتغيرات بالسلة مثل Alibaba. إن للمنتج أكثر من متغير يستطيع المندوب الطلب من **كل متغير** وتحديد العدد والإضافة **بشكل مستقل** عن المتغيرات الأخرى، ويظهر **فوق كل متغير** العدد المطلوب منه.

القائمة والتفاصيل تُرجعان `variants[]`. `variant_id` على `POST /cart/lines` و`POST /pricing/quote`.

```
┌ زيت دوار الشمس 1 لتر          12,000 ل.س ┐
│ [حبة]        [كرتون 12]     [عرض صندوق] │
│   ١٢              ٤               ٠      │  ← RxInt فوق الشريحة
│  −  +           −  +            −  +    │
└─────────────────────────────────────────┘
```

- كل شريحة متغير: `RxInt qty` مستقل. تغيير العدد يحدّث المسودّة فوراً ولا ينتظر «إضافة».
- العدد 0 = غير مختار (لا يظهر في الشريط العائم).
- لا تُخلط كميات متغيرين في سطر واحد.
- `variants[]` فارغة → شريحة واحدة «الكمية» على المنتج و`variant_id` يُحذف (null). **لا تخترع** أسماء متغيرات.
- إن ضاقت البطاقة: نفس الشبكة داخل `Get.bottomSheet`.

### 7.4 تسعير الخادم ✅ 🔁 قبل تثبيت الكمية

`POST /api/v1/app/pricing/quote`

```json
{
  "lines": [{ "product_id": 880, "variant_id": 1, "qty": 6 }],
  "zone_id": 12
}
```

`zone_id` = **منطقة المحل** لا منطقة المندوب الافتراضية إن اختلفا. **لا ترسل** `unit_price`. استدعِه عند استقرار عدّاد المسودّة (debounce ~400ms) لشريحة `tiered` حتى يتحدّث المجموع على الشريط العائم.

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

### 7.5 العروض ✅ — البند 6 سلايدر

`GET /api/v1/app/offers?filter[zone_id]=12&filter[activity_type_id]=3` — صفحات. `zone_id` = منطقة المحل المختار.

سلايدر أفقي بعنوان **«العروض»** تحت شريط الفئات. بطاقة: اسم + `price_after` + صورة/حرف. النقر يفتح `GET /app/offers/{id}`. زر على البطاقة يزيد مكونات العرض إلى **المسودّة** (كل `components[]` بسطر)، لا إلى السلة بعد.

`company` دائماً `null` حتى تأكيد طلب. قائمة فارغة = أخفِ السلايدر — هذا صحيح لا خطأ.

### 7.6 المسودّة ثم السلة ✅ 🔁 — البند 9

الشريط العائم ثابت، النص **«قائمة المنتجات والعروض المختارة»**، يظهر إن `stagedLines.isNotEmpty`. النقر → `Get.bottomSheet` بعنوان اسم المحل:

```
┌ قائمة بقالية النور                    ┐
│ زيت دوار الشمس · حبة      12  − +  ✕ │
│ عرض اشترِ 12              1   − +  ✕ │
│─────────────────────────────────────│
│ المجموع (تقديري)          48,000 ل.س │
│ [ أضف للسلة باسم بقالية النور ]      │
└──────────────────────────────────────┘
```

هنا يُسمح بالإنقاص والحذف — المسودّة محلية. بعد التشبيك:

`POST /api/v1/app/rep/cart/lines` — نداء واحد لكل سطر مسودّة (مفتاح تكرار جديد لكل نداء):

```json
{
  "retailer_id": 481,
  "product_id": 880,
  "variant_id": 1,
  "qty": 4
}
```

`variant_id` اختياري. `qty` min 1.

🟡 على **الخادم** الكمية تُضاف إلى سطر موجود لنفس المنتج/المتغير. لا PATCH ولا DELETE. لذلك المسودّة هي مكان التصحيح. بعد الإضافة الناجحة امسح المسودّة. إن أخطأ بعد الإضافة: «لا يمكن الإنقاص من الخادم. أرسل الطلب أو تواصل مع القناة.» لا حذف محلي يضلّل السلة.

الرد = شكل السلة كاملاً (§7.7). ثم توست «أُضيفت لسلة {المحل}» وزر اختياري «إرسال الطلب».

### 7.7 قراءة السلة ✅

`GET /api/v1/app/rep/cart`

```json
{
  "data": {
    "sections": [{
      "retailer": { "id": 481, "shop_name": "بقالية النور", "zone_id": 12 },
      "channel": { "id": 1, "name": "شركة النور" },
      "created_at": "2026-03-01T10:00:00+03:00",
      "lines": [{
        "id": 11,
        "product_id": 880,
        "name": "زيت دوار الشمس 1 لتر",
        "qty": 4,
        "unit_price": 12000,
        "line_total": 48000
      }],
      "total": 48000,
      "discount": 0
    }]
  }
}
```

تبويب السلة يعرض **كل** المحلات: اسم المحل، اسم القناة، تاريخ الإنشاء، بنود بالاسم والكمية والسعر و`line_total`. الشريط العائم في تسجيل الطلب يعرض المسودّة لا السلة. لا صورة على البند — حرف الاسم من `name`.

### 7.8 إرسال طلب محل ✅ 🔁

من تبويب السلة أو من زر بعد §7.6:

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

الخادم يحذف قسم هذا المحل. احفظ `sub_order` ثم حدّث تبويب الطلبات من `GET /app/rep/orders`. 422 قسم فارغ. 404 محل مجهول.

---

## 8. القبول · المستودع · التسليم · المجدولة · المرتجع

### 8.1 قبول الطلبات ✅ — حرفياً من موجّه المنتج

ضغطة «قبول الطلبات» تفتح شاشة تعرف المندوب بالطلبات الموكلة إليه من قناة التوريد.

```
┌ قبول الطلبات                            ┐
│ الطلبات الموجودة: 2                      │
│                                          │
│ المزة — 2                                │
│ ┌──────────┐ ┌──────────┐                │  بطاقات أفقية حسب المنطقة
│ │ اسم المحل │ │ …        │                │
│ │ المنطقة  │ │          │                │
│ │ القناة   │ │          │                │
│ │ رقم الفاتورة          │                │
│ │ تاريخ الطلب ووقته     │                │
│ │ [ قبول ] [ رفض ]      │                │
│ │ [ تفاصيل ]            │                │
│ └──────────┘ └──────────┘                │
└──────────────────────────────────────────┘
```

كل بطاقة تعرض بالترتيب: اسم المحل · المنطقة · قناة التوريد · رقم الفاتورة · تاريخ الطلب ووقته · قبول · رفض · تفاصيل.

- **قبول:** `POST /assignments/{id}/accept` → احذف البطاقة فوراً. موجّه المنتج: تُضاف لقائمة تسليم الطلبات. **لا** تستدعِ `GET /deliveries` من هنا (أثر كتابة). توست «مقبول. أكّد العهدة من المستودع ثم افتح تسليم الطلبات.»
- **رفض:** حوار سبب (max 255) → `POST /assignments/{id}/reject` → احذف البطاقة.
- **تفاصيل:** ورقة بنفس الحقول. لا `GET /assignments/{id}`. لا تختلق بنود فاتورة. إن نجح لاحقاً `GET /deliveries/{id}` اعرض البنود؛ وإلا اكتفِ بحقول البطاقة.

`GET /api/v1/app/rep/assignments` — مصفوفة بلا صفحات. `invoice_no` دائماً null — اعرض الصف بنص «—» لا تخفه (موجّه المنتج يطلب الحقل).

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

409 إن غادر `assigned`. 404 إن ليس لك. فارغ: «لا إسنادات تنتظر قبولك.»


### 8.2 استلام طلب مستودع ✅ — حرفياً من موجّه المنتج

الهدف: بعد إسناد قناة التوريد طلباً أو أكثر للمندوب، تسمح هذه النافذة بتأكيد استلام الطلبات من المستودع لأن الطلب موجود لدى تطبيق المندوب ولوحة تحكم المستودع للمطابقة بينهما. بلا هذا التأكيد `complete` يرجع 409 `illegal_transition`.

```
┌ استلام طلب مستودع                       ┐
│ 1. التاريخ          2026-03-01          │
│ 2. اسم المندوب      عمر الشامي          │
│ 3. عدد الطلبات الواجب استلامها     2    │
│                                          │
│ 4. جدول الطلبات الموكلة إليه             │
│ رقم الطلب/الفاتورة │ المحل │ المنطقة │ تفاصيل │ استلام │
│ SO-9001            │ النور │ المزة   │  👁   │ استلام │
└──────────────────────────────────────────┘
```

1. **التاريخ** = `data.date` (أو منتقي يوم يعيد الجلب بـ `?date=`).
2. **اسم المندوب** = `data.rep_name`.
3. **عدد الطلبات الواجب استلامها** = `data.count` (عدد بنود الطلبات لا عدد الحوالات).
4. **جدول** صف لكل عنصر في `orders[]`:
   - رقم الطلب أو الفاتورة = `order_no` (لا `invoice_no` على هذا المسار).
   - اسم المحل = `shop`
   - المنطقة = `zone`
   - **تفاصيل** → عرض الفاتورة المطلوبة: `GET /deliveries/{sub_order_id}` إن نجح؛ وإلا ورقة بحقول الصف فقط. **لا** تستدعِ قائمة `GET /deliveries` من هنا (أثر كتابة).
   - **استلام** → تأكيد استلام الطلب. الخادم يؤكّد **حوالة** (`handover_id`) برمز 4 خانات يطابقه المستودع على لوحته — نافذة: حقل رمز + تأكيد. صفوف نفس `handover_id` تُؤكَّد معاً وتُحذف من الجدول بعد النجاح.

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

`POST /app/rep/warehouse-receipts/{handoverId}/confirm` 🔁

```json
{ "temp_code": "7391" }
```

`temp_code` size 4. نجاح: `{ "status": "on_the_way", "tracking_enabled": true }` — ابدأ النبضات إن المناوبة شغّالة. بعد التأكيد أعد جلب الجدول حتى ينقص `count`.

| خطأ | |
|---|---|
| 422 | رمز خاطئ |
| 409 `illegal_transition` | 🟡 حوالة مجهولة أو ليست لك — **ليس 404** |
| إعادة تأكيد | حوالة مؤكَّدة تعيد نفس النجاح حتى لو الرمز خاطئ |

فارغ: «لا عهدة بانتظارك. راجع المستودع.»

### 8.3 قائمة التسليم ✅ — حرفياً من موجّه المنتج

من الوظائف الهامة والأكثر طلباً: تسليم فاتورة المحل الموكلة من قناة التوريد. الضغطة تفتح واجهة مصنّفة حسب مناطق المندوب:

```
┌ تسليم الطلبات                           ┐
│ 1. الأحد 20 أيلول 2026                  │  اليوم والتاريخ
│                                          │
│ 2. المزة          الكلي 4 · المسلّم 1    │  اسم المنطقة + العدّان
│    ┌────────┐ ┌────────┐                 │  3. بطاقات أفقية · إطار أخضر
│    │ اسم المحل                           │
│    │ المنطقة                             │
│    │ قناة التوريد                        │
│    │ رقم الفاتورة                        │
│    │ تاريخ الطلب ووقته                   │
│    │ الحالة: [ إلغاء ] [ لم يتم ] [ تأجيل ]
│    │ [ عرض على الخريطة ]                 │
│    └────────┘                            │
└──────────────────────────────────────────┘
```

1. **اليوم والتاريخ** أعلى الشاشة من `server_time` (أو ساعة الجهاز عند الانقطاع).
2. **اسم المنطقة** وبجانبه عدد الطلبات: الكلي + المسلّم (`zones[].total` / `zones[].delivered`).
3. **بطاقات أفقية** تحت اسم المنطقة، إطار أخضر 2dp. كل بطاقة بالترتيب:
   - اسم المحل
   - المنطقة
   - قناة التوريد
   - رقم الفاتورة
   - تاريخ الطلب ووقته
   - حالة الطلب — ثلاثة خيارات على البطاقة (لا تُخفَ):
     - **إلغاء** → تأكيد ثم `fail` بسبب «ألغاه المندوب» · الإطار يصبح أحمر ثم حدّث القائمة
     - **لم يتم التسليم** → نافذة سبب إلزامي → `fail` · أحمر
     - **تأجيل** → نافذة تاريخ + وقت مجدول + سبب → `postpone` · الإطار أزرق · البطاقة تُضاف للطلبات المجدولة وتغادر هذه القائمة
   - **عرض على الخريطة** → يفتح خرائط بإحداثيات المحل إن وُجدت في كاش العميل (`lat`/`lng` عند إضافة المحل). **لا** تختلق وقت وصول. إن لا إحداثيات: «لا موقع محفوظ لهذا المحل.»
4. نقر جسم البطاقة (ليس الأزرار) → التفاصيل (§8.4).

بعد الإجراء حدّث من الخادم. لا تلوّن ضد `border_color` إن الخادم أعاد لوناً آخر للحالة نفسها — ما عدا المؤجَّل: أظهر أزرق حتى لو أعادت المجدولة `amber`.

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

`status`: `accepted` · `on_the_way` · `delivered` اليوم فقط. المسلَّم في أيام سابقة **لا يظهر**.

ألوان الإطار بعد الإجراء (موجّه المنتج):

| حدث | إطار |
|---|---|
| في القائمة / تم التسليم للطريق | أخضر |
| تأكيد الاستلام | رمادي |
| إلغاء أو لم يتم | أحمر |
| تأجيل | أزرق |

### 8.4 تفاصيل التسليم ✅ — ظهر البطاقة

```
┌ تسليم · بقالية النور                    ┐
│ ┌────────────────────────────────────┐  │
│ │ صورة  اسم المنتج                   │  │
│ │       البراند · الخصائص            │  │
│ │       الكمية · السعر               │  │
│ │ [ تعديل كمية ] [ إرجاع ] [ استبدال ]
│ └────────────────────────────────────┘  │
│ مجموع الفاتورة              48,000 ل.س  │
│ [ تم الاستلام ]                          │  إن الكمية تطابق الطلب
│ [ استلام دفعة ]                          │  يظهر بعد التأكيد
└──────────────────────────────────────────┘
```

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

`lines[].id` = بند **التسليم** لـ PATCH وcomplete. `qty` متوقع. `variant` = الخصائص. `image` دائماً null → حرف الاسم.

سلوك البنود حرفياً:

| حالة الميدان | ماذا يظهر | ماذا يُستدعى |
|---|---|---|
| نقص كمية | عدّاد على الكمية ثم زر **تعديل كمية** يحفظ | `PATCH` `action=adjust` + `qty_delivered` |
| المنتج والكمية مطابقان لطلب الشراء | زر **تم الاستلام** | `POST .../complete` مع `action=accept` لكل بند مطابق |
| تالف أو مرفوض ولم يُحل | **إرجاع** أو **استبدال** | نافذة سبب → إرسال طلب للقناة |

نافذة الإرجاع/الاستبدال:

1. حقل سبب إلزامي.
2. زر «إرسال للقناة».
3. بعد الإرسال: شارة على البند **«بانتظار قرار القناة»**. القناة تقبل أو ترفض مع بيان السبب — ⛔ لا مسار قراءة لاحق للمندوب؛ ابقَ على `pending` واحفظ `request_no` محلياً. لا تختلق قبولاً.

عندما (إن) عُلم القبول من الخادم لاحقاً:

- **إرجاع مقبول:** يسجَّل مرتجعاً ويصفر حسابه (منتج واحد أو عرض) — لون المبلغ **أصفر**.
- **استبدال مقبول:** يسجَّل استبدالاً ويتغيّر لون السعر، يُفتح الطلب من جديد، الحالة **رمادي** حتى يتم الاستلام من الاستبدال، ثم يُسجَّل السعر الجديد في الفاتورة (`new_invoice_total`).

تعديل بند 🔁:

```json
{ "qty_delivered": 3, "action": "return", "reason": "رفض التاجر عبوة" }
```

`action` ✔: `accept` \| `adjust` \| `return` \| `exchange`. رد: `{ "new_invoice_total": 36000 }`.

مع الإرجاع/الاستبدال أرسل أيضاً `POST /return-requests` (§8.10). لا تغيّر لون السعر محلياً قبل رد الخادم.

أسفل القائمة: **مجموع الفاتورة** = `invoice_total`.

### 8.5 إنهاء التسليم ✅ 🔁 ثم استلام دفعة

بعد تأكيد الاستلام يظهر زر **استلام دفعة** يفتح نافذة §9.2 (ليست مساراً منفصلاً إجبارياً — `Get.dialog` أو `Get.toNamed(Routes.collect)` بنفس الحقول).

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

إن `ask_payment` املأ نافذة التحصيل بـ `invoice.no` + `receipt_no` + `total`. **لا تحجز وصلاً ثانياً** — الوصل موجود. إطار بطاقة القائمة يصبح رمادياً بعد التحديث.

إعادة الإنهاء بعد التسليم تعيد نفس الفاتورة والوصل.

### 8.6 تأجيل ✅ 🔁

من البطاقة أو التفاصيل. النافذة: منتقي تاريخ + وقت + سبب. كلا الحقلين إلزاميان مع السبب.

`POST .../postpone`

```json
{ "scheduled_at": "2026-03-02T10:00:00+03:00", "reason": "المحل مغلق" }
```

→ `{ "status": "postponed" }`. البطاقة تغادر قائمة اليوم إلى المجدولة. الإطار أزرق.

### 8.7 تعذّر / إلغاء تسليم ✅ 🔁

`POST .../fail` `{ "reason": "رفض الاستلام" }` → `{ "status": "undelivered", "border_color": "red" }`.

- إلغاء من البطاقة: تأكيد «لا يمكن التراجع من التطبيق.» ثم `reason` = «ألغاه المندوب».
- لم يتم التسليم: السبب من النافذة، لا إرسال فارغ.

الإطار أحمر. حدّث القائمة.

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

### 8.9 طلبات مجدولة ✅ — حرفياً من موجّه المنتج

الضغطة تفتح شاشة الطلبات ذات المواعيد المحددة أو المجدولة التي تحددها لوحة تحكم المورّد. الجدول بطاقات **أفقية**. كل طلب يُسلَّم **يُحذف من الشاشة**.

```
┌ طلبات مجدولة                            ┐
│ ┌──────────┐ ┌──────────┐                │  بطاقات أفقية
│ │ (1) لوغو المحل                         │
│ │ (2) اسم المحل                          │
│ │ (3) عنوان المحل                        │
│ │ (4) رقم جوال المحل                     │
│ │ (5) تاريخ ووقت التسليم المجدول         │
│ │ (6) [ تفاصيل الطلب ]                   │  فاتورة المحل
│ │ (7) الحالة: تم التسليم │ لم يتم │ مؤجلة │ ملغى
│ │ (8) ● أيقونة الحالة                    │
│ └──────────┘                             │
└──────────────────────────────────────────┘
```

كل بطاقة بهذا الترتيب:

| # | عنصر المنتج | المصدر |
|---|---|---|
| 1 | لوغو المحل | `shop_logo` دائماً `null` — حرف من `shop` |
| 2 | اسم المحل | `shop` |
| 3 | عنوان المحل | `address` |
| 4 | رقم جوال المحل | `phone` — زر اتصال `tel:` |
| 5 | تاريخ ووقت التسليم المجدول | `scheduled_at` كما هو |
| 6 | **تفاصيل الطلب** | فاتورة المحل: `GET /deliveries/{id}` → §8.4. `id` = `sub_order_id` |
| 7 | **حالة الطلب** — قائمة على البطاقة | انظر الجدول التالي |
| 8 | أيقونة يتغيّر لونها حسب الحالة | **هذه الشاشة** (لا تخلطها بإطار قائمة التسليم §8.3) |

حالة الطلب (البند 7) — يختار من القائمة:

| اختيار | حوار | مسار | بعد النجاح |
|---|---|---|---|
| تم التسليم | — (أو تفاصيل الفاتورة ثم complete) | `POST /deliveries/{id}/complete` | **احذف البطاقة** من الشاشة |
| لم يتم التسليم | حقل سبب إلزامي | `POST .../fail` | أيقونة **رمادي** ثم حدّث القائمة (غالباً تغادر لأن الحالة لم تعد `postponed`) |
| مؤجلة | موعد تسليم جديد (تاريخ+وقت) + سبب · يُرسل للوحة تحكم قناة التوريد عبر الخادم | `POST .../postpone` | ابقَ في القائمة بالوقت الجديد · أيقونة **أزرق** |
| ملغى | حقل سبب إلزامي | `POST .../fail` بسبب الإلغاء | أيقونة **أحمر** ثم احذف/حدّث |

ألوان الأيقونة (البند 8) — حرفياً من موجّه المنتج:

| حالة | لون الأيقونة |
|---|---|
| مسلَّم | **أخضر** — ثم تُحذف البطاقة فلا تبقى ظاهرة |
| غير مسلَّم | **رمادي** |
| مؤجَّل | **أزرق** |
| ملغى | **أحمر** |

الخادم يعيد `color: "amber"` دائماً على المؤجَّل — **ارسم الأيقونة أزرق** كما طلب المنتج. لا تستخدم `amber` على هذه الشاشة.

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

فارغ: «لا طلبات مؤجَّلة.»

### 8.10 مرتجع ميداني ✅ 🔁

يُستدعى من نافذة الإرجاع/الاستبدال في §8.4. لا شاشة مستقلة إجبارية.

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

موجّه المنتج: «رقم وصل الاستلام مرتبط بتطبيق المحل». على الخادم الرقم **يولَّد هنا** ثم يُستخدم في الدفعة. اعرضه في رأس الشاشة كحقل للقراءة مع أيقونة مشاركة — هذا هو الربط مع تطبيق المحل اليوم (لا مسار مزامنة مع تطبيق التاجر).

### 9.2 استلام دفعة ✅ 🔁 — حرفياً من موجّه المنتج

الضغطة من الرئيسية أو زر «استلام دفعة» بعد التسليم تفتح **هذه النافذة** بهذا الترتيب:

```
┌ استلام دفعة                             ┐
│ رقم وصل الاستلام     RCPT-10041    ⧉    │  مرتبط بتطبيق المحل — للعرض
│ التاريخ والوقت · عمر الشامي             │  من الجلسة + الساعة
│ [ اسم المحل                    ▼ ]      │
│ [ رقم الفاتورة (اختياري)       ▼ ]      │
│ عند اختيار رقم الفاتورة يُسجَّل دفعة    │
│ على الفاتورة رقم كذا؛ وإلا دفعة على     │
│ الحساب                                  │
│ مبلغ الدفعة              [          ]   │
│ [ تأكيد الاستلام ]                      │
└─────────────────────────────────────────┘
```

بعد النجاح: رسالة «تم استلام المبلغ» وإضافة المبلغ للمحفظة (`wallet_balance` من الرد). أعد `GET /app/rep/home` عند الرجوع للرئيسية حتى يتحرّك عدّاد `collected_today`.

موجّه المنتج يجعل رقم الفاتورة اختيارياً (دفعة على الحساب). **الخادم يفرض `invoice_no`.** ابنِ الحقل اختيارياً في الواجهة بالنص أعلاه؛ زر التأكيد يبقى معطّلاً حتى اختيار فاتورة أو افتح الذمم (§9.6) لاختيار واحدة. لا ترسل جسماً بلا `invoice_no`.

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

`amount` int ≥ 1. `client_op_id` max 80. إعادة بنفس القيمة = نفس الدفعة. `paid_at` = وقت الجهاز المعروض في الرأس.

افتتاح الشاشة من الرئيسية: احجز وصلاً فوراً (`reserve`) ثم املأ المحل من منتقي `GET /customers`. من بعد `complete`: لا `reserve` — استخدم `receipt_no` و`invoice.no` و`invoice.total` كاقتراح للمبلغ.

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

## 10. المنتجات · العملاء · المناطق · الطلبات · السلة · الحساب · الإشعارات

ابنِ هذه الشاشات للنسخة المسلَّمة للعميل. OTP يبقى كما هو في §5.3–5.4 — لا تعِد بناءه الآن.

### 10.1 العملاء ✅

`GET /api/v1/app/rep/customers?filter[search]=النور&page=1&per_page=25`

البحث `filter[search]` على `shop_name` فقط.

```json
{
  "data": [{
    "id": 481,
    "shop_name": "بقالية النور",
    "logo": null,
    "zone_id": 12,
    "zone": "المزة",
    "address": "المزة فيلات شرقية",
    "phone": "+963931000002",
    "lat": 33.51,
    "lng": 36.27,
    "is_open": true,
    "is_active": true,
    "last_order_at": null
  }]
}
```

القائمة تضم: محلات سجّلها المندوب **أو** تجاراً نشطين في مناطقه. `id` = `retailer_id`. `is_active=false` قيد المراجعة — اعرض؛ السلة قد تفشل لاحقاً.

بطاقة القائمة (موجّه المنتج):

```
┌──────────────────────────────────────┐
│ (حرف الاسم)  بقالية النور            │
│              المزة                   │
│ 📞 اتصال     📍 خريطة                │
│ فاتح · مفعّل                         │
└──────────────────────────────────────┘
```

- `logo` دائماً null → دائرة بحرف الاسم.
- `zone` اسم المنطقة. `phone` يفتح `tel:`. إن null أخفِ زر الاتصال.
- خريطة: `lat`/`lng` إن وُجدا وإلا «لا موقع محفوظ».
- `is_open` دائماً true حتى يوجد جدول ساعات — شارة «فاتح» ثابتة، لا تختلق إغلاقاً.
- أخفِ صف «آخر طلب» (`last_order_at` دائماً null).

نقر البطاقة → `GET /app/rep/customers/{id}`.

فلاتر متقدمة (الأكثر شراء): نفّذ `search` + فلتر منطقة محلي على `zone_id`. الباقي بلا مسار.

### 10.1b تفاصيل محل ✅

`GET /api/v1/app/rep/customers/{id}` — 404 إن لم يُسجَّل من هذا المندوب وليس تاجراً نشطاً في تغطيته.

```json
{
  "id": 481,
  "shop_name": "بقالية النور",
  "logo": null,
  "zone_id": 12,
  "zone": "المزة",
  "address": "المزة فيلات شرقية",
  "phone": "+963931000002",
  "lat": 33.51,
  "lng": 36.27,
  "is_open": true,
  "is_active": true,
  "last_order_at": null,
  "owner_name": "أبو سامر",
  "activity_type_id": 3,
  "categories": [1],
  "equipments": []
}
```

الشاشة: رأس البطاقة + المالك + نوع النشاط من كاش refs + شرائح الفئات/التجهيزات (اربط الأسماء من refs) + أزرار اتصال/خريطة + «تسجيل طلب» يفتح §7 بهذا `retailer_id` و`zone_id`.

### 10.2 إضافة محل ✅ 🔁

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
  "address": "المزة فيلات شرقية",
  "category_ids": [1],
  "equipment_ids": [],
  "client_op_id": "op_shop_local_1"
}
```

`lat` `lng` من GPS اختياريان. `address` اختياري. `category_ids` / `equipment_ids` اختياريان من refs (متعدد). `client_op_id` ✔ ولّده قبل الإرسال.

| الحقل | ويدجت | يُرسل |
|---|---|---|
| المنطقة | قائمة واحدة من `zones` (فلتر محافظة اختياري) | `zone_id: 12` |
| نوع النشاط | قائمة واحدة من `activity_types` | `activity_type_id: 3` |
| الفئات | متعدد من `root_categories` | `category_ids: [1]` |
| التجهيزات | متعدد من `equipments` | `equipment_ids: [1]` |
| العنوان | نص | `address` |

خريطة اختيار الموقع تكتب `lat`/`lng`.

```json
{ "data": { "id": 490, "status": "pending_sync" } }
```

🟡 `id` العائد = صف `RepSourcedShop` **لا** تستخدمه كـ `retailer_id`. أعد `GET /customers` وخذ `id` من القائمة.

### 10.3 المناطق ✅

`GET /api/v1/app/rep/zones`

```json
{
  "data": [{
    "id": 12,
    "name": "المزة",
    "governorate_id": 1,
    "shops_count": 4
  }]
}
```

بطاقة المنطقة: الاسم، اسم المحافظة من كاش refs عبر `governorate_id`، وعدّاد `shops_count` (تجار نشطون في المنطقة). النقر → `GET /app/rep/zones/{id}/shops` — نفس بطاقة العميل.

إضافة منطقة ✅ 🔁: `POST /api/v1/app/rep/zones`

```json
{ "zone_id": 14, "note": "طلب تغطية كفرسوسة" }
```

→ `{ "status": "pending_approval" }`. لا قائمة طلبات لاحقة. توست «طلبك قيد الموافقة».

منتقي الإضافة = محافظة (فلتر) ثم `zones` التي ليست في القائمة الحيّة. 422 خارج التغطية.

### 10.4 تبويب الطلبات ✅

`GET /api/v1/app/rep/orders` — مصفوفة غير صفحات. الطلبات التي أرسلها هذا المندوب (`submit` يضع `rep_id`) أو أُسندت إليه لاحقاً.

```json
{
  "data": [{
    "id": 9001,
    "sub_order_no": "SO-9001",
    "invoice_no": null,
    "created_at": "2026-03-01T10:00:00+03:00",
    "status": "pending",
    "shop": "بقالية النور",
    "zone": "المزة",
    "channel": "شركة النور",
    "total": 47040
  }]
}
```

بطاقة أفقية:

- رقم الفاتورة إن وُجد وإلا `sub_order_no`
- التاريخ والوقت
- الحالة (`pending` / بقية دورة الطلب)
- المحل · المنطقة · القناة
- المجموع int
- زر تفاصيل: إن وُجد التسليم لنفس `id` افتح `DeliveryDetailView`، وإلا بطاقة هذه الحقول فقط

`invoice_no` null حتى تُصدَر فاتورة — اعرض «—». اسحب-للتحديث. فارغ: «لا طلبات بعد. أرسل سلة محل.»

### 10.5 تبويب السلة ✅

`GET /app/rep/cart` — §7.7. كل قسم محل: اسم المحل، القناة، تاريخ الإنشاء، بنود بالاسم، إرسال §7.8.

### 10.6 الحساب

| عنصر المنتج | مصدر |
|---|---|
| اسم + أيقونة | جلسة — `avatar` دائماً null → حرف الاسم |
| طلبات منفّذة | `GET /wallet` → `stats.invoices_delivered` (إجمالي لا شهري — عنون بوضوح) |
| محلات | `GET /customers` → `meta.total` |
| نسبة تنفيذ | لا مسار. أخفِ أو اترك «—» |
| نقاط | `loyalty` null — أخفِ |
| لغة / مظهر / عملة | محلي GetStorage. العملة `SYP` ثابتة |
| إشعارات تفعيل | محلي حتى FCM ⛔ |
| مساعدة وخصوصية | `legal.*` نسخ ثابتة في الأصول. ⛔ لا API قبول |
| تعديل هاتف/صورة | ⛔ لا مسار. «تواصل مع القناة» |
| تعديل مناطق العمل | `POST /zones` فقط (طلب إضافي) |
| خروج | §5.10 |

لا تختلق إحصاءً شهرياً. أرقام المحفظة إجماليات مدى الحياة.

### 10.7 الإشعارات ⛔ `AP-02` — ابنِ الشاشة فارغة

ابنِ الشاشة للنسخة: قائمة، مقروء/غير، تحديد الكل، مسح، تعليم كمقروء.

`NotificationsRemote` يرمي `RemoteNotReady`. لا تستدعِ المسارات. جرس الرئيسية من `unread_notifications` (0). فارغ: «لا إشعارات بعد.»

أمثلة المنتج (جاهز للمستودع، تأكيد طلب) في `kDebugMode && USE_FIXTURES` فقط — **لا في نسخة العميل.**

العقد المستقبلي (لا تستدعِه):

```
GET    /app/notifications?filter[read]=0
POST   /app/notifications/read-all
DELETE /app/notifications
POST   /app/devices/push-token   { token, platform }
```

### 10.8 الولاء ⛔ `AP-05`

أخفِ الشريط. عقد مستقبلي: `GET /app/loyalty` · `POST /app/loyalty/redeem { reward_id }`.

### 10.9 المزامنة ⛔ `AP-03`

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
| EP-RP-011 | GET | `/app/rep/products/{id}` | |
| EP-RP-070A | GET | `/app/rep/customers` | |
| EP-RP-070C | GET | `/app/rep/customers/{id}` | |
| EP-RP-070B | POST | `/app/rep/customers` | ✔ |
| EP-RP-072 | GET | `/app/rep/zones` | |
| EP-RP-071 | POST | `/app/rep/zones` | ✔ |
| EP-RP-020 | GET | `/app/rep/zones/{id}/shops` | |
| EP-RP-021 | POST | `/app/rep/cart/lines` | ✔ |
| EP-RP-022 | GET | `/app/rep/cart` | |
| EP-RP-023 | POST | `/app/rep/cart/sections/{retailer_id}/submit` | ✔ |
| EP-RP-024 | GET | `/app/rep/orders` | |
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
| `PATCH/DELETE /app/rep/cart/lines/{id}` | غير موجود للمندوب (موجود للتاجر فقط) |
| `GET /app/rep/return-requests` | غير موجود |
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
| `ProductCard` | `id`, `name`, `image?`, `brand?`, `channel`, `price.value/type/label`, `availability`, `variants[]` |
| `ProductDetail` | بطاقة + `images[]`, `long_description?` |
| `ShopCard` | `id`, `shop_name`, `logo?`, `zone_id`, `zone?`, `address?`, `phone?`, `lat?`, `lng?`, `is_open`, `is_active`, `last_order_at?` |
| `CustomerDetail` | بطاقة + `owner_name?`, `activity_type_id`, `categories[]`, `equipments[]` |
| `ZoneCard` | `id`, `name`, `governorate_id?`, `shops_count` |
| `CartView` | `sections[].retailer.id/shop_name/zone_id`, `channel`, `created_at`, `lines[].id/product_id/name/qty/unit_price/line_total`, `total`, `discount` |
| `RepOrderCard` | `id`, `sub_order_no`, `invoice_no?`, `created_at`, `status`, `shop`, `zone`, `channel`, `total` |
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
| `RepHome` | `greeting.name/avatar`, `on_duty`, `tracking_enabled`, `tasks.*`, `loyalty`, `unread_notifications` |

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
| تخطّي كزائر | لا توكن زائر (401 على `/app/*`) | قفل محلي: «يجب أن تسجّل حساباً لاستخدام هذه الخدمة» |
| دليل القنوات عند التسجيل | ممنوع في `/public/refs` | دعوة / معرّف تجربة / قناة البذرة `1` |
| صور منتجات ومحلات | `image` / `logo` غالباً null | placeholder / حرف الاسم |
| أيقونة حالة المجدولة | الخادم `color=amber` دائماً | ارسم أخضر مسلَّم / رمادي غير مسلَّم / أزرق مؤجَّل / أحمر ملغى كما في §8.9 |
| تأكيد مستودع لكل طلب | التأكيد حوالة + رمز 4 خانات | زر استلام على الصف يفتح الرمز؛ نفس `handover_id` يُؤكَّد معاً |
| تفاصيل منتج + متغيرات علي بابا | ✅ قائمة + `GET /products/{id}` + `variants[]` | شبكة عدّادات؛ شريحة كمية واحدة إن المصفوفة فارغة |
| فئات / الأكثر مبيعاً / سلايدرات | ⛔ home-blocks | شريط فئات من `root_categories`؛ أخفِ «الأكثر طلباً»؛ سلايدر الأحدث من نفس قائمة المنتجات؛ سلايدر العروض من `GET /app/offers` |
| تعديل/حذف بند سلة | الكمية تصعد فقط | المسودّة المحلية في §7.6 هي مكان الإنقاص؛ بعد الإضافة رسالة صريحة |
| قائمة طلبات المندوب | ✅ `GET /app/rep/orders` | بطاقات أفقية من الخادم |
| تفاصيل عميل / هاتف / عنوان | ✅ بطاقة القائمة + `GET /customers/{id}` | اربط الحقول الحيّة؛ `logo`/`last_order_at` null |
| إضافة محل: فئات وتجهيزات وعنوان | ✅ اختيارية على POST | أرسلها من refs |
| `GET /app/rep/zones` | ✅ تغطية المندوب | بطاقة اسم + محافظة + `shops_count` |
| دفعة بلا رقم فاتورة | `invoice_no` إلزامي | الحقل اختياري في الشكل؛ التأكيد معطّل حتى فاتورة أو الذمم |
| قرار القناة على مرتجع/استبدال | لا GET لاحق؛ `status=pending` | شارة «بانتظار القناة»؛ لا أصفر/رمادي مختلق |
| إشعارات FCM | ⛔ AP-02 | شاشة فارغة — ابنِ الواجهة بلا استدعاء |
| ولاء / شريط نقاط | ⛔ AP-05 | أخفِ |
| مزامنة pull/push | ⛔ AP-03 | outbox محلي |
| فرض تحديث / صيانة | ⛔ app-config | مراجعة متجر يدوية |
| إحصاءات شهر الحساب | لا مسار شهري | `wallet.stats` + `customers.meta.total` بعنوان إجمالي |
| تعديل ملف المندوب (هاتف/صورة) | لا مسار | «تواصل مع القناة» |
| رفع صور مرتجع | `photos: []` فقط | لا picker |
| تصدير PDF ذمم/سحوبات | لا مسار مندوب | PDF على الجهاز |
| خريطة ETA للتسليم | لا مسار | إحداثيات المحل من بطاقة العميل؛ لا وقت وصول مختلق |
| OTP | حي كما في §5 | **مؤجّل** — لا تعِد بناء شاشات التحقق حتى تكتمل الشاشات أعلاه |

عقود الكتالوج غير الحيّة (`notifications`, `sync`, `loyalty`, `home-blocks`, `app-config`) تُنفَّذ كـ `*Remote` جاهز بـ `fromJson` **مع** `enabled = false` حتى ينقلب `status/` إلى ✅. عندها يكفي فك الرابط لا إعادة الشاشة.

---

## 15. قائمة قبول — لـ Cursor عند وصول مستودع Flutter

اعمل الشاشات الناقصة بهذا الترتيب. **OTP مؤجّل** — أبقِ §5.3–5.4 كما هي ولا تبدأ بإعادة بنائها. لا تضف مسارات مخترعة.

1. **العميل:** Dio + غلاف + مال int + هاتف سوري + ترويسات + تكرار على الكتابات فقط.
2. **Splash → انترو إن لا توكن → Phone.** توكن موجود يتجاوز الانترو إلى الرئيسية. (مسار OTP الحالي يكفي للنسخة — لا توسّعه.)
3. **Register:** `GET /public/refs` → نشاط قائمة واحدة + مناطق **متعددة** + قناة قائمة واحدة من الدعوة/define (لا دليل قنوات) + استبدال التوكن. هدف أقل من 60 ثانية. لا بريد.
4. **Shell** 5 أزرار (رئيسية / طلبات / سلة / محفظة / حسابي) + مناوبة في الرأس + شارة اتصال.
5. **Home:** `GET /app/rep/home` — ستة أزرار من `tasks` + ثلاث دوائر + رأس. لا `GET /deliveries` من الرئيسية.
6. **Order capture — §7.0 حرفياً:** منطقة → محل → بحث → فئات إن وُجدت → سلايدر عروض → كل المنتجات مع عدّاد فوق كل متغير من `variants[]` → شريط عائم «قائمة المنتجات والعروض المختارة» → مراجعة باسم المحل → `POST /cart/lines` ثم submit. مسودّة محلية قبل السلة.
7. **Products tab + detail** §7.2 / §7.2b.
8. **Cart tab** حسب المحل + اسم البند من الخادم + إرسال.
9. **قبول الطلبات §8.1** · **تسليم §8.3–8.7** · **مستودع §8.2** · **مجدولة §8.9**.
10. **استلام دفعة §9.2** + محفظة §9.3–9.6.
11. **Customers + detail + add shop** (فئات/تجهيزات/عنوان) + **Zones list** + shops + request zone.
12. **Orders tab** من `GET /app/rep/orders`.
13. **Account** من session + wallet.stats + customers meta.
14. **Notifications screen** فارغة (`RemoteNotReady`) — بلا استدعاء 404.
15. **Offers** · **Location ping** عند on_duty · **Outbox**.
16. **زائر:** قفل الكتابة.
17. **خطأ 401** → الهاتف. `otp_*` يبقى. 404 «غير موجود». مال بلا كسور. RTL.
18. **OTP أخيراً** — بعد تسليم الشاشات أعلاه: صناديق الطول، إعادة الإرسال، الرسائل فقط.

اربط كل `onPressed` بمسار من §11 أو بـ `RemoteNotReady`. إن وُجد في المشروع استدعاء لـ `/app/retailer/*` أو `/channel/*` احذفه.

اختبار يدوي ببذرة `0932000001` + `0000` ضد `http://127.0.0.1:8000/api/v1`.

---

## 16. رحلات QA

### 16.1 أول تشغيل لمندوب البذرة

`health` + إن لا توكن `GET /public/content/intro` → انترو إن `enabled` → هاتف `0932000001` `purpose=login` → OTP `0000` → الرئيسية (تجاوز الانترو في التشغيل التالي لأن التوكن موجود). المناوبة كما في البذرة (عمر: true).

### 16.2 رقم جديد

`purpose=register` → OTP → register (قناة 1 + قائمة نشاط واحدة + **مناطق متعددة** من refs تغطيها القناة) → استبدال توكن → session → شارة قيد المراجعة.

### 16.3 طلب محل

الرئيسية → تسجيل طلب → منطقة → اسم المحل → بحث/فئات/عروض/منتجات → عدّاد فوق كل متغير في المسودّة → الشريط العائم «قائمة المنتجات والعروض المختارة» → مراجعة باسم المحل → أضف للسلة → submit خصم ≤ السقف.

### 16.4 يوم تسليم

قبول الطلبات → بطاقة أفقية → قبول (تُحذف من الشاشة) → استلام مستودع (تاريخ + اسم المندوب + العدد + جدول + تفاصيل + استلام برمز 4 خانات) → تسليم الطلبات (تاريخ + منطقة الكلي/المسلّم + بطاقات خضراء) → إلغاء/لم يتم/تأجيل أو فتح التفاصيل → تعديل كمية / إرجاع أو استبدال (سبب للقناة) / تم الاستلام → إطار رمادي → استلام دفعة (وصل + محل + فاتورة + مبلغ) → نبضات ≥ 30ث أثناء الخدمة. تأجيل: تاريخ ووقت + سبب → المجدولة بطاقة أفقية (لوغو/اسم/عنوان/جوال/موعد/تفاصيل/حالة) أيقونة أزرق. لم يتم من المجدولة: سبب → أيقونة رمادي. ملغى: أحمر. تم التسليم من المجدولة: احذف البطاقة.

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
- ✅ رئيسية المندوب: `GET /app/rep/home` (EP-RP-002). بلوك المهام الأربعة من موجّه المنتج (تسجيل طلب · تسليم الطلبات · استلام دفعة · قبول الطلبات) + صف ثانٍ للمجدولة والمستودع. لا `GET /deliveries` من الرئيسية. لا بريد على التسجيل (`email` prohibited). زائر محلي — 401 بلا توكن.
- ⛔ الإشعارات والمزامنة والولاء و`home-blocks` و`app-config` ما زالت ناقصة (`plan/apps.md` AP-02…06). `app-config` للتحديث الإجباري فقط، ليس للانترو.
- 2026-09-20: الشاشات الست في بلوك المهام مطابقة لموجّه المنتج حرفياً (§7 تسجيل طلب، §8.3 تسليم، §9.2 استلام دفعة، §8.1 قبول، §8.9 مجدولة ببطاقات أفقية وحالة وأيقونة، §8.2 مستودع بجدول تأكيد العهدة).
- 2026-09-20 (نسخة العميل): `GET /products/{id}` + `variants[]` على القائمة، بطاقة محل كاملة، `GET /customers/{id}`، `GET /zones`، `GET /orders` (`rep_id` عند الإرسال)، السلة بالاسم والقناة، إضافة محل بالعنوان والفئات. OTP مؤجّل كما هو. الإشعارات شاشة فارغة.
- هذا الملف يضيف: GetX، موجّه المنتج، زائر، انترو، outbox، علي بابا، PDF محلي، وخريطة صريحة للفجوات.

عندما يصل مستودع Flutter: راجع كل شاشة مقابل §15 وهذا الملف، وأكمل الناقص دون اختراع API.
