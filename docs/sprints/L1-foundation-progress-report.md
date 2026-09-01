# تقرير طبقة 1 — حالة البناء (SP-00 … SP-04)

**التاريخ:** 1 أيلول 2026  
**العقد:** [`L1-foundation-backend-spec.md`](./L1-foundation-backend-spec.md)  
**الكتالوج الملزم:** `docs/api/catalog/*.php` → `docs/api/b2b-api.catalog.json` (338 نقطة)  
**المسار الكامل:** `/api/v1` + `path` من الكتالوج. الرد داخل غلاف `data` / `error`.  
**URL + Body + Response لكل مسار جاهز:** [`L1-ready-apis.md`](./L1-ready-apis.md)

---

## 1. الخلاصة التنفيذية

| | |
|---|---|
| النطاق | أساس المنصة فقط: `core` `identity` `access` `tenancy` `reference` `integration`. لا منتجات ولا سلة ولا طلبات. |
| المطلوب إغلاقه في الطبقة | **101** مسار كتالوج (SP-00…SP-04 بما فيها ملحق DOC-12E / DOC-07) |
| المبني الآن | **47** مسار جديد حيّ على الحراس الأربعة + health |
| المتبقي | **54** مسار (SP-03 = 32، SP-04 = 22) |
| الاختبارات الخضراء المؤكدة | SP-00 أساس، SP-01 OTP عام (9)، SP-02 IAM + الأدوار (13) |
| الخطوة التالية | SP-03 المراجع ثم حذف المسارات الجغرافية القديمة |

```
████████████░░░░░░░░  ~47% من مسارات الطبقة  (47 / 101)
SP-00 ████  منتهٍ
SP-01 ████  منتهٍ تقريباً (Pest الهوية لم يُعد تشغيله كاملاً بعد إصلاح DB)
SP-02 ████  منتهٍ (13 Pest أخضر)
SP-03 ░░░░  لم يبدأ
SP-04 ░░░░  لم يبدأ
```

---

## 2. لوحة السبرنتات

| سبرنت | موديول | مسارات الكتالوج | الحالة | Pest |
|---|---|---:|---|---|
| **SP-00** إغلاق الأساس | core | 1 | **منتهٍ** | `FoundationTest` + `IdempotencyTest` خضر |
| **SP-01** هوية 4 حراس + تسجيل (يشمل SP-05) + DOC-07 | identity + integration | **26** | **منتهٍ في الكود** | `PublicOtpTest` 9/9 أخضر. باقي ملفات الهوية مكتوبة؛ آخر تشغيل كامل كان قبل استقرار MySQL |
| **SP-02** صلاحيات + تدقيق + صندوق اعتماد | access | **20** | **منتهٍ** | `IamTest` + `RolesPermissionsTest` **13/13** |
| **SP-03** مراجع مشتركة + PUT/status/FX | reference | **32** | **لم يبدأ** | — |
| **SP-04** دورة حياة القناة | tenancy | **22** | **لم يبدأ** | — |
| **المجموع** | | **101** | | |

تعريف «منتهٍ» في المواصفة (§9): كل صف كتالوج أخضر على المسار الكامل + Deptrac صفر + لا سطح مكرر + Pint.  
**SP-00 و SP-02** أقرب للتعريف. **SP-01** الكود والمسارات موجودة؛ يُفضَّل إعادة تشغيل مجموعة `tests/Feature/Identity` كاملة بعد استقرار MySQL. **SP-03/04** لم يُلمسا بعد، والمسارات القديمة ما زالت حيّة.

---

## 3. قرارات مغلقة (لا تُعاد مناقشتها)

