# طبقة 1 — توصيف بناء الباك (SP-00 … SP-05)

عقد تنفيذ. المصدر الملزم: DOC-10 / DOC-11A / DOC-11B (`docs/api/catalog/*.php`) / ADR-01…09.
كل مسار كامل = `/api/v1` + `path` من الكتالوج. الأشكال في الكتالوج هي شكل `data` داخل الغلاف، وليست الجسم الخام.

**النطاق:** إغلاق أساس SP-00 ثم بناء SP-01…SP-04. SP-05 في DOC-11A مندمج في SP-01 (لا endpoints مستقلة). لا كتالوج منتجات (SP-06) في هذه الطبقة.

**مهام فرق الفرونت الموازية:** `docs/sprints/frontend/` (ملف لكل تطبيق/لوحة). الفهرس: `docs/sprints/frontend/README.md`. ليست جزءاً من إغلاق باك هذا الملف.

**الموديولات المعنية:** `core` `identity` `access` `tenancy` `reference` `integration`. الباقي يبقى هيكلاً فارغاً.

---

## 0. قواعد لا تُكسر

### 0.1 طبقات داخل كل موديول

```
app-modules/{name}/
  composer.json
  routes/api.php                          # تجميع المسارات فقط
  database/migrations|factories|seeders
  src/
    {Name}ServiceProvider.php
    Domain/          Models, Enums, ValueObjects, Events, Exceptions
    Application/     Actions (كتابة), Queries (قراءة), Listeners
    Infrastructure/  Adapters (OTP, Excel, queues)
    Presentation/    Http/Controllers, Requests, Resources, Middleware
    Contracts/       واجهات يستهلكها موديول آخر — إن لزم
```

- Controller: يحقن Action/Query، يرجّع Resource. لا قاعدة بيانات، لا `if` أعمال أطول من تفريع خطأ واضح. الكتابة: `return $this->ok($action(..., $request->validated()))`.
- Action واحد = حالة استخدام كتابة واحدة (`invoke` أو `__invoke`). اسمه فعل: `RequestOtp`, `TransitionChannel`.
- Query واحد = قراءة واحدة: `ListGovernorates`, `PublicRefsSnapshot`.
- Model لا يعرف HTTP. Resource لا يعرف قاعدة البيانات خارج الـ model المعطى.
- Enum لحالة/غرض/حارس. ممنوع string سحري في الشروط (`'active'` داخل `if` — استخدم Enum).
- لا `DB::table` للهوية/المال/الحالات. Eloquent + Value Objects.

**HTTP validation (ملزم في كل الطبقات، لا يُعاد اختراعه في L2–L4):**

| طبقة | ماذا تملك | ممنوع |
|---|---|---|
| `ApiFormRequest` | شكل الجسم: `required` / نوع / طول / `SyrianPhone` | منطق أعمال، استعلام قناة/مرجع |
| Controller | حقن Request + Action/Query | `$request->validate()`، رسائل، Eloquent |
| Action | قواعد العمل | نص خطأ إنجليزي ثابت |

- كل كتابة بجسم (POST/PUT/PATCH، وDELETE إن وُجد جسم): صف يمتد `Modules\Core\Http\ApiFormRequest`. القراءة وDELETE بلا جسم: `Illuminate\Http\Request`.
- الـ Request فيه `rules()` فقط. الرسائل وأسماء الحقول من `lang/{ar,en}/validation.php` (`attributes` + `syrian_phone`). ممنوع `messages()` بنص خام إلا إن كان المفتاح غير موجود في lang.
- هاتف سوري: قاعدة `SyrianPhone` + `NormalizesSyrianPhone` عند الحاجة — ليس regex مكرر في كل ملف.
- خطأ حقل من منطق العمل (المنطقة خارج التغطية، مرجع غير موجود): `Modules\Core\Support\InvalidFields::throw(['field' => 'module.key'])` ومفتاح في `lang/{locale}/{module}.php`. تعارض حالة (قناة غير نشطة): `DomainException` بكود الكتالوج (غالباً 409).
- `Accept-Language`: `ar` افتراضي، `en` مدعوم (`SetAcceptLanguage`). غلاف 422: `error.code = validation_failed`، `error.message` = أول رسالة حقل، `error.details` = أخطاء الحقول.

### 0.2 حدود الموديول (Deptrac + Composer)

- Foundation لا يستورد Domain/Coordination.
- لا `use Modules\X\Domain\Models\...` من موديول Y. العبور: **عقد في `core` + ربط في ServiceProvider المنفِّذ**، أو Event.
- المعرّفات أرقام صحيحة عبر الحدود (`channel_id`, `zone_id`). لا علاقات Eloquent عابرة.
- `identity` يعتمد `core` فقط. `tenancy` و`reference` و`access` تنفّذ عقود `core` التي يحتاجها `identity`.

عقود تُضاف في `Modules\Core\Contracts` في هذه الطبقة:

