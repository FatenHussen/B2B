# طبقة 1 — عقد تاسكات الفرونت (تفصيلي)

عقد تنفيذ لفرق العملاء الخمسة أثناء بناء باك L1.  
الملزم للأشكال: `docs/api/catalog/*.php` (`b` جسم الطلب، `r` شكل `data`، `e` أكواد الخطأ).  
المسار = `https://{host}/api/v1` + `path`.  
باك موازٍ: `docs/sprints/L1-foundation-backend-spec.md`. الشاشات الكاملة لاحقاً في DOC-12A–E — **هذا الملف يقول ماذا يُنفَّذ الآن بالضبط.**

| عميل | نوع | مستودع مقترح | حارس التوكن |
|---|---|---|---|
| تطبيق التاجر | موبايل | `apps/retailer` | `app` |
| تطبيق المندوب | موبايل | `apps/rep` | `app` |
| لوحة القناة | ويب | `apps/channel-web` | `channel` |
| لوحة المستودع | ويب / سطح مكتب | `apps/warehouse-web` | `warehouse` |
| لوحة المنصة | ويب | `apps/platform-web` | `platform` |

**يُبنى في L1:** دخول، تسجيل، انتظار اعتماد، مراجع عامة، كامل سطح المنصة (حساب / IAM / مراجع / قنوات).  
**لا يُبنى:** منتجات، أسعار، عروض، سلة، طلبات، مخزون، تسليم، مالية، مزامنة أوفلاين، بنرات، ولاء، لوحات KPI، `GET /public/app-config` (SP-17).

---

## 0. نواة تقنية مشتركة (قبل أي شاشة منتج)

حزمة واحدة (`packages/b2b-api-client`) أو نسخة مطابقة حرفياً في كل مستودع. بلا هذه النواة لا تُفتح شاشات.

### 0.1 عميل HTTP

- `baseURL` = `/api/v1`.
- كل رد نجاح: اقرأ `data` فقط للواجهة؛ احتفظ `meta.server_time` لمزامنة الساعة.
- كل رد فشل: لا تستخدم `message` من Laravel خارج الغلاف. اعرض `error.message`؛ إن `error.code === validation_failed` اربط `error.details.{field}[]` بالحقول.
- رموز تُترجم لسلوك لا لنص مخترع:

| `error.code` | سلوك الواجهة |
|---|---|
| `validation_failed` | أخطاء تحت الحقول (422) |
| `rate_limited` | تعطيل الزر + عدّاد إن وُجد `resend_after` / Retry-After |
| `unauthenticated` | امسح التوكن → شاشة الدخول |
| `token_revoked` | كـ unauthenticated |
| `otp_invalid` | اهتزاز حقل الرمز؛ لا تُعد طلب OTP تلقائياً |
| `insufficient_permission` | توست + إخفاء الإجراء |
| `requires_password_confirm` | مودال كلمة مرور ثم إعادة نفس الطلب (منصة فقط) |
| `sod_violation` | اشرح أن الجمع ممنوع؛ لا تُعد الإرسال |
| `conflict` / `illegal_transition` / `idempotency_key_conflict` | أوقف العملية واعرض الرسالة |
| `idempotency_key_required` | عطل الإنتاج — النواة نقصها المفتاح |
| `operation_in_progress` | انتظر/أعد بنفس المفتاح |

### 0.2 هيدرات على كل طلب

| هيدر | من | قيمة |
|---|---|---|
| `Accept` | الكل | `application/json` |
| `Accept-Language` | الكل | `ar` (أو `en` إن المستخدم بدّل) |
| `X-Client` | ثابت للتطبيق | `retailer-android` / `retailer-ios` / `rep-android` / `rep-ios` / `channel-web` / `warehouse-web` / `platform-web` |
| `X-App-Version` | من البناء | semver مثل `1.0.0` |
| `X-Device-Id` | موبايل + مستودع | UUID يُولَّد مرة ويُخزَّن ولا يُغيَّر بعد إعادة التثبيت إن أمكن |
| `Authorization` | بعد الدخول | `Bearer {token}` |
| `X-Channel-Id` | لوحة القناة فقط | عند عضوية أكثر من قناة |
| `X-Idempotency-Key` | كل POST/PUT/PATCH/DELETE | UUID لكل **نية مستخدم** (ضغطة تأكيد). إعادة المحاولة الشبكية = **نفس** المفتاح. ضغطة تأكيد جديدة = مفتاح جديد |