- **4 حراس = 4 جداول:** `platform_users` / `channel_users` / `warehouse_users` / `app_users` (`kind: retailer|rep`). جدول `users` هُجِّر ثم **حُذف**.
- هاتف التطبيق **فريد** — نفس الرقم لا يكون تاجراً ومندوباً في SP-01.
- بادئات الكتالوج فقط: `/public` `/platform` `/channel` `/warehouse` `/app/...`.
- صلاحيات الكتالوج: `ad.*` `sc.*` `wh.*` `rt.*` `rp.*`. مصفوفة `dashboard.view` القديمة ما زالت تُبذر **مؤقتاً** حتى تُحذف مسارات الجغرافيا/القناة القديمة في SP-03/04.
- مال: `BIGINT` + `Money` / `MoneyCast`. لا `float`.
- `X-Idempotency-Key` **إلزامي** على الكتابات المحمية → 400 `idempotency_key_required`.
- `Accept-Language`: افتراضي `ar`، `en` مدعوم.
- عبور الموديولات: عقود في `Modules\Core\Contracts` فقط. `identity` يعتمد `core` فقط.
- Sanctum: `config/sanctum.guard = [web]` فقط. وضع الحراس الأربعة هناك يسبب تكراراً لا نهائياً. العزل عبر وسيط `guard.tokenable`.
- Spatie teams: `team_id = channel_id` لأدوار القناة، و`0` للمنصة/العالمي.

---

## 4. ما بُني — تفصيل

### 4.1 SP-00 — الأساس (1 مسار)

| بند | التنفيذ |
|---|---|
| Health | `GET /api/v1/health` → `data.status = ok` |
| Idempotency | `EnsureIdempotency` على مجموعة `api`. المفتاح إلزامي. الاختبارات تحقن UUID تلقائياً عبر `Tests\TestCase::json()` |
| Horizon | `laravel/horizon` + طوابير `default` `notifications` `exports` `provisioning` |
| أخطاء المجال | `DomainException` → غلاف الكتالوج في `bootstrap/app.php` |
| التحقق 422 | `error.code = validation_failed`، الرسالة = أول حقل، `details` = أخطاء الحقول |
| اللغة | وسيط `SetAcceptLanguage` في مقدمة `api` |
| `Modules.old` | محذوف |

### 4.2 عقود Core

| عقد | ينفّذه | يستهلكه |
|---|---|---|
| `OtpChannel` | integration (واتساب/SMS/مركّب) — في الاختبار `FakeOtpChannel` | identity |
| `ChannelDirectory` | tenancy | identity (تسجيل مندوب) |
| `ReferenceDirectory` | reference | identity (منطقة ∈ محافظة، نشاط، تجهيز) |
| `RecordsAudit` | core على `audit_logs` | الكل |
| `AccessCatalog` | access (Spatie + kind للتطبيق) | identity في `/me` و`/session` |
| `WarehouseDirectory` | tenancy | identity (device-login) |
| `AssignsChannelManager` | موجود كعقد — التنفيذ الكامل مع SP-04 | tenancy عند إنشاء قناة |

تحقق HTTP الملزم (§0.1): كتابات الجسم تمتد `ApiFormRequest`، الرسائل من `lang/{ar,en}`، هاتف سوري `SyrianPhone`، أخطاء العمل `InvalidFields::throw`.

### 4.3 SP-01 — الهوية (26 مسار)

**جداول:** أربعة حراس + OTP بـ `otp_id` + تحدي 2FA + أجهزة تطبيق/مستودع + ملفات تاجر/مندوب + عضوية قناة متعددة + جلسات منصة + تأكيد كلمة مرور 15 دقيقة.

**OTP:** تحقق بالمعرّف لا بالهاتف؛ 5 محاولات؛ حدود 3/هاتف و10/جهاز و30/IP في الساعة؛ زائر جديد يأخذ توكن قدرة `registration` فقط.

**مسارات حيّة** (`identity/routes/api.php`):

| مجموعة | المسارات |
|---|---|
| عام | `POST /public/auth/request-otp` `verify-otp` `resend-otp` |
| منصة | login، 2FA verify، logout، me، confirm-password، sessions، revoke session |
| DOC-07 `/platform/me` | GET/PUT الملف، تغيير كلمة المرور، تفعيل/تأكيد 2FA، أكواد الاحتياط، list/create/delete توكنات API |
| قناة | request-otp / verify-otp |
| مستودع | `POST /warehouse/auth/device-login` + أمر `warehouse:register-device` |
| تطبيق | retailer/rep register، session، logout |

**تكامل:** `CompositeOtpChannel` (واتساب ثم SMS). في الاختبار Identity يربط `FakeOtpChannel`. لا شبكة واتساب من Pest.