| عقد | ينفّذه | يستهلكه |
|---|---|---|
| `OtpChannel` | `integration` (واتساب/SMS) — موجود | `identity` |
| `ChannelDirectory` | `tenancy` | `identity` (تسجيل مندوب: القناة نشطة؟ تغطي المنطقة؟) |
| `ReferenceDirectory` | `reference` | `identity` (المنطقة ∈ المحافظة؟ نوع النشاط موجود؟) |
| `RecordsAudit` | `core` (تنفيذ داخلي على `AuditLog`) | الكل |
| `AccessCatalog` | `access` | `identity` (صلاحيات في `/me` و`/session`) |
| `AssignsChannelManager` | `access` + `identity` عبر حدث | `tenancy` عند إنشاء قناة |

### 0.3 HTTP

غلاف نجاح:

```json
{ "data": {}, "meta": { "server_time": "ISO-8601 Asia/Damascus" } }
```

غلاف خطأ:

```json
{ "error": { "code": "snake_case", "message": "…", "details": {} } }
```

- `meta.server_time` دائماً. القوائم: Spatie Query Builder — `page`, `per_page` (افتراضي 25، حد 100)، `sort`, `search`, `filter[*]`.
- الكتابة (POST/PUT/PATCH/DELETE) على مسارات محمية: هيدر `X-Idempotency-Key` **إلزامي**. غيابه → 400 `idempotency_key_required`. إعادة نفس المفتاح + نفس الجسم → إعادة الاستجابة المخزّنة. جسم مختلف → 409 `idempotency_key_conflict`. قيد التنفيذ → 409 `operation_in_progress`. (اليوم المفتاح اختياري — يُصلح في SP-00/01.)
- هيدرات قياسية: `Authorization`, `Accept`, `Accept-Language` (افتراضي `ar` عبر `SetAcceptLanguage`؛ `en` مدعوم), `X-Device-Id` (تطبيقات + مستودع), `X-App-Version`, `X-Client`, `X-Channel-Id` (أدمن منصة أو مستخدم قنوات متعددة).
- الحراس (Sanctum token abilities / guard name):

| بادئة | Guard | دخول |
|---|---|---|
| `/public/*`, `/health` | لا شيء | — |
| `/platform/*` | `platform` | بريد + كلمة مرور + 2FA |
| `/channel/*` | `channel` | هاتف + OTP واتساب |
| `/warehouse/*` | `warehouse` | جهاز مسجّل + PIN |
| `/app/retailer/*`, `/app/rep/*`, `/app/session`, `/app/auth/*` | `app` | هاتف + OTP مربوط بجهاز |

طلب على الحارس الخطأ يُرفض **قبل** أي استعلام أعمال (401 `unauthenticated`).

- المال: `BIGINT` أصغر وحدة. لا `float`. `Money` + `MoneyCast` الموجودان. أسعار الصرف في SP-03: `rate` عدد صحيح.
- المستأجر: `BelongsToChannel` يبقى. عمود النطاق يبقى `supply_channel_id` في هذه الطبقة (لا إعادة تسمية الجدول إلى `channels` الآن — تكلفة هجرة بلا فائدة API). `Tenant::withoutScope()` / `as()` للمهام الإدارية فقط.
- التدقيق: `audit_logs` append-only (موجود + triggers). كل كتابة IAM/قناة/تعطيل مرجع تُسجَّل عبر `RecordsAudit`. لا Spatie activitylog لأحداث الأعمال الجديدة.
- الطوابير (إغلاق SP-00): Horizon، أربع طوابير: `default`, `notifications`, `exports`, `provisioning`. لا `dispatch` متزامن لعمل > 200ms (تجهيز قناة، تصدير، استيراد).

### 0.4 أسلوب الكود

- `declare(strict_types=1);` في كل ملف PHP جديد.
- `final` للـ Actions/Queries/Value Objects إلا إذا وُجد سبب وراثة.
- أسماء إنجليزية في الكود، `name_ar` في الكتالوج للنصوص المعروضة.
- اختبار Pest: ملف Feature لكل مجموعة endpoints. مجموعة `tenancy` لعزل القناة، `permissions` للصلاحيات الحرجة. لا اختبار يضرب شبكة واتساب — `FakeOtpChannel` (موجود).
- المسارات القديمة `/api/v1/auth/*`, `/admin/channels`, `/governorates` تُحذف أو تُوجَّه 410 بعد أن تثبت المسارات الجديدة في الاختبارات. لا تُترك سطحين حيّين.

---

## 1. SP-00 — إغلاق الأساس

الموجود ويعمل: غلاف، Money، Idempotency (اختياري)، Audit، Tenant trait، 22 حزمة، Deptrac، CI، Docker Compose هيكلي، `GET /api/v1/health`.

يُغلق في هذا السبرنت (بدون منتج جديد):