مسارات `GET /health` و`GET /public/refs` بلا Bearer وبلا idempotency.

**ممنوع:** `/api/v1/auth/*` و`/admin/channels` حتى لو ردّت.

### 0.3 مال وقوائم

- كل مبلغ/حد/`rate` FX: عدد صحيح كما الخادم. العرض: `decimals` من العملة (SYP = 0 خانات).
- قوائم المنصة: `page`, `per_page` (افتراضي 25، حد 100)، `sort`, `search`, `filter[*]` كما الكتالوج. لا تطلب الصفحة الظاهرة عند التصدير — صدّر **نفس الفلاتر النشطة**.

### 0.4 تاسكات النواة

| ID | يبني | قبول |
|---|---|---|
| FE-CORE-01 | عميل الغلاف + خريطة الأخطاء أعلاه | طلب `/health` يعرض `data.status=ok` |
| FE-CORE-02 | حقن الهيدرات + تخزين توكن آمن | بعد logout لا يبقى Bearer |
| FE-CORE-03 | Idempotency store مرتبط بزر التأكيد | قطع الشبكة وإعادة المحاولة لا يضاعف OTP request إن نفس المفتاح |
| FE-CORE-04 | `can(code)` من `permissions[]` | زر بلا صلاحية غير موجود أو معطّل |
| FE-CORE-05 | كاش `GET /public/refs` بمفتاح `sync_cursor` | محافظة تغيّر قائمة المناطق؛ `since=` عند وجود cursor |

---

## 1. تطبيق التاجر — DOC-12A

**ماذا هو في L1:** دفتر هوية محل. المستخدم يثبت رقمه، يعبّئ ملف المحل، وينتظر اعتماد المنصة/القناة.  
**ماذا ليس هو:** متجر، كتالوج، سلة، طلبات، نقاط.

### 1.1 خريطة الشاشات (هذه فقط)

```
Splash
  ├─ لا توكن ──────────────────────────────► Phone
  ├─ توكن قدرة registration / profile غير مكتمل ─► Register
  ├─ status=pending_review ────────────────► Waiting
  ├─ status=rejected ─────────────────────► Rejected
  └─ توكن كامل + active ──────────────────► EmptyHome
Phone → Otp → (verify)
  ├─ is_new_user أو profile_completed=false ─► Register
  └─ مستخدم قائم مكتمل ────────────────────► EmptyHome أو Waiting حسب status
Register → Waiting
EmptyHome / Waiting / Rejected → Logout → Phone
```

لا تبويب «الرئيسية / الطلبات / الحساب» كمنتج. حساب = خروج + رقم الهاتف فقط إن لزم.

### 1.2 شاشة الهاتف `FE-RT-PHONE`

- حقل واحد: رقم سوري. اقبل `09XXXXXXXX` أو `+9639XXXXXXXX` وأرسل كما المستخدم؛ الخادم يطبّع.
- زر واحد: «إرسال الرمز».
- `purpose`: إن جاء من «تسجيل» = `register`، من «دخول» = `login`. إن شاشة واحدة: أرسل `login`؛ الخادم يعالج الجديد عبر `is_new_user`.
- جسم: `{ phone, purpose, client: "retailer-android"|"retailer-ios" }`.
- API: `POST /public/auth/request-otp` (EP-CM-001).
- نجاح: خزّن `otp_id`, `expires_in` (300), `resend_after` (60), `channel_used` واذهب لـ OTP.
- 422: تحت حقل الهاتف. 429: عطّل الزر.

### 1.3 شاشة الرمز `FE-RT-OTP`

- 6 خانات رقمية. لا لصق عشوائي أطول من 6.
- عدّاد تنازلي من `resend_after`. عند 0: زر «إعادة إرسال».
- إعادة الإرسال: `POST /public/auth/resend-otp` جسم `{ otp_id, prefer_channel: "whatsapp"|"sms" }`. الافتراضي `whatsapp`؛ إن فشل أو المستخدم اختار SMS أرسل `sms`.
- تحقق: `POST /public/auth/verify-otp` جسم:

```
otp_id, code,
device_id = X-Device-Id,
device_name = اسم الجهاز الظاهر,
platform = android|ios
```

- 401 `otp_invalid`: امسح الخانات. لا تطلب OTP جديداً تلقائياً.
- نجاح:
  - خزّن `token` إن وُجد (قدرة `registration` للجديد أو توكن كامل للقديم).
  - `is_new_user=true` أو `profile_completed=false` → Register.
  - وإلا → Session (1.6).

### 1.4 شاشة التسجيل `FE-RT-REGISTER`

تُفتح **فقط** بتوكن `app` (قدرة registration). بدون توكن ارجع للهاتف.

قبل الرسم: `GET /public/refs` (EP-PB-001). استخدم `activity_types` النشطة، `root_categories`، `equipments`، `governorates` + `zones` حيث `zone.governorate_id` يطابق المحافظة المختارة.

| حقل UI | مفتاح API | إلزامي | ملاحظات |
|---|---|---|---|
| اسم صاحب المحل | `owner_name` | نعم | max كما الخادم |
| اسم المحل | `shop_name` | نعم | |
| نوع النشاط | `activity_type_id` | نعم | قائمة من refs |
| التصنيفات | `category_ids` | نعم ≥1 | متعدد من `root_categories`؛ إن النشاط فيه مقترحات أظهرها أولاً |
| التجهيزات | `equipment_ids` | لا | متعدد |
| المحافظة | `governorate_id` | نعم | يصفّر المنطقة عند التغيير |
| المنطقة | `zone_id` | نعم | فقط مناطق تلك المحافظة |
| الموقع | `lat`, `lng` | لا | من GPS مع إذن؛ لا تمنع الإرسال بلا GPS |
| العنوان النصي | `address` | لا | |

**لا حقل بريد. لا كلمة مرور.**

- إرسال: `POST /app/retailer/register` (EP-RT-001) + Bearer + idempotency.
- نجاح: استبدل التوكن بـ `token` الجديد. اذهب Waiting. الملف `status=pending_review`.
- 422 `zone_id` لا يتبع المحافظة: أبقِ النموذج واعرض `details`.

### 1.5 انتظار / رفض `FE-RT-WAIT`

- `pending_review`: نص ثابت «طلبك قيد المراجعة» + رقم الهاتف + زر خروج. زر «تحديث الحالة» يستدعي Session.
- `rejected`: رسالة الرفض إن وُجدت في الجلسة؛ لا نموذج تعديل في L1 إلا إعادة تواصل تشغيلي (واتساب خارج التطبيق مقبول كنص). لا إعادة تسجيل صامتة بنفس التوكن.

### 1.6 إقلاع وجلسة `FE-RT-SESSION`

عند كل cold start بوجود توكن: `GET /app/session` (EP-CM-004).

| من `data` | فعل |
|---|---|
| 401 | Phone |
| `profile_completed=false` | Register |
| `user` بحالة pending | Waiting |
| مستخدم مكتمل | EmptyHome |

`feature_flags` و`sync_cursor` و`legal`: خزّنها ولا تبنِ شاشات قانونية/أوفلاين في L1 إلا إن `requires_legal_accept=true` فاعرض حاجز نصي بسيط «يلزم قبول الشروط لاحقاً» — **لا** تستدعِ APIs قانونية غير موجودة في L1. لا تعرض `points` / `tier` حتى SP-15 حتى لو المثال في الكتالوج يحملها.

### 1.7 الرئيسية الفارغة `FE-RT-HOME-EMPTY`

شاشة واحدة: اسم المحل إن وُجد + «سيتم عرض المنتجات هنا بعد اعتماد حسابك وتجهيز الكتالوج».  
لا شبكة منتجات وهمية. لا شريط بحث. تبويب واحد.

### 1.8 خروج `FE-RT-LOGOUT`

`POST /app/auth/logout` (EP-CM-005) ثم امسح: التوكن، كاش refs، أي ملفات محلية (REQ-CM-056). تجاهل فشل الشبكة بعد المسح المحلي.

### 1.9 تعريف منتهٍ — تاجر

مراجع يمشي يدوياً:

1. رقم غير سوري → 422 تحت الحقل بالعربية.  
2. رقم جديد → OTP → نموذج → انتظار.  
3. نفس الرقم مرة ثانية (حساب قائم معتمد) → OTP → الرئيسية الفارغة.  
4. خروج يعيد لشاشة الهاتف ولا يدخل بأزرار النظام للخلف إلى التسجيل.