**حُذف:** `/api/v1/auth/*` القديم ونموذج `User` الموحّد.

### 4.4 SP-02 — الصلاحيات والتدقيق (20 مسار)

- `PermissionCatalog`: **129** رمزاً مستخرجاً من الكتالوج حتى SP-17 (`app-modules/access/bin/extract-permissions.php`) + `ad.billing.manage` و`ad.channels.archive` لـ SP-04.
- أوامر: `php artisan access:sync` و`access:verify` (يفشل CI إن وُجد `permission:` بلا صف كتالوج — حالياً أخضر).
- أدوار مدمجة: `platform_admin` `channel_manager` `sales_manager` `catalog_manager` `accountant` `warehouse_keeper` `retailer` `rep`.
- SoD-01: `sc.orders.confirm` × `sc.finance.payment`. استثناء موثّق: دور `channel_manager` (وإلا مدير القناة الكامل يُرفض عند الإسناد).
- SOD-05: المعتمد ≠ المنشئ → 403 `sod_violation`.
- جداول: `sod_rules` `access_change_requests` `temp_grants` `access_reviews` + items. عمود `audit_logs.channel_id` nullable.
- تصدير التدقيق: `ExportAuditLogJob` على طابور `exports`. العملية نفسها تُدقَّق.

| كود | مسار |
|---|---|
| EP-AD-010 | `GET /platform/iam/permissions` |
| EP-AD-011 | `GET /platform/iam/permissions/{code}/holders` |
| EP-AD-012 | `GET /platform/iam/roles` |
| EP-AD-013 | `POST /platform/iam/roles` (مسودة + طلب اعتماد) |
| EP-AD-014 | `POST /platform/iam/roles/{id}/approve` |
| EP-AD-015 | `PUT /platform/iam/roles/{id}/permissions` |
| EP-AD-016 / 017 | إسناد / سحب أدوار |
| EP-AD-018 | `POST /platform/iam/simulate` — `decision_path[]`: guard → permission → tenant → sod → temp-grant |
| EP-AD-019 / 020 | منح مؤقت + اعتماد |
| EP-AD-021 | `GET /platform/iam/sod-rules` |
| EP-AD-022 / 023 | قائمة التدقيق + تصدير |
| EP-AD-024 | بدء حملة مراجعة |
| EP-AD-025 / 026 | صندوق الاعتماد + بتّ |
| EP-AD-027 | معاينة أثر الدور |
| EP-AD-028 / 029 | تفصيل الحملة + بتّ بند |

---

## 5. ما لم يُبنَ بعد

### 5.1 SP-03 — المراجع (32 مسار) — **التالي**

إعادة محاذاة `governorates` (`name` / `order` / `status`) و`zones` (`district` / `order` / status + polygon). جداول: أنواع نشاط، تصنيفات جذر، وحدات بيع، تجهيزات، عملات، أسعار صرف، استيراد Excel.

| أصلية (18) | ملحق DOC-12E (14) |
|---|---|
| `GET /public/refs` | PUT لكل نوع مرجع (042A…G) |
| CRUD محافظات/مناطق + تعطيل منطقة | PATCH status لكل نوع عدا المناطق (043A…F) |
| activity-types, root-categories, sale-units, equipments | `GET /platform/refs/fx-rates` |
| عملات + FX + استيراد | |

**بعد ثبات المسارات الجديدة:** حذف `/api/v1/governorates` و`/zones` و`/channel/zones`. السطح الحالي ما زال على `auth:sanctum` لذلك Pest الجغرافيا القديمة يرجع **401** على الحراس الأربعة — هذا متوقع ويُغلق بحذف السطح لا بإصلاحه.

جداول مرجعية مشتركة (activity_types, root_categories, sale_units, equipments) **موجودة جزئياً** من SP-01 لتسجيل التاجر؛ لا توجد مسارات منصة `/platform/refs/*` بعد.

### 5.2 SP-04 — القناة (22 مسار)

آلة الحالات: `provisioning → active → suspended → archived`.  
`POST /platform/channels` يرد تحت 500ms: صف `provisioning` + `ProvisionChannelJob` على طابور `provisioning`.