| بند | المطلوب |
|---|---|
| Health | الإبقاء على `GET /api/v1/health` = EP-CORE-001. `data.status = ok`. بدون auth/tenant. |
| Idempotency | تفعيل الوسيط على كل مجموعة كتابة. المفتاح إلزامي. |
| Horizon | تثبيت + 4 طوابير أعلاه. |
| استثناءات المجال | `OtpException` ونظائرها تُترجم في `bootstrap/app.php` إلى أكواد الكتالوج (`otp_invalid`, `rate_limited`, …) لا رسالة Laravel الخام. |
| `EnsureIdempotency` + `ResolveTenant` | تسجيلهما على مجموعة `api` المناسبة لا الاكتفاء بالـ alias. |
| Larastan | إصلاح بوابة CI أو عزلها بملاحظة ADR إن بقي تعارض Laravel 13. |
| `Modules.old` | حذف بعد تأكيد أخضر للاختبارات. |

**API الخارج:** 1 — `GET /health`.

---

## 2. SP-01 — الهوية (4 جداول / 4 حراس) + تسجيل (يشمل SP-05)

### 2.1 قرار الجداول (تفسير ADR-03)

أربعة حراس = أربعة جداول مصادقة. التاجر والمندوب يشتركان في حارس `app` وجدول `app_users` مع `kind`.

| جدول | Guard | مفاتيح فريدة |
|---|---|---|
| `platform_users` | `platform` | `email` |
| `channel_users` | `channel` | `phone` |
| `warehouse_users` | `warehouse` | لا هاتف عام — مربوط بجهاز |
| `app_users` | `app` | `phone` + `kind` ∈ `retailer\|rep` (هاتف واحد لا يكون تاجر ومندوب في نفس الصف؛ إن لزم الدوران لاحقاً صفّان منفصلان بهاتف واحد فقط إذا اختلف `kind` — **ارفض نفس الهاتف على kindين في SP-01** إلا بقرار منتج مكتوب) |

ملفات Sanctum: `personal_access_tokens.tokenable` polymorphic على هذه النماذج الأربعة. `config/auth.php`: أربعة guards `sanctum` + أربعة providers.

جدول `users` الحالي يُهجَّر **مرة واحدة** (data migration) ثم يُحذف. لا `type` enum على جدول واحد بعد هذا السبرنت.

### 2.2 جداول مساندة في `identity`

```
otp_requests          — الموجود + أعمدة: public_id (otp_9f2a71), channel_used (whatsapp|sms),
                        client, ip, device_id, purpose ∈ register|login|channel_login
otp_challenges        — تحدي 2FA للمنصة: public_id (cht_*), platform_user_id, expires_at, attempts
app_devices           — بديل devices: tokenable_type/id, device_uuid, platform, name, push_token, last_seen_at
warehouse_devices     — device_token (hash), pin_hash, warehouse_id (int فقط), channel_id, label, revoked_at
retailer_profiles     — app_user_id unique, shop_name, activity_type_id, governorate_id, zone_id,
                        lat, lng, address, status ∈ pending_review|active|rejected, category/equipment via pivots
rep_profiles          — app_user_id unique, channel_id, activity_type_id, status, note
rep_profile_zones     — (rep_profile_id, zone_id)
retailer_profile_categories / retailer_profile_equipments
channel_user_channels — (channel_user_id, channel_id) عضوية متعدد-إلى-متعدد + is_default
platform_sessions     — إن لم يكفِ Sanctum: token_id, ip, user_agent, last_active_at للعرض في EP-AD-006
password_confirmations — platform_user_id, confirmed_until (15 دقيقة، BR-AD-04)
```

`warehouse_id` في SP-01: عمود صحيح بدون جدول مستودعات كامل. صف جهاز يتضمّن `warehouse_id` و`name` للرد. جدول `warehouses` الأدنى يُنشأ في `tenancy` (id, channel_id, name, status) حتى device-login لا يُرجع بيانات وهمية بلا مصدر — **ليس** موديول fulfillment.

### 2.3 نماذج المجال (`identity`)

- `PlatformUser`, `ChannelUser`, `WarehouseUser`, `AppUser` — كلها `Authenticatable` + `HasApiTokens`. لا `HasRoles` على `AppUser` إلا صلاحيات تطبيق ثابتة من AccessCatalog حسب `kind`.
- `PhoneNumber` — الإبقاء. الغرض OTP: وسّع `OtpPurpose`: `Login`, `Register`, `ChannelLogin`.
- `OtpService` — يُعاد عقده:
  - `request()` يرجع `otp_id` + قناة + `expires_in` + `resend_after` (اليوم `void`).
  - تحقق بـ `otp_id` لا بالهاتف في الجسم (الكتالوج).
  - 5 محاولات لكل `otp_id`.
  - حدود: 3/ساعة/هاتف، 10/ساعة/جهاز، 30/ساعة/IP (REQ-CM-055). استبدل `otp` limiter الحالي.
- `TokenIssuer` — يصدر توكن الحارس الصحيح ويربط الجهاز. بادئة توكن للتمييز في الأمثلة فقط ليست شرطاً أمنياً.
- زائر (SP-05): `verify-otp` لمستخدم غير موجود → `is_new_user: true`, `profile_completed: false`, **بدون** توكن تطبيق كامل أو بتوكن قدرة `registration` فقط يُسمح له بـ `POST /app/retailer/register` أو `/app/rep/register`. اختر **توكن قدرة `registration`** — أوضح للعميل من الجلسة المؤقتة في الكاش.