---

## 2. تطبيق المندوب — DOC-12B

**ماذا هو في L1:** إثبات هوية مندوب مربوط بقناة ومناطق.  
**ماذا ليس هو:** كتالوج قنوات، زبائن، سلة محل، عهدة، محفظة، خريطة توصيل.

خريطة الشاشات مطابقة للتاجر مع استبدال Register بمحتوى مختلف وEmptyHome بنص المندوب.

### 2.1 هاتف وOTP `FE-RP-PHONE` / `FE-RP-OTP`

نفس EP-CM-001/002/003.  
`client` = `rep-android` | `rep-ios`.  
تطبيق **منفصل** عن التاجر (أيقونة، `X-Client`). لا وضع «تبديل تاجر/مندوب» داخل نفس الجلسة. نفس الهاتف لا يُسجَّل kindين (الخادم يرفض).

### 2.2 تسجيل `FE-RP-REGISTER`

Bearer قدرة registration.

| حقل | مفتاح | إلزامي |
|---|---|---|
| الاسم | `name` | نعم |
| قناة التوريد | `supply_channel_id` | نعم |
| نوع النشاط | `activity_type_id` | نعم من `/public/refs` |
| المناطق | `zone_ids` | نعم ≥1 |
| ملاحظة | `note` | لا |

**مصدر القناة (ملزم — لا تخترع API):**  
`GET /public/refs` **لا** يحتوي قنوات (REQ-IN-06). لا تستدعِ `/platform/channels`.

التنفيذ في L1:

1. **رابط دعوة** من أدمن المنصة بعد إنشاء القناة: التطبيق يفتح `.../register?channel_id={id}` (و`channel_name` اختياري للعرض). الحقل يُقفل ولا يُعدَّل.  
2. إن فُتح التسجيل بلا `channel_id`: شاشة «استخدم رابط الدعوة الذي وصلك من شركتك» — لا قائمة بحث قنوات.

المناطق: من `/public/refs`. الخادم يرفض منطقة خارج تغطية القناة (422 على `zone_ids`) وقناة غير نشطة (409). اعرض `error.message` كما هو.

- API: `POST /app/rep/register` (EP-RP-001).
- نجاح: توكن جديد + `rep.status=pending_review` → Waiting.

### 2.3 انتظار ورئيسية فارغة `FE-RP-WAIT` / `FE-RP-HOME-EMPTY`

نفس منطق التاجر. الرئيسية: اسم المندوب + اسم القناة إن وُجد في الرد + «المهام والطلبات تُفعَّل بعد الاعتماد». لا قائمة منتجات.

جلسة وخروج: EP-CM-004 / EP-CM-005 كما التاجر.

### 2.4 تعريف منتهٍ — مندوب

1. بلا `channel_id` لا يُرسل التسجيل.  
2. مناطق لا تخص القناة → رسالة الخادم، النموذج يبقى.  
3. رحلة دعوة → OTP → انتظار.

---

## 3. لوحة القناة — DOC-12C

**ماذا هي في L1:** بوابة دخول لفريق الشركة المورِّدة. صدفة + اختيار قناة.  
**ماذا ليست:** كتالوج SKU، تسعير، طلبات، مناديب تشغيليون، مالية، لوحة أرقام.

### 3.1 شاشات

```
/login/phone → /login/otp → [/select-channel] → /home
```

لا مسارات `/catalog` `/orders` `/reps` في الراوتر إلا كـ 404 أو بطاقة «قريباً» **غير قابلة للنقر إلى API**.

### 3.2 دخول `FE-CH-LOGIN`

1. هاتف → `POST /channel/auth/request-otp` جسم `{ phone }` (EP-CH-001). ليس `purpose` في الكتالوج — لا ترسله.  
2. رمز → `POST /channel/auth/verify-otp` جسم `{ otp_id, code }` (EP-CH-002).  
3. خزّن `token`.  
4. إن `channels.length === 1`: احفظ id في `X-Channel-Id` واذهب `/home`.  
5. إن `> 1`: شاشة بطاقات القنوات؛ الاختيار يثبّت `X-Channel-Id` حتى يغيّره المستخدم من الهيدر/القائمة.