9 مسارات أصلية (قائمة/إنشاء/تفصيل/retry/transition/limits/usage/export/delete) + 13 ملحق (خطة جماعية، طلبات انضمام، PUT قناة، مستخدمون، إعادة مدير، تغطية، مستودعات، ميزات، إشعار).

بعدها: حذف `/api/v1/admin/channels`. إعدادات `/api/v1/channel` ليست من كتالوج SP-04.

السطح الحالي (`admin/channels` + `PUT /channel`) ما زال قديماً على `auth:sanctum`.

---

## 6. الاختبارات

| ملف | آخر نتيجة معروفة |
|---|---|
| `tests/Feature/FoundationTest` + `IdempotencyTest` | أخضر (ضمن SP-00) |
| `tests/Feature/Identity/PublicOtpTest` | **9/9** (أُعيد بعد تشغيل MySQL) |
| `tests/Feature/Identity/{Platform,Channel,Warehouse,GuardIsolation,RetailerRep}Test` | مكتوبة؛ يُعاد تشغيل المجموعة كاملة للاعتماد |
| `tests/Feature/Access/IamTest` + `RolesPermissionsTest` | **13/13** |
| `tests/Feature/GovernorateTest` (قديم) | 401 — السطح القديم `auth:sanctum`؛ يُحذف في SP-03 |
| `access:verify` | أخضر |

**قاعدة الاختبار:** MySQL `b2b_platform_test` على `127.0.0.1:3308` (XAMPP، الخدمة قد تكون متوقفة يدوياً). Pest Feature يستخدم `RefreshDatabase` — بطيء (~12–15 ث لكل ملف IAM بسبب البذر). إن رُفض الاتصال، الاختبارات تسقط كلها بـ `SQLSTATE[HY000] [2002]` وليست فشلاً منطقياً.

تشغيل مفيد:

```bash
php -d memory_limit=512M artisan test --compact tests/Feature/Identity
php -d memory_limit=512M artisan test --compact tests/Feature/Access
php artisan access:verify
```

---

## 7. فجوات إغلاق «منتهٍ» (§9)

| شرط | SP-00 | SP-01 | SP-02 | SP-03 | SP-04 |
|---|---|---|---|---|---|
| كل صف كتالوج Pest على المسار الكامل | نعم تقريباً | نعم في الكود — أعد تشغيل Identity كاملاً | نعم للـ 20 | لا | لا |
| Deptrac صفر | لم يُشغَّل في هذه الجولة | | | | |
| لا مسار قديم مكرر | health فقط | `/auth/*` محذوف | لا تكرار IAM | الجغرافيا القديمة ما زالت | `/admin/channels` ما زال |
| Pint على الملفات الجديدة | لم يُشغَّل في هذه الجولة | | | | |
| AccessMatrix القديمة | — | تُبذر للتوافق | تُبذر بجانب الكتالوج | تُحذف مع المسارات القديمة | |

---

## 8. ترتيب العمل المتبقي (لا يُعكس)

1. إعادة تشغيل `tests/Feature/Identity` كاملة وتثبيت SP-01 على التعريف §9.
2. **SP-03:** مخطط المراجع + `GET /public/refs` + CRUD/PUT/status/import/FX ثم **حذف** `/governorates` `/zones` `/channel/zones`.
3. **SP-04:** آلة الحالة + `ProvisionChannelJob` + الخطة الخفيفة + الملحق 059–068 ثم **حذف** `/admin/channels`.
4. Pint + Deptrac على الملفات الجديدة.
5. إسقاط بذرة `AccessMatrix` بعد اختفاء `can:settings.*`.

الموديولات الأخرى (`catalog` `ordering` …) تبقى هيكلاً فارغاً حتى الطبقات 2–4.

---

## 9. ملاحظة تشغيل محلي

- `.env`: `DB_PORT=3308`. Docker Compose الافتراضي يعرّض MySQL على **3306** وهو غير مستخدم حالياً.
- خدمة Windows `mysql` متوقفة يدوياً؛ التشغيل عبر `C:\xampp\mysql\bin\mysqld.exe` مع `my.ini` (المنفذ 3308).
- لا تضع حراس `platform|channel|warehouse|app` داخل `sanctum.guard`.