### 2.4 مسارات — 17 endpoint

تجميع في `identity/routes/api.php` تحت `api/v1`.

#### عام

| كود | مسار | سلوك |
|---|---|---|
| EP-CM-001 | `POST /public/auth/request-otp` | جسم: `phone`, `purpose` (`register\|login`), `client`. هاتف `+9639XXXXXXXX`. رد: `otp_id`, `channel_used`, `expires_in=300`, `resend_after=60`. أخطاء: 422 `validation_failed`, 429 `rate_limited`. |
| EP-CM-002 | `POST /public/auth/verify-otp` | جسم: `otp_id`, `code`, `device_id`, `device_name`, `platform`. مستخدم موجود مكتمل: توكن `app` + ملف. جديد: `is_new_user=true` بدون ملف كامل. 401 `otp_invalid`. |
| EP-CM-003 | `POST /public/auth/resend-otp` | `otp_id`, `prefer_channel` `whatsapp\|sms`. فشل واتساب → SMS عبر `OtpChannel` (تكامل). 404 إن انتهت/لا توجد. |

#### منصة

| كود | مسار | سلوك |
|---|---|---|
| EP-AD-001 | `POST /platform/auth/login` | بريد+كلمة مرور. إن 2FA مفعّل: **لا توكن** — `requires_2fa` + `challenge_token`. وإلا توكن (غير مستحسن في الإنتاج؛ افترض 2FA إلزامي لأدمن). 401 `unauthenticated`. |
| EP-AD-002 | `POST /platform/auth/2fa/verify` | `challenge_token` + `code`. رد: `token`, `user` (roles+permissions), `expires_at`. |
| EP-AD-003 | `POST /platform/auth/logout` | إلغاء التوكن الحالي. `{success:true}` |
| EP-AD-004 | `GET /platform/auth/me` | المستخدم + `two_factor_enabled` + `last_login_at` |
| EP-AD-005 | `POST /platform/auth/confirm-password` | يخزّن `confirmed_until` +15د. مطلوب قبل EP-AD-057/058. 403 `requires_password_confirm` إن انتهت. |
| EP-AD-006 | `GET /platform/auth/sessions` | قائمة جلسات الجهاز/المتصفح |
| EP-AD-007 | `DELETE /platform/auth/sessions/{id}` | إلغاء. إن أُلغي الحالي: 401 `token_revoked` في الطلبات اللاحقة |

2FA: TOTP (Google Authenticator) أو OTP على البريد. في SP-01 يكفي TOTP سرّ في `platform_users.two_factor_secret` (مشفّر) + أكواد احتياط. لا تعيد اختراع واتساب لأدمن المنصة.

#### قناة

| كود | مسار | سلوك |
|---|---|---|
| EP-CH-001 | `POST /channel/auth/request-otp` | `phone` فقط. نفس محرك OTP بـ `ChannelLogin`. |
| EP-CH-002 | `POST /channel/auth/verify-otp` | توكن `channel` + `channels[]` إن تعدد العضوية + `permissions`. لا عضوية → 403. |

#### مستودع

| كود | مسار | سلوك |
|---|---|---|
| EP-WH-001 | `POST /warehouse/auth/device-login` | `device_token` + `pin` (4 أرقام). الجهاز مُسبَق التسجيل. رد: توكن + `warehouse{id,name}` + permissions. 401. |

لا API لتسجيل الجهاز في SP-01 — seeder/artisan `warehouse:register-device`.

#### تطبيق

| كود | مسار | سلوك |
|---|---|---|
| EP-RT-001 | `POST /app/retailer/register` | حارس `app` بقدرة `registration` أو جلسة غير مكتملة. **لا حقل email.** `zone_id` ∈ `governorate_id` عبر `ReferenceDirectory`. حالة الملف `pending_review`. رد: retailer + توكن كامل. |
| EP-RP-001 | `POST /app/rep/register` | `supply_channel_id` نشط؛ كل `zone_ids` داخل تغطية القناة عبر `ChannelDirectory`. `pending_review`. |
| EP-CM-004 | `GET /app/session` | `user` + `permissions` + `feature_flags` (مصفوفة ثابتة/config حتى SP-17) + `sync_cursor` (سلسلة فارغة/حالية) + `server_time`. |
| EP-CM-005 | `POST /app/auth/logout` | إلغاء توكن الجهاز. |

### 2.5 تكامل (`integration`)

- `WhatsAppOtpChannel` موجود — اربطه افتراضياً خارج `local`/`testing`.
- `SmsOtpChannel` جديد (محوّل مزوّد محلي؛ في الاختبار Fake).
- `CompositeOtpChannel`: طلب أول → واتساب؛ `resend` بـ `prefer_channel=sms` أو فشل واتساب → SMS.
- لا منطق أعمال هنا. يحقن `identity` عقد `OtpChannel` فقط.