لا عضوية → الخادم 403؛ اعرض الرسالة ولا تخترع قناة.

### 3.3 الغلاف `FE-CH-SHELL`

- قائمة جانبية تُولَّد من `permissions[]`.  
- بنود طبقة 2+ (`sc.catalog.*`, `sc.orders.*`, `sc.dashboard.view` كأرقام): **لا تُنشأ صفحات**. إن صمّم DOC-12C القائمة كاملة: أظهر البند معطّلاً مع تلميح «يتاح بعد تجهيز التشغيل».  
- `/home`: اسم القناة المختارة + حالة المستخدم + لا رسوم بيانية ولا GMV.

### 3.4 خروج `FE-CH-LOGOUT`

لا endpoint قناة logout في كتالوج L1. امسح التوكن و`X-Channel-Id` محلياً وأعد `/login/phone`.

### 3.5 تعريف منتهٍ — قناة

مدير يدخل OTP، إن له قناتان يختار، يصل لمكتب فارغ، يخرج، لا طلبات شبكة إلى `/channel/brands` أو `/channel/products`.

---

## 4. لوحة المستودع — DOC-12D

**ماذا هي في L1:** قفل الجهاز. المستودع جهاز مشترك بـ PIN، ليس حساب واتساب.  
**ماذا ليست:** طوابير التقاط، تغليف، جرد، مرتجعات واردة.

### 4.1 تهيئة الجهاز (مرة، خارج المستخدم النهائي)

لا شاشة «إنشاء جهاز» في المنتج. `device_token` يُدخل في إعدادات التثبيت / ملف محلي يضعه المشغّل بعد `warehouse:register-device` على الخادم. الواجهة تقرأ التوكن ولا تعرضه في الإنتاج.

### 4.2 شاشة PIN `FE-WH-PIN`

- 4 أرقام فقط (`regex /^\d{4}$/`). لوحة أرقام كبيرة (استخدام قفازات/مستودع).  
- جسم: `{ device_token, pin }` → `POST /warehouse/auth/device-login` (EP-WH-001).  
- 401: اهتزاز + امسح PIN. لا تكشف إن الجهاز أم PIN.  
- نجاح: خزّن Bearer. اعرض `warehouse.name`. اذهب `/home`.

### 4.3 الرئيسية `FE-WH-HOME`

«الطوابير تُفعَّل مع التشغيل (طبقة 3)». لا أرقام `to_pick`. لا تستدعِ `GET /warehouse/queues`.

خروج: امسح التوكن؛ أبقِ `device_token` على الجهاز.

### 4.4 تعريف منتهٍ — مستودع

PIN صحيح → اسم المستودع. PIN خطأ → بقاء على الشاشة. لا مسار طوابير في الراوتر.

---

## 5. لوحة المنصة — DOC-12E (العمل الأكبر)

**ماذا هي في L1:** تشغيل المنصة: هوية الأدمن، صلاحيات، مراجع سوريا، دورة حياة القناة.  
**ماذا ليست:** لوحة GMV حية (SP-16)، خطط فوترة كاملة (SP-17)، إشعارات حملات (SP-14)، فريق المنصة CRUD الكامل (جزء SP-17).

### 5.1 معلومات التطبيق (IA)

بعد الدخول تظهر **فقط** إن `can`:

| مجموعة القائمة | مسار | صلاحية دنيا |
|---|---|---|
| نظرة عامة | `/` | أي أدمن داخل — صفحة ترحيب بلا مخططات |
| القنوات | `/channels` | `ad.channels.view` |
| طلبات الانضمام | `/channels/applications` | `ad.channels.view` |
| المراجع | `/refs/*` | `ad.refs.view` (عملات: `ad.refs.currency`) |
| الصلاحيات | `/iam/*` | `ad.iam.view_catalog` |
| التدقيق | `/audit` | `ad.audit.view` |
| حسابي | `/me` | كل أدمن |

لا بند «لوحة القيادة التشغيلية» يستدعي `GET /platform/dashboard`.

جداول: ترقيم، بحث، `per_page`. حالات تحميل/فارغ/خطأ غلاف.

### 5.2 حوارات عامة للمنصة

**تأكيد كلمة المرور (15 د):** أي 403 `requires_password_confirm` أو مسار `crit` يفتح مودال → `POST /platform/auth/confirm-password` `{ password }` (EP-AD-005) ثم يعيد الطلب الأصلي.

**سبب مكتوب:** كل تعديل مرجع/قناة في الكتالوج يطلب `reason` — حقل إلزامي في المودال لا ملاحظة اختيارية.

**اعتماد مزدوج:** POST قد لا ينفّذ فوراً. إن الرد طلب `pending` أو `deletion_request_id` أو دور `draft`: توست «أُرسل للاعتماد» + رابط صندوق الوارد. لا تُظهر «تم الحذف» قبل التنفيذ.

**Jobs:** تصدير/تجهيز تُرجع `job_id`. اعرض «جارٍ في الخلفية» — لا spinner إلى الأبد على نفس الطلب.

### 5.3 الدخول والحساب — SP-01

#### `FE-AD-LOGIN`

- `/login`: `email` + `password` → `POST /platform/auth/login` (EP-AD-001).  
- إن `requires_2fa`: خزّن `challenge_token` في ذاكرة الشاشة فقط (ليس localStorage طويل) → `/login/2fa`.  
- 401: رسالة عامة لا «الإيميل غير موجود».

#### `FE-AD-2FA`

- 6 أرقام → `POST /platform/auth/2fa/verify` `{ challenge_token, code }` (EP-AD-002).  
- نجاح: خزّن `token`، `user.roles`, `user.permissions`, `expires_at`. اذهب `/`.  
- 401 `otp_invalid`.

#### جلسة `FE-AD-BOOT`

- `GET /platform/auth/me` (EP-AD-004) أو `GET /platform/me` (EP-AD-159A) عند الإقلاع.  
- خروج: `POST /platform/auth/logout` (EP-AD-003) + مسح التخزين.

#### حسابي `/me` `FE-AD-ME`

تبويبات:

1. **الملف:** عرض + `PUT /platform/me` `{ name, phone }` (EP-AD-159B).  
2. **كلمة المرور:** `PUT /platform/me/password` `{ current_password, password, password_confirmation }` (EP-AD-159C) — crit.  
3. **جلسات:** جدول `GET /platform/auth/sessions`؛ إنهاء `DELETE /platform/auth/sessions/{id}` (EP-AD-006/007). الجلسة الحالية معلّمة `current`. إن أُنهيت الحالية → دخول.  
4. **2FA:** إن غير مفعّل: `POST /platform/me/2fa/enable` اعرض `qr_svg`/`secret` ثم `POST .../2fa/confirm` `{ code }`. اعرض `recovery_codes` **مرة واحدة** مع تحذير نسخ. لاحقاً `GET .../recovery-codes` يعرض `codes_remaining` فقط لا الرموز (EP-AD-159D/E/F).  
5. **توكنات API:** قائمة EP-AD-159G. إنشاء: اسم + `password_confirmation` → السر يُعرض مرة (EP-AD-159H). حذف EP-AD-159I.

### 5.4 IAM — SP-02

#### صلاحيات `FE-AD-IAM-PERMS`

- جدول `GET /platform/iam/permissions` فلاتر `system`, `severity`. أعمدة: code، اسم عربي، نظام، وحدة، خطورة، dual، عدد أدوار/مستخدمين.  
- صف → `GET /platform/iam/permissions/{code}/holders`: أدوار، مستخدمون، استخدام أخير.

#### أدوار `FE-AD-IAM-ROLES`

- قائمة `GET /platform/iam/roles` فلتر `system`. `is_builtin` غير قابل للحذف في UI.  
- إنشاء: اسم، `key`, `system`, وصف، صلاحيات، اختياري `copy_from_role_id`. قبل الحفظ: `POST /platform/iam/roles/preview` يعرض `summary_ar` و`sod_conflicts`. ثم `POST /platform/iam/roles` → الحالة `draft` لا `active`.  
- اعتماد: مستخدم آخر `POST /platform/iam/roles/{id}/approve` `{ reason }`. إن المنشئ يضغط اعتماد → 403 يُعرض.  
- تعديل صلاحيات: `PUT .../permissions` `{ permissions, reason }`.

#### إسناد `FE-AD-IAM-ASSIGN`