### 2.6 اختبارات إلزامية

- رقم غير سوري → 422.
- تجاوز معدل OTP → 429.
- 6 محاولات تحقق → `otp_invalid` / قفل.
- توكن تاجر لا يفتح `/channel/*` ولا `/platform/*`.
- توكن منصة لا يفتح `/app/retailer/*`.
- تسجيل تاجر: منطقة خارج المحافظة → 422.
- تسجيل مندوب: منطقة خارج تغطية القناة → 422.
- تسجيل مندوب على قناة غير `active` → 422/409.

---

## 3. SP-02 — الصلاحيات والتدقيق

موديول: `access`. التدقيق يُقرأ من `core.audit_logs` عبر Query في Access (عقد قراءة) حتى لا ينسخ الجدول.

### 3.1 كتالوج الصلاحيات

استبدل `AccessMatrix` (وحدات `dashboard.view` القديمة). الاسم = كود الكتالوج: `ad.*` `sc.*` `wh.*` `rt.*` `rp.*`.

في SP-02:

1. ثابت PHP `Modules\Access\Domain\PermissionCatalog` — كل كود يظهر في `docs/api/catalog` حتى SP-17 (المصدر الوحيد المتاح لـ «170»). كل عنصر: `code`, `name_ar`, `system` (`platform|channel|warehouse|app`), `module`, `severity` (`standard|critical`), `dual_approval`, `delegatable`.
2. أمر `access:sync` يزامن Spatie permissions.
3. أمر `access:verify` يفشل CI إن وُجد `permission:` في مسار بلا صف كتالوج.
4. أدوار مدمجة (`is_builtin=true`): `platform_admin`, `channel_manager`, `sales_manager`, `catalog_manager`, `accountant`, وصلاحيات مستودع/تاجر/مندوب كأدوار تطبيق ثابتة.

Spatie: `guard_name` يطابق حارس Laravel (`platform`, `channel`, `warehouse`, `app`). أدوار القناة مربوطة بـ team = `channel_id` (Spatie teams) حتى مدير قناة أ لا يرى قناة ب.

### 3.2 فصل المهام (SoD)

جدول `sod_rules`: `code`, `permission_a`, `permission_b`, `reason`, JSON `exceptions`. بذرة SOD-01 على الأقل: `sc.orders.confirm` × `sc.finance.payment`. عند إنشاء/تعديل دور أو إسناد: ارفض تجميع الطرفين على نفس المستخدم إلا استثناء موثّق → 403 `sod_violation`.

SOD-05: معتمد الدور ≠ منشئه.

### 3.3 موافقة مزدوجة ومنح مؤقت

جداول:

```
access_change_requests  — type ∈ role_create|role_permissions|temp_grant|channel_delete
                          status ∈ pending|approved|rejected, payload JSON,
                          requester_id, approver_id, reason
temp_grants             — user morph, permission, granted_until, request_id, revoked_at
```

أي endpoint في الكتالوج `dual_approval: true` لا ينفّذ مباشرة: ينشئ طلباً (أو ينفّذ المسار على مرحلتين كما في الكتالوج: إنشاء دور `draft` ثم `POST .../approve`).

منح مؤقت: `duration_minutes ≤ 240`, `reason` ≥ 20 حرفاً. حتى الاعتماد: `pending_approval`. بعد الاعتماد: Spatie يعطي الصلاحية حتى `granted_until` (رافع في Gate).

### 3.4 محاكاة

`POST /platform/iam/simulate`: طبقات بالترتيب guard → permission → tenant → sod → temp-grant. رد `decision_path[]` كما في الكتالوج. لا يغيّر حالة.

### 3.5 مسارات — 15

كلها `auth:platform` + صلاحية مذكورة في الكتالوج:

| كود | مسار | إذن |
|---|---|---|
| EP-AD-010 | `GET /platform/iam/permissions` | `ad.iam.view_catalog` |
| EP-AD-011 | `GET /platform/iam/permissions/{code}/holders` | نفس |
| EP-AD-012 | `GET /platform/iam/roles` | نفس |
| EP-AD-013 | `POST /platform/iam/roles` | `ad.iam.role_create` — dual، يبدأ `draft` |
| EP-AD-014 | `POST /platform/iam/roles/{id}/approve` | `ad.iam.role_approve` — crit + dual |
| EP-AD-015 | `PUT /platform/iam/roles/{id}/permissions` | `ad.iam.role_create` — dual |
| EP-AD-016 | `POST /platform/iam/assignments` | `ad.iam.role_assign` — حد 50 `user_ids` |
| EP-AD-017 | `DELETE /platform/iam/assignments` | نفس — جسم `user_id`,`role_id`,`reason` |
| EP-AD-018 | `POST /platform/iam/simulate` | `ad.iam.simulate` |
| EP-AD-019 | `POST /platform/iam/temp-grants` | `ad.iam.grant_temp` — crit + dual |
| EP-AD-020 | `POST /platform/iam/temp-grants/{id}/approve` | نفس |
| EP-AD-021 | `GET /platform/iam/sod-rules` | `ad.iam.view_catalog` |
| EP-AD-022 | `GET /platform/audit` | `ad.audit.view` — فلتر actor/action/channel_id/date |
| EP-AD-023 | `POST /platform/audit/export` | `ad.audit.export` — Job على `exports`، العملية نفسها تُدقَّق |
| EP-AD-024 | `POST /platform/iam/reviews` | `ad.audit.review` — حملة ربعية؛ جدول `access_reviews` + items |