- `POST /platform/iam/assignments` حد 50 `user_ids`. اعرض `assigned` vs `rejected`.  
- سحب: `DELETE` بنفس المسار جسم `{ user_id, role_id, reason }`.

#### محاكاة `FE-AD-IAM-SIM`

نموذج: `user_type`, `user_id`, `permission`, `resource_type`, `resource_id` → مسار طبقات `decision_path` (نجاح/فشل كل طبقة). للقراءة فقط.

#### منح مؤقت `FE-AD-IAM-TEMP`

- مدة ≤ 240 دقيقة. `reason` ≥ 20 حرفاً. ينشأ `pending_approval`.  
- اعتماد: `decision` + `reason`.

#### SoD `FE-AD-IAM-SOD`

قائمة للقراءة `GET /platform/iam/sod-rules`. لا محرر قواعد في L1 ما لم يُضف لاحقاً.

#### صندوق الاعتماد `FE-AD-IAM-INBOX`

- `GET /platform/iam/approval-requests?filter[status]=pending`.  
- بتّ: `POST .../{id}/decide` `{ decision: approve|reject, reason }`. المعتمد ≠ الطالب.

#### تدقيق `FE-AD-AUDIT`

- فلاتر: actor, action, channel_id, من–إلى.  
- صف: قبل/بعد، IP، انتحال.  
- تصدير: `POST /platform/audit/export` `{ filters, format: xlsx }` → `job_id` (لا تنتظر الملف).

#### مراجعة ربعية `FE-AD-IAM-REVIEW`

- بدء: `{ quarter, scope }` → عدد البنود.  
- تفاصيل حملة: كل بند `keep|revoke` + سبب. الحملة لا تُغلق في UI وفي بنود بلا قرار.

### 5.5 المراجع — SP-03

نمط موحّد لكل كيان: قائمة → إنشاء → تعديل (`reason`) → تعطيل/تفعيل (مودال أثر `affected` ثم تأكيد). **لا حذف صلب.**

| شاشة | GET/POST قائمة | PUT | PATCH status |
|---|---|---|---|
| محافظات | EP-AD-030/031 | 042A | 043A |
| مناطق | 032/033 (`filter[governorate_id]`) | 042B | **034** (ليس 043) |
| أنواع نشاط | 035A/B | 042C | 043B |
| فئات جذر | 036A/B | 042D | 043C — 409 `ref_in_use` |
| وحدات بيع | 037A/B | 042E | 043D — 409 إن مستخدمة |
| تجهيزات | 038A/B | 042F | 043E |
| عملات | 039A/B | 042G | 043F |

مناطق: بعد اختيار تعطيل اعرض `affected.retailers/reps/open_orders` **قبل** التأكيد الثاني (REQ-AD-031).

عملات: `rate` في `POST /platform/refs/fx-rates` عدد صحيح. قائمة `GET /platform/refs/fx-rates`. تعيين `is_display_currency` يوضّح أنه يلغي السابقة. لا رسائل «سيُعاد تسعير الطلبات».

استيراد `FE-AD-REFS-IMPORT`: multipart `type` ∈ zones|governorates|… و`dry_run=true` أولاً. جدول معاينة + أخطاء `row/column/message`. تنفيذ بنفس الملف `dry_run=false` ومفتاح idempotency جديد.

### 5.6 القنوات — SP-04

#### قائمة `FE-AD-CH-LIST`

فلاتر الكتالوج: status, plan_id, governorate_id, subscription_status, over_limit, idle.  
أعمدة: اسم، حالة، باقة، محافظات، تجار/مناديب/طلبات/GMV **كما الخادم** (أصفار مسموحة، اختراع ممنوع).  
تصدير القائمة: EP-AD-059C بنفس الفلاتر لا الصفحة الحالية.

عمليات جماعية: إشعار مديرين حتى 50 قناة (EP-AD-068). تغيير باقة: معاينة EP-AD-059A (أظهر delta) ثم تنفيذ 059B مع `reason`.

#### إنشاء `FE-AD-CH-CREATE`

نموذج مطابق EP-AD-051: اسم، slug، شكل قانوني، سجل تجاري، مستندات، محافظات/مناطق/أنشطة، شعار، ملاحظة داخلية، خطة، دورة فوترة، أيام تجربة، حدود، خصم، مدير `{name, phone, email, invite_via}`.  
نجاح فوري `<500ms` ذهنياً: الحالة `provisioning` + `provisioning_job_id`. لا تنتظر `active` في نفس الطلب. اذهب للبطاقة واستطلع `GET /platform/channels/{id}` حتى `active` أو فشل → زر «إعادة التجهيز» EP-AD-053 (idempotent).

#### بطاقة القناة تبويبات DOC-07

| تبويب | سلوك | API |
|---|---|---|
| نظرة عامة | تعديل بيانات + `reason` | GET 052, PUT 062 |
| مستخدمون | قراءة | 063 |
| مدير | إعادة تعيين دعوة 72س | 064 crit |
| تغطية | خريطة/قائمة مناطق، تحذير تداخل، حفظ + سبب | 065A/B |
| مستودعات | قراءة فقط | 066 |
| ميزات | قراءة أسبقية plan/override | 067 |
| استخدام | `range=30d`؛ سلسلة فارغة/صفر مقبولة | 056 |
| حدود | override + `temporary_until` + سبب | 055 |
| حالة | أزرار حسب `allowed_next` فقط. `active→archived` غير موجود → لا زر | 054 |
| تصدير | كلمة مرور ثم job | 057 |
| حذف | يظهر فقط إن `archived`؛ اكتب اسم القناة حرفياً + كلمة مرور + otp 2FA + معتمد ثانٍ | 058 → `deletion_request_id` لا «حُذفت» |

KPI على البطاقة: اطبع أصفار الخادم أو اخفِ البلاط إن كله صفر. لا Sparkline مزيفة.

#### طلبات انضمام `FE-AD-CH-APPS`

قائمة `filter[status]=under_review`. قرار: `approve|reject` + سبب + `plan_id` + `trial_days` عند الموافقة → قناة `provisioning`.

### 5.7 تعريف منتهٍ — منصة

مراجع أدمن:

1. دخول → 2FA → قائمة قنوات فارغة أو بذور.  
2. إنشاء محافظة ومنطقة.  
3. إنشاء قناة تظهر `provisioning` ثم `active` (أو إعادة تجهيز).  
4. إنشاء دور يبقى `draft` حتى يعتمد مستخدم ثانٍ.  
5. تعطيل منطقة يطلب سبباً ويعرض الأثر.  
6. لا صفحة dashboard charts.

---

## 6. ترتيب التسليم (فرق فرونت × باك)

| أسبوع منطقي | باك جاهز | فرونت يسلّم |
|---|---|---|
| A | SP-00 `/health` | FE-CORE-01…03 على عميل واحد |
| B | SP-01 OTP/حراس | تاجر+مندوب: هاتف→OTP→انتظار؛ قناة: OTP؛ مستودع: PIN؛ منصة: دخول+2FA+حسابي |
| C | SP-02 IAM | منصة: صلاحيات، أدوار draft/اعتماد، إسناد، صندوق dual، تدقيق |
| D | SP-03 refs | تاجر: نموذج تسجيل كامل؛ منصة: كل شاشات المراجع+استيراد |
| E | SP-04 قنوات | منصة: قائمة/إنشاء/بطاقة/انضمام؛ مندوب: رابط دعوة `channel_id` |

التاجر يستطيع إنهاء OTP قبل SP-03 لكن **لا** يغلق تسجيل المحل قبل `/public/refs`.

---

## 7. ما يُغلق الطبقة للفرونت

- خمسة تطبيقات تفتح وتدخل بالعقود أعلاه.  
- صفر استدعاء لمسار `sprint` في الكتالوج ≥ SP-06.  
- Idempotency على كل كتابة.  
- 422 مربوط بالحقول.  
- `pending_review` يحجب التاجر/المندوب عن أي هيكل تشغيلي.  
- صلاحيات المنصة تخفي مجموعات القائمة.  
- مراجعة قبول: رحلة واحدة موثّقة لكل عميل (تسجيل شاشة أو قائمة يدوية).

## 8. ممنوع صراحة

اختراع منتجات في الرئيسية · أسعار من العميل · توكن تاجر على `/platform` · تسجيل جهاز مستودع من UI · `float` للحدود · GMV مزيف · نسخ شاشات DOC-12 التشغيلية «حتى تجهز الـ API» بموك بيانات بيع.