وسّع `audit_logs` إن لزم: `channel_id`, `impersonated` (bool), `before`/`after` داخل `properties`. لا تحديث صفوف قديمة — أعمدة جديدة nullable.

اختبار مجموعة `permissions`: محاولة `role_approve` من المنشئ → 403؛ إسناد يخالف SOD → 403؛ تصدير التدقيق يضيف صفاً في `audit_logs`.

---

## 4. SP-03 — المراجع المشتركة

موديول: `reference`. الجغرافيا الحالية (محافظات/مناطق/`channel_zone`) **تُعاد محاذاتها مع الكتالوج** — المخطط الحالي (`name_ar/name_en/code` بلا `order/status` على المحافظة) لا يطابق العقد.

### 4.1 جداول

أعد تشكيل `governorates`: `name`, `order`, `status` (`active|disabled`). انقل `name_ar` → `name` في هجرة. `code` اختياري داخلي إن بقي.

`zones`: أضف `district`, `order`, `status` (موجود). `polygon` JSON. **تعطيل ناعم فقط** — لا `DELETE` منطقة في عقد المنصة (الكتالوج `PATCH .../status`).

جداول جديدة (منصة، ليست قناة):

```
activity_types        name, icon, description, order, status
activity_type_root_category  (activity_type_id, root_category_id)  — suggested_category_ids
root_categories       name, icon, image, order, status   — مستوى جذر المنصة فقط (5 مستويات للكتالوج في SP-06)
sale_units            name, abbr, default_factor, status
equipments            name, icon, description, order, status
currencies            iso unique, name, decimals, is_display_currency
fx_rates              currency_id, rate BIGINT, effective_from, source
ref_imports           type, actor, dry_run, result JSON, cursor
```

`channel_zone` يبقى في `reference` (تغطية قناة × منطقة + رسوم Money). لا CRUD قناة له في مسارات المنصة SP-03؛ يُستخدم من `ChannelDirectory.coversZone`.

مزامنة عامة: عمود `updated_at` + توليد `sync_cursor` (ADR-06): سلسلة غير زمنية خام (مثلاً ULID أو `id:timestamp` موقعة). `GET /public/refs?since=` يرجع ما تغيّر فقط إن `since` غير فارغ.

### 4.2 سلوك التعطيل

`PATCH /platform/refs/zones/{id}/status`: احسب الأثر (تجّار/مناديب/طلبات مفتوحة) عبر عقود لاحقاً؛ في SP-03 إن لم توجد جداول تجّار بعد: `retailers`/`reps` من `retailer_profiles`/`rep_profiles` في identity عبر أعداد SQL على IDs فقط أو عدّاد 0 للطلبات حتى SP-09. لا تحذف الصف.

الاستيراد Excel: `POST /platform/refs/import` multipart `type` ∈ `zones|governorates|…`, `dry_run`. PhpSpreadsheet في `Infrastructure`. `dry_run=true` لا يكتب. أخطاء صف/عمود كما في الكتالوج.

FX: `POST /platform/refs/fx-rates` لا يعيد تسعير طلبات (لا طلبات بعد). `rate` integer.

عملة العرض: عملة واحدة `is_display_currency=true`. عند إنشاء ثانية بـ true تُسقط السابقة.

### 4.3 مسارات — 18

| كود | مسار | إذن |
|---|---|---|
| EP-PB-001 | `GET /public/refs` | عام |
| EP-AD-030/031 | `GET/POST /platform/refs/governorates` | view / create |
| EP-AD-032/033 | `GET/POST /platform/refs/zones` | view / create |
| EP-AD-034 | `PATCH /platform/refs/zones/{id}/status` | `ad.refs.disable` crit |
| EP-AD-035A/B | activity-types | view / create |
| EP-AD-036A/B | root-categories | view / create |
| EP-AD-037A/B | sale-units | view / create |
| EP-AD-038A/B | equipments | view / create |
| EP-AD-039A/B | currencies | `ad.refs.currency` |
| EP-AD-040 | `POST /platform/refs/fx-rates` | `ad.refs.currency` |
| EP-AD-041 | `POST /platform/refs/import` | `ad.refs.import` |

القوائم كلها `ad.refs.view` إلا العملة. **لا تُبقِ** `/api/v1/governorates` القديم.

اختبارات: عزل — إنشاء منطقة؛ `GET /public/refs` يراها `active` فقط؛ تعطيل منطقة يغيّر اللقطة؛ استيراد dry_run بلا صفوف جديدة.

---

## 5. SP-04 — دورة حياة القناة

موديول: `tenancy`. الفوترة الكاملة SP-17. هنا: خطة **خفيفة** حتى يعمل `plan_id` والحدود.

### 5.1 جداول

وسّع `supply_channels` (لا تعيد تسمية الجدول):

```
legal_form, cr_number, logo_media_id nullable,
plan_id, billing_cycle, trial_ends_at,
status ∈ provisioning|active|suspended|archived,
provisioned_at, deleted deletion flow via requests not hard delete in same call
```

```
channel_plans           — id, key (starter|growth|…), name, default_limits JSON  — بذرة صفّين
channel_limits          — channel_id unique, users, warehouses, reps, skus, storage_mb,
                          temporary_until nullable, reason
channel_governorates / channel_activity_types  — تغطية نشاط/محافظة
channel_documents       — channel_id, kind, media_id
channel_internal_notes  — channel_id, body, actor_id, created_at
channel_events          — timeline: created|provisioned|suspended|…
channel_provision_jobs  — public job id, status, error
channel_deletion_requests — dual approval payload
```

ربط المناطق: استخدم `channel_zone` الموجود عند الإنشاء (`zone_ids` في EP-AD-051).

آلة الحالات:

```
provisioning → active
active → suspended → active | archived
archived → (حذف منطقي بعد ≥ 30 يوماً عبر EP-AD-058)
أي انتقال غير موجود → 409 illegal_transition
```

`POST /platform/channels` يرد **تحت 500ms**: أدرج الصف `provisioning` + `dispatch` `ProvisionChannelJob` على `provisioning`. الوظيفة: حدود من الخطة، عضوية المدير (`channel_users` + invite واتساب عبر حدث `ChannelManagerInvited` تسمعه Identity)، مستودع افتراضي واحد، أدوار قناة عبر Access. إعادة المحاولة EP-AD-053冪ية على نفس القناة.

KPI في التفصيل/القائمة (`gmv`, `orders_30d`, `active_retailers`): في SP-04 أرجع **0** أو عدّاد الملفات إن وُجدت. لا تخترع أرقاماً. `usage.series` فارغ حتى SP-16 إلا إطار الأيام بصفر.

حذف: EP-AD-058 لا يمسح فوراً — `deletion_request_id` + dual + شرط `archived ≥ 30 days` + تطابق `typed_name` + `password_confirmation` نافذة EP-AD-005 + `otp_code` 2FA.

تصدير EP-AD-057: Job `exports` + تأكيد كلمة المرور.

حدود EP-AD-055: دمج فوق `channel_limits`. `ad.billing.manage` (صلاحية تُبذر في SP-02).

انتقال EP-AD-054: صلاحية `ad.channels.suspend` أو `ad.channels.archive` حسب الهدف.

### 5.2 مسارات — 9

| كود | مسار | إذن |
|---|---|---|
| EP-AD-050 | `GET /platform/channels` | `ad.channels.view` |
| EP-AD-051 | `POST /platform/channels` | `ad.channels.create` |
| EP-AD-052 | `GET /platform/channels/{id}` | view |
| EP-AD-053 | `POST .../retry-provisioning` | `ad.channels.update` |
| EP-AD-054 | `POST .../transition` | suspend/archive حسب الهدف |
| EP-AD-055 | `PUT .../limits` | `ad.billing.manage` |
| EP-AD-056 | `GET .../usage` | view |
| EP-AD-057 | `POST .../export` | `ad.channels.export` crit |
| EP-AD-058 | `DELETE /platform/channels/{id}` | `ad.channels.delete` dual crit |

احذف `/api/v1/admin/channels` و`PUT /api/v1/channel` أو أبقِ إعدادات القناة الذاتية لسبرنت لوحة القناة لاحقاً (ليست ضمن الـ 9 الأصلية). إعدادات «قناة تدخل على نفسها» ليست في كتالوج SP-04 الأصلي.

اختبارات: إنشاء → 201 `provisioning` + Job؛ انتقال `active→archived` مباشرة → 409؛ retry لا يضاعف المدير؛ حذف غير مؤرشف → 422/409.

### 5.3 ملحق ملزم — DOC-12E / DOC-07 (بعد توسّع الكتالوج)

الكتالوج الحالي **338** نقطة لا 250. الإضافات التالية في طبقة 1 **ملزمة** (ملفات `10-platform-12e.php` و`11-platform-doc07.php`). الأشكال في تلك الملفات.

**SP-01 — حساب أدمن المنصة (9) — `identity`**

| كود | مسار |
|---|---|
| EP-AD-159A | `GET /platform/me` |
| EP-AD-159B | `PUT /platform/me` |
| EP-AD-159C | `PUT /platform/me/password` |
| EP-AD-159D | `POST /platform/me/2fa/enable` |
| EP-AD-159E | `POST /platform/me/2fa/confirm` |
| EP-AD-159F | `GET /platform/me/2fa/recovery-codes` |
| EP-AD-159G/H/I | توكنات API: list/create/delete |

يُغلق SP-01 على **26** مساراً لا 17.

**SP-02 — صندوق الاعتماد المزدوج (5) — `access`**

| كود | مسار |
|---|---|
| EP-AD-025 | `GET /platform/iam/approval-requests` |
| EP-AD-026 | `POST /platform/iam/approval-requests/{id}/decide` |
| EP-AD-027 | `POST /platform/iam/roles/preview` |
| EP-AD-028 | `GET /platform/iam/reviews/{id}` |
| EP-AD-029 | `POST /platform/iam/reviews/{id}/items/{itemId}/decide` |

يُغلق SP-02 على **20** مساراً.

**SP-03 — تعديل وتعطيل كل نوع مرجع (14) — `reference`**

| كود | مسار |
|---|---|
| EP-AD-042A…G | `PUT` لكل من governorates, zones, activity-types, root-categories, sale-units, equipments, currencies |
| EP-AD-043A…F | `PATCH .../status` لنفس الأنواع ما عدا المناطق (المناطق لها EP-AD-034) |
| EP-AD-043G | `GET /platform/refs/fx-rates` |

يُغلق SP-03 على **32** مساراً.

**SP-04 — طلبات انضمام، تغطية، مستودعات، خطة جماعية (13) — `tenancy`**

| كود | مسار |
|---|---|
| EP-AD-059A/B | معاينة/تنفيذ تعيين خطة جماعي |
| EP-AD-059C | تصدير قائمة قنوات |
| EP-AD-060/061 | طلبات انضمام قناة + قرار |
| EP-AD-062 | `PUT /platform/channels/{id}` |
| EP-AD-063 | مستخدمو القناة |
| EP-AD-064 | إعادة تعيين مدير |
| EP-AD-065A/B | تغطية جغرافية GET/PUT |
| EP-AD-066 | مستودعات القناة (قراءة) |
| EP-AD-067 | ميزات القناة (قراءة) |
| EP-AD-068 | إشعار مديري قنوات |

يُغلق SP-04 على **22** مساراً.

---

## 6. توجيه موحّد

`bootstrap` أو `core` يسجّل وسيطات. كل موديول يحمّل ملفه:

```
GET    /api/v1/health                              core
/api/v1/public/*                                   identity + reference
/api/v1/platform/auth/*                            identity
/api/v1/platform/iam/*  /audit*                    access
/api/v1/platform/refs/*                            reference
/api/v1/platform/channels*                         tenancy
/api/v1/channel/auth/*                             identity
/api/v1/warehouse/auth/*                           identity
/api/v1/app/retailer/register                      identity
/api/v1/app/rep/register                           identity
/api/v1/app/session  /app/auth/logout              identity
```

وسيط مقترح للمجموعات:

`throttle` → `idempotency` (كتابات) → `auth:{guard}` → `tenant` (channel/warehouse) → `can:{permission}` → `password.confirm` (حين `crit` منصة).

---

## 7. ترتيب البناء داخل الطبقة (لا توازِ عكس التبعيات)

1. SP-00 إغلاق: طوابير، idempotency إلزامي، ترجمة أخطاء، health كما هو.
2. SP-01 جداول الحراس + OTP على العقد الجديد + حذف `/auth/*` القديم.
3. SP-02 كتالوج صلاحيات + Spatie teams + dual + audit GET.
4. SP-03 إعادة مخطط المراجع + `/public/refs`.
5. SP-04 آلة القناة + Job التجهيز (يحتاج 1–3: مستخدم قناة، صلاحيات، مناطق).

---

## 8. ما يُمنع في هذه الطبقة

- أي جدول منتج/سعر/سلة/طلب/مخزون تشغيلي.
- استيراد Eloquent بين الموديولات.
- الإبقاء على `User::$type` كحل دائم.
- صلاحيات `dashboard.view` القديمة بجانب `ad.*` — هجرة ثم حذف.
- `float` للنقود أو لسعر الصرف.
- تنفيذ فوري لتجهيز قناة أو تصدير Excel داخل الـ request.
- مطابقة TikTok/B2C أو مسارات «تقريبية» خارج الكتالوج.
- توسيع `platform-billing` الكامل (اشتراكات، dunning) — خطة خفيفة داخل tenancy فقط.

---

## 9. تعريف «منتهٍ» لكل سبرنت

يُغلق السبرنت عندما:

1. كل صف كتالوج لذلك السبرنت ينجح عبر Pest ضد `/api/v1{path}` بالغلاف الصحيح وأكواد الخطأ المذكورة.
2. Deptrac صفر مخالفات.
3. مجموعة `tenancy` خضراء حيث يوجد `channel_id`.
4. لا مسار قديم مكرر لنفس القدرة.
5. Pint على الملفات الجديدة.

المرجع الشكلي للاستجابة: `docs/api/catalog/*.php` الحقول `b` / `r` / `e`. هذا الملف يحدد **أين تُبنى** و**كيف تُرتَّب** لا أن يستبدل الكتالوج.
