# لوحة إدارة المنصة (السنترال) — مواصفة Next.js 15 + عقد الـ API

| | |
|---|---|
| المستند | **العقد الوحيد** لبناء وإكمال لوحة الأدمن (Next.js 15) على حارس `platform` |
| الجمهور | مطوّر الويب، وCursor AI عند إكمال الجوانب الناقصة، ومراجع القبول |
| الحارس | `auth:platform` · بادئة `/api/v1/platform/*` · `X-Client: platform-web` |
| المنفذ | **3000** — **ليست** لوحة القناة (3001 / `channel`) ولا المستودع |
| البذرة | `admin@platform.sy` / `password` — دور `platform_admin`، 2FA مطفأ |
| التاريخ | 2026-09-19 — من الكنترولر الحي + `docs/status/01-platform-admin.md` + الكتالوج + SRS DOC-03 §3.5 |
| JSON القديم | `docs/DocsLast/platform.json` (2026-09-17) **متخلف**: ما زال يقول `/admin/channels` و«مراجع 404». **هذا الملف يفوز.** |

> **قاعدة الأولوية:** الكود في `app-modules/` ← `docs/status/01-platform-admin.md` ← الكتالوج `01/02/03/10/11-platform-*.php` ← هذا الملف.
> جملة هنا تخالف FormRequest/كنترولر → الكود يفوز.
> شاشة SRS بلا مسار حي → ابنِ الهيكل + `EmptyState`، **لا تختلق أرقاماً** (خصوصاً GMV).

هذا الملف كافٍ لـ Cursor لإكمال لوحة المنصة عندما يصل مستودع Next.js. لا تعتمد على ذاكرة المحادثة.

---

## المحتويات

| § | العنوان |
|---|---|
| 0 | الرموز، المال، البيئة |
| 1 | المنتج — من هو الأدمن وما يغطيه SRS |
| 2 | معمارية Next.js 15 |
| 3 | العقد العابر |
| 4 | الدخول والحساب والجلسات و2FA |
| 5 | الهيكل والتنقّل والصلاحيات |
| 6 | IAM والتدقيق |
| 7 | المرجعيات (محافظات، مناطق، أنشطة، فئات، وحدات، تجهيزات، عملات، صرف) |
| 8 | القنوات |
| 9 | الانترو الافتراضي للمنصة |
| 10 | شاشات SRS غير الحيّة (لوحة، فوترة، فريق، دعم، صحة، إشعارات، قانوني) |
| 11 | جدول كل مسارات الكتالوج |
| 12 | عميل TypeScript |
| 13 | قائمة قبول Cursor |
| 14 | بذور QA |

---

## 0. كيف تقرأ

### 0.1 الرموز

| رمز | المعنى | Next.js |
|---|---|---|
| ✅ | حي على `/platform/…` | `fetch` حقيقي |
| 🟡 | حي بتحفّظ | ابنِ واقرأ التحفّظ |
| ⛔ | 404 اليوم | لا تستدعِ. `EmptyState` + تعطيل الرابط |
| 🔁 | `X-Idempotency-Key` | UUID لكل نيّة مستخدم |
| 🔒 | صلاحية Spatie | أخفِ الزر إن `!can(code)` إلا `platform_admin` |
| 🧩 | SRS يطلبها والخادم ناقص | هيكل الشاشة فقط |

`Gate::before`: دور `platform_admin` يتجاوز كل فحص صلاحية. الواجهة ما زالت تستخدم `can()` لإخفاء عناصر غير الأدمن الكامل.

### 0.2 المال والصرف

- كل مبلغ مالي `int` بأصغر وحدة. الليرة: `12000` → `12,000 ل.س`. **لا ÷ 100.**
- سعر الصرف `int` بسلم ثابت `Money::FX_SCALE = 6`: معدل 1.0 = **`1_000_000`**. لا عمود سلم في الصف. `1500000` = 1.5 ليرة لكل وحدة عملة أجنبية إن كان الزوج كذلك — اعرضه بقسمة واضحة في UI فقط، واحفظ/أرسل العدد الصحيح كما هو.
- **لا تختلق GMV.** قائمة القنوات الحيّة **لا** تُرجع `gmv` ولا `orders_30d`. تفاصيل القناة `kpis.gmv_30d` و`orders_30d` = **0 صادقة** حتى يقرأ Reporting عبر عقد (اليوم أصفار من Tenancy).

### 0.3 الوقت

`Asia/Damascus` (`+03:00`) في الجلسة والتدقيق. `created_at` في مورد قائمة القنوات ISO من الخادم — اعرضه كما هو.

### 0.4 البيئة

| | |
|---|---|
| `NEXT_PUBLIC_API_BASE` | `http://127.0.0.1:8000/api/v1` |
| `X-Client` | `platform-web` |
| توكن | `sessionStorage` مفتاح `platform` (لا تخلطه مع `channel`) |
| مفتاح التكرار | كل كتابة ما عدا `login` و`2fa/verify` |
| `X-Channel-Id` | **لا ترسل** إلا إن وُجد تدفق تبديل مستأجر موثّق — اليوم لا |
| `X-Device-Id` | غير مطلوب على هذا الحارس |

---

## 1. المنتج — لوحة المنصة (AD)

من SRS DOC-03 §3.5 و§2.2: إدارة المنصة فريق تشغيل ودعم، خبرة تقنية مرتفعة، استخدام يومي. ليست لوحة مبيعات قناة.

المبدأ الحاكم للمنصّة كلها: **لا منطق أعمال في الواجهة.** السعر والحالة والصلاحية والحد تُقرَّر في النواة. الأدمن يرى ويعتمد ويعطّل ويُنشئ المستأجر.

### 1.1 متطلبات SRS ↔ الشاشات

| FR | المتطلب | شاشة | حالة API |
|---|---|---|---|
| FR-AD-001 | إنشاء/تفعيل/تعطيل القنوات وإدارة الاشتراكات والحدود | قنوات + حدود | ✅ قنوات وحدود واستخدام. ⛔ باقات واشتراكات وفواتير |
| FR-AD-002 | المرجعيات المشتركة | مراجع | ✅ CRUD + تعطيل |
| FR-AD-003 | العملات وأسعار الصرف والسياسات العامة | عملات + FX + إعدادات | ✅ عملات وFX. ⛔ سياسات عامة (`/settings/*`) |
| FR-AD-004 | لوحة إشراف: قنوات، تجار، مندوبون، طلبات، تداول | لوحة القيادة | ⛔ `GET /platform/dashboard` |
| FR-AD-005 | بحث مستخدم، سجل نشاط، انتحال موثّق | دعم | ⛔ `AP/PA-14` |
| FR-AD-006 | محتوى عام، شروط، خصوصية، إصدارات تطبيقات، انترو | محتوى | ✅ انترو المنصة. ⛔ قانوني/مساعدة/نسخ |
| FR-AD-007 | صحة النظام: طوابير، أخطاء، استجابة، مزامنة | نظام | ⛔ `PA-15` |
| FR-AD-008 | سجل تدقيق لكل تدخل إداري | تدقيق | ✅ `GET/POST /platform/audit` |

ما يزيد على SRS الثمانية وهو **حيّ وإلزامي للتشغيل:** IAM (أدوار، SOD، اعتماد مزدوج، مراجعات)، الملف الشخصي، 2FA، رموز API، الجلسات.

### 1.2 خارج النطاق في الإصدار الأول (SRS §1.2) — لا تبنِ في هذه اللوحة

دفع إلكتروني · محاسبة قيد مزدوج · ERP · تنبؤ AI · لغات غير العربية في الإطلاق (البنية `Accept-Language` موجودة؛ UI عربي فقط).

### 1.3 العزل

مستخدم المنصة يرى كل القنوات عن قصد. مستخدم قناة **لا** يصل إلى `/platform/*` (403 `wrong_guard`). اختبارات العزل على الباك؛ الواجهة لا ترسل توكن `channel` إلى هذه اللوحة.

---

## 2. معمارية Next.js 15

لوحة ويب. **لا Flutter هنا.** App Router + Server Components للقشرة، Client للكتابة والنماذج.

### 2.1 الحزم المقترحة

```
next@15
react@19
@tanstack/react-query
zod
react-hook-form + @hookform/resolvers
axios أو ky
date-fns + date-fns-tz   # Asia/Damascus
lucide-react
sonner                    # توست
```

لا تخلط Redux مع React Query. لا تُولّد أنواعاً يدوياً تخالف هذا الملف — نوع من JSON الحي.

### 2.2 المجلدات

```
src/
  app/
    (auth)/login/page.tsx
    (auth)/2fa/page.tsx
    (app)/layout.tsx              # شريط جانبي + صلاحيات
    (app)/page.tsx                # لوحة: EmptyState حتى PA-16
    (app)/iam/...
    (app)/audit/page.tsx
    (app)/refs/...
    (app)/channels/...
    (app)/content/intro/page.tsx
    (app)/me/...
    (app)/billing/...             # معطّل / empty
    (app)/support/...
    (app)/system/...
    (app)/team/...
    (app)/settings/...
  lib/
    api.ts                        # عميل واحد
    envelope.ts
    money.ts
    fx.ts                         # عرض السلم 10^6
    can.ts
    errors.ts                     # error.code → عربي
    idempotency.ts
  features/                       # وحدات شاشة: hooks + components
  types/api.ts
```

### 2.3 قواعد الواجهة

1. كل `GET` عبر React Query. المفتاح يتضمن المرشّحات.
2. كل كتابة: `useMutation` + مفتاح تكرار يُولَّد عند الضغط ويُعاد مع إعادة المحاولة.
3. 403 `requires_password_confirm`: مودال كلمة السر → `POST /platform/auth/confirm-password` → أعد **نفس** المفتاح والجسم.
4. 403 `insufficient_permission`: لا تعِد المحاولة كم Moك.
5. 404: «غير موجود».
6. 409 `illegal_transition`: اعرض `details.allowed_next` إن وُجد.
7. 204: نجاح بلا JSON (حذف قناة).
8. لا روابط `/channel/*` أو `/warehouse/*` أو `/app/*`.
9. RTL: `dir="rtl"` `lang="ar"` على `html`. أرقام غربية `tabular-nums`.
10. إجراءات حساسة (تعطيل مرجع، انتقال قناة، حذف): حوار سبب إلزامي قبل الإرسال.

### 2.4 `can(code)`

```ts
export function can(user: Me, code: string): boolean {
  if (user.roles.includes('platform_admin')) return true
  return user.permissions.includes(code)
}
```

عنصر تنقّل بلا صلاحية: لا ترسمه. شاشة ⛔: ارسمها معطّلة بجملة «غير متاح في الـ API بعد» إن كان الدور يراها في التصميم، وإلا اخفِها.

---

## 3. العقد العابر

### 3.1 الغلاف

نجاح: `{ data, meta: { server_time } }`  
قائمة: `meta.page / per_page / total / last_page` — افتراضي 25 سقف 100.  
خطأ: `{ error: { code, message, permission?, details? } }`

لا payload تحقق Laravel النيء. فرّع على `error.code`.

### 3.2 الترويسات

```
Accept: application/json
Accept-Language: ar
Content-Type: application/json
X-Client: platform-web
Authorization: Bearer <token>     # ما عدا login و 2fa/verify و GET /health
X-Idempotency-Key: <uuid>         # كل كتابة إلا login و 2fa/verify
```

### 3.3 أخطاء شائعة

| HTTP | code | عربي | سلوك |
|---|---|---|---|
| 401 | `unauthenticated` | بيانات غير صحيحة / انتهت الجلسة | دخول: جملة عامة. غير الدخول: إلى `/login` |
| 401 | `otp_invalid` | رمز 2FA غير صحيح | ابقَ على شاشة 2FA |
| 403 | `wrong_guard` | توكن تطبيق/قناة | اخرج |
| 403 | `insufficient_permission` | لا صلاحية | `error.permission` |
| 403 | `requires_2fa` | — | الكتالوج يذكره؛ الحيّ يعيد 200 مع `requires_2fa: true` بلا توكن |
| 403 | `requires_password_confirm` | أكّد كلمة المرور | مودال ثم إعادة |
| 403 | `sod_violation` | تعارض فصل مهام | أخفِ إعادة المحاولة لنفس الممثّل |
| 404 | `not_found` | غير موجود | |
| 409 | `illegal_transition` | الانتقال غير مسموح | |
| 409 | `ref_in_use` | المرجع مستخدم | اعرض `affected` |
| 409 | `idempotency_key_conflict` | مفتاح بجسم مختلف | |
| 422 | `validation_failed` | الحقول | `error.details` |
| 429 | `rate_limited` | انتظر | تصدير |

### 3.4 `EmptyState` لـ ⛔

عنوان عربي + «المسار غير مبني بعد (`PA-xx`)» + لا سكيلتون أرقام. **ممنوع** وضع أرقام الكتالوج المثال في الإنتاج.

---

## 4. الدخول والحساب

### 4.1 صحة ✅

`GET /api/v1/health` — قد يرجع `status: "degraded"` مع 200. لا شاشة.

### 4.2 دخول ✅ — معفى من التكرار

`POST /api/v1/platform/auth/login`

```json
{ "email": "admin@platform.sy", "password": "password" }
```

بذرة بلا 2FA:

```json
{
  "data": {
    "token": "1|platform_xxxxx",
    "user": {
      "id": 1,
      "name": "منصّة",
      "email": "admin@platform.sy",
      "roles": ["platform_admin"],
      "permissions": ["ad.iam.view_catalog", "ad.channels.view"]
    },
    "expires_at": "2026-09-20T13:00:00+03:00"
  }
}
```

إن 2FA مفعّل (ليس البذرة):

```json
{ "data": { "requires_2fa": true, "challenge_token": "cht_8f3a" } }
```

لا توكن بعد. خزّن `challenge_token` في حالة الراوتر فقط (10 دقائق، 5 محاولات). خطأ الدخول 401 عام — «بيانات غير صحيحة» (لا تفرّق إيميل/كلمة). لا autologout على `expires_at`.

### 4.3 تحقق 2FA ✅ — معفى

`POST /platform/auth/2fa/verify`

```json
{ "challenge_token": "cht_8f3a", "code": "123456" }
```

6 خانات، `dir=ltr`. يقبل TOTP أو رمز استعادة. الرد نفس شكل إصدار الجلسة. 401 `otp_invalid`.

### 4.4 أنا ✅

`GET /platform/me` و`GET /platform/auth/me` — نفس الشكل تقريباً. بعد الدخول استدعِ `GET /platform/me` لتحديث الصلاحيات.

```json
{
  "data": {
    "id": 1,
    "name": "منصّة",
    "email": "admin@platform.sy",
    "phone": null,
    "roles": ["platform_admin"],
    "permissions": ["…"],
    "two_factor_enabled": false,
    "last_login_at": "2026-09-19T13:00:00+03:00"
  }
}
```

`PUT /platform/me` 🔁 `{ "name": "…", "phone": "+9639…" }` — **أرسل الاسم دائماً.** رد `{ updated: true }` ثم أعد GET. الهاتف سوري يُطبَّع في الخادم.

### 4.5 كلمة المرور ✅ 🔁

`PUT /platform/me/password` `{ current_password, password, password_confirmation }` `min:8`.

تدفق: 403 `requires_password_confirm` → `POST /platform/auth/confirm-password` `{ password }` → `{ confirmed_until }` نافذة 15د → أعد PUT **بنفس** المفتاح. كلمة التأكيد غلط = 401.

### 4.6 تفعيل 2FA ✅ 🔁

1. `POST /platform/me/2fa/enable` بلا جسم → `secret` (رابط `otpauth://`) و`qr_svg` **فارغ**. ارسم QR من `secret`.
2. 2FA ليس مفعّلاً بعد.
3. `POST /platform/me/2fa/confirm` `{ code }` → `enabled` + **8 رموز استعادة مرة واحدة**. لا تغلق الحوار قبل النسخ/التنزيل.
4. `GET /platform/me/2fa/recovery-codes` → `{ codes_remaining }` فقط.
5. رمز غلط = `401 otp_invalid` و2FA يبقى مغلقاً.

### 4.7 رموز API ✅

`GET /platform/me/api-tokens`  
`POST` 🔁 `{ name, password_confirmation }` — كلمة السر الحالية **ليست** نافذة الـ 15 دقيقة. التوكن يظهر مرة.  
`DELETE /platform/me/api-tokens/{id}` 🔁  

403 هنا ≠ مودال confirm-password العام.

### 4.8 الجلسات ✅

`GET /platform/auth/sessions` — بلا صفحات، الأحدث أولاً: `{ id, ip, agent, last_active_at, current }`.  
`DELETE /platform/auth/sessions/{id}` 🔁 — لا تلغِ `current` بلا حوار.  
`POST /platform/auth/logout` 🔁 جسم `{}` → امسح `sessionStorage.platform`.

---

## 5. الهيكل والتنقّل

بعد الدخول: شريط جانبي. أخفِ ما لا `can` وما هو ⛔ إلا إن التصميم يصرّ على عنصر معطّل.

| بند | شرط | § |
|---|---|---|
| نظرة عامة | — | EmptyState حتى لوحة ⛔ |
| قنوات | `ad.channels.view` | 8 |
| مراجع | `ad.refs.view` | 7 |
| عملات وصرف | `ad.refs.currency` | 7.8 |
| IAM صلاحيات | `ad.iam.view_catalog` | 6 |
| أدوار | نفسها | 6 |
| تعيينات | `ad.iam.role_assign` | 6 |
| محاكاة | `ad.iam.simulate` | 6 |
| منح مؤقت | `ad.iam.grant_temp` | 6 |
| SOD | `ad.iam.sod_rules` | 6 |
| اعتمادات | `ad.iam.view_catalog` | 6 |
| مراجعات | `ad.audit.review` | 6 |
| تدقيق | `ad.audit.view` | 6 |
| انترو التطبيقات | `ad.content.view` | 9 |
| فوترة / باقات | `ad.billing.*` | 10 ⛔ |
| فريق | `ad.team.view` | 10 ⛔ |
| دعم | `ad.support.*` | 10 ⛔ |
| نظام | `ad.system.view` | 10 ⛔ |
| إشعارات منصة | `ad.notify.*` | 10 ⛔ |
| حسابي | — | 4 |

لا عنصر «قناة النور» يفتح `/channel`.

---

## 6. IAM والتدقيق — 20/20 ✅

كل الأزرار خلف `can()`. لا `sort` إلا قائمة الأدوار: `sort=id|name|created_at` (أو `-`).

### 6.1 كتالوج الصلاحيات 🔒 `ad.iam.view_catalog`

`GET /platform/iam/permissions?page=&per_page=&filter[system]=platform|channel|warehouse|app&filter[severity]=&search=`

`search` على الرمز أو `name_ar`.

صف: `{ code, name_ar, system, module, severity, dual_approval, delegatable, roles_count, users_count }`.

~134 رمزاً مزروعاً (DOC-08: 170) — الناقص ليس عطلاً. `dual_approval: true` → التنفيذ عبر صندوق الاعتماد لا الزر المباشر.

`GET /platform/iam/permissions/{code}/holders` — **ليس** غلاف قائمة صفحات.

```json
{
  "data": {
    "roles": [{ "id": 1, "key": "platform_admin" }],
    "users": [{ "id": 1, "name": "#1" }],
    "recent_usage": [{ "at": "…", "actor": 1, "action": "simulate" }]
  }
}
```

🟡 `users[].name` غالباً `"#"+id`. سقف 50 صامت. رمز مجهول 404.

### 6.2 الأدوار

`GET /platform/iam/roles` صفحات: `{ id, key, name, system, status, is_builtin, permissions_count, users_count }`.  
لا حذف لـ `is_builtin`.

**معاينة** 🔁: `POST /iam/roles/preview` `{ permissions }` عند كل تغيير. اطبع `summary_ar` كما هو. اعرض `sod_conflicts`.

**إنشاء** 🔁: `POST /iam/roles`

```json
{
  "name": "مشرف كتالوج",
  "key": "catalog_supervisor",
  "system": "channel",
  "description": "صلاحيات الكتالوج دون المالية",
  "permissions": ["sc.catalog.view", "sc.catalog.create"],
  "copy_from_role_id": null
}
```

201 `{ id, status: "draft", sod_conflicts }`. الدور **لا يمنح** حتى اعتماد ثانٍ. أبرز `draft`.

**اعتماد** 🔁 🔒 `ad.iam.role_approve`: `POST /iam/roles/{id}/approve` `{ reason }` 3–500. المنشئ → `403 sod_violation` — أخفِ الزر له.

**صلاحيات** 🔁: `PUT /iam/roles/{id}/permissions` **استبدال كامل** `{ permissions, reason }` → `{ changed, sod_conflicts }`. نظام الصلاحية ≠ نظام الدور → 422.

### 6.3 التعيينات 🔒 `ad.iam.role_assign`

`POST /iam/assignments` 🔁 `{ user_ids, role_id, expires_at?, reason }` حد 50.  
**200 عند نجاح جزئي** — ارسم `assigned` و`rejected[].reason`.

`DELETE /iam/assignments` 🔁 **بجسم** `{ user_id, role_id, reason }`. بلا جسم = 422.

⛔ لا `GET /platform/team` — حقل معرّف مستخدم حتى PA-10.

### 6.4 المحاكي 🔒 `ad.iam.simulate`

`POST /iam/simulate` 🔁

```json
{
  "user_type": "channel",
  "user_id": 10,
  "permission": "sc.orders.confirm",
  "resource_type": "sub_order",
  "resource_id": 9001
}
```

خمس طبقات بالترتيب: `guard` · `permission` · `tenant` · `sod` · `temp-grant` (`pass|fail|skip`).

`allowed = guard && (permission || temp-grant) && !sod`.  
🟡 طبقة `tenant` **ثابتة pass** اليوم — اكتب ذلك في الواجهة.

### 6.5 منح مؤقت 🔒 `ad.iam.grant_temp`

`POST /iam/temp-grants` 🔁 `{ user_id, permission, duration_minutes (1–240), reason }` — **reason ≥ 20**.  
→ `pending_approval` + `expires_request_at` (مهلة الاعتماد لا المنحة).

`POST /iam/temp-grants/{id}/approve` 🔁 `{ decision: "approve"|"reject", reason }`. الطالب لا يعتمد.

### 6.6 SOD 🔒 `ad.iam.sod_rules`

`GET /iam/sod-rules` قراءة بلا صفحات: `{ code, permission_a, permission_b, reason, exceptions }`. حمّل مرة. لا إنشاء.

### 6.7 صندوق الاعتماد

`GET /iam/approval-requests` صفحات · `filter[status]` · `filter[type]`. `payload` JSON حر — عارض عام.

`POST …/{id}/decide` 🔁 `{ decision: "approve"|"reject", reason }` → اقرأ **`executed`** لا `status` وحده.  
اعتماد بلا تنفيذ: `approved` + `executed: false`. تكرار → 409. اعتماد النفس → 403.

### 6.8 مراجعات الوصول 🔒 `ad.audit.review`

`POST /iam/reviews` 🔁 `{ quarter, scope }` → `{ campaign_id, items_count }`.  
`GET /iam/reviews/{id}` **بلا صفحات**. `decision: null` = غير محسوم.  
`POST …/items/{itemId}/decide` 🔁 `{ decision: "keep"|"revoke", reason }` — **ليس** approve/reject.

### 6.9 التدقيق 🔒 `ad.audit.view`

`GET /platform/audit` صفحات. المرشّحات داخل **`filter`**: `actor` · `action` · `channel_id` · `date_from` · `date_to`.

صف: `{ at, actor, action, entity_type, entity_id, before, after, ip, impersonated }`. شارة إن `impersonated`.

`POST /platform/audit/export` 🔁 🔒 `ad.audit.export` جسم **`filters`** (جمع). رد `{ job_id }`.  
اعرض «في الانتظار» **وتوقف**. ⛔ لا `GET /platform/exports/{jobId}` على هذا الحارس بعد. بلا Horizon لا ملف.

---

## 7. المرجعيات — 31/31 ✅ 🔒

القاعدة 12: **لا حذف صلب.** تعطيل منطقي + سبب + تدقيق. شاشة التعطيل تعرض `affected` (أعداد عبر القنوات — هذا المقصود من الأدمن).

`search` و`filter[status]` حيث يدعمها الكنترولر. `per_page` ≤ 100.

مسارات `GET /platform/refs/{entity}/{id}` حيّة **خارج الكتالوج** — مسموح لتفاصيل الصف. لا تختلق DELETE.

### 7.1 المحافظات 🔒 `ad.refs.view|create|update|disable`

`GET /platform/refs/governorates`

صف حيّ:

```json
{
  "id": 1,
  "name": "دمشق",
  "name_ar": "دمشق",
  "name_en": "Damascus",
  "code": "DI",
  "status": "active",
  "order": 1,
  "zones_count": 18
}
```

🟡 **إنشاء لا يطابق مثال الكتالوج `name`.** الجسم الحي (FormRequest):

```json
{
  "name_ar": "ريف دمشق",
  "name_en": "Rif Dimashq",
  "code": "RD",
  "order": 2,
  "reason": "إضافة محافظة"
}
```

`POST` 🔁 🔒 `ad.refs.create` → 201 مورد كامل.  
`PUT /platform/refs/governorates/{id}` 🔁 `{ name_ar?, name_en?, code?, order?, reason }` — **reason إلزامي**.  
`PATCH …/status` 🔁 `{ status: "disabled"|"active", reason }` — reason إلزامي. رد فيه `affected` (zones / retailers / channels كأعداد).

### 7.2 المناطق 🔒

`GET /platform/refs/zones?filter[governorate_id]=1`

صف: `{ id, governorate_id, governorate: { id, name }, name, district, polygon, order, status, retailers_count, reps_count, channels_count }`.

🟡 `status` في العقد `disabled` بينما العمود قد يخزّن `inactive` — المورد يترجم. أرسل في PATCH `disabled` كما في العقد.

`POST` 🔁 `{ governorate_id, name, district?, polygon?, order?, reason? }` — **لا `status` في الإنشاء.**  
`PUT` + `reason` إلزامي على التعديل.  
`PATCH /zones/{id}/status` 🔁 `{ status, reason }` → `affected: { retailers, reps, open_orders }`.

### 7.3 أنواع النشاط

قائمة + `POST` `{ name, icon?, description?, suggested_category_ids[], order?, reason? }`  
`PUT` + reason. `PATCH …/status` → `affected`.

### 7.4 الفئات الجذر

قائمة + إنشاء `{ name, image?, icon?, activity_type_ids[], order? }`.  
تعطيل قد 409 `ref_in_use` إن تحتها فئات/منتجات نشطة — اعرض العدد.

### 7.5 وحدات البيع

`{ name, abbr, default_factor }` عند الإنشاء. تعطيل 409 إن مستخدمة في منتج.

### 7.6 التجهيزات

`{ name, icon?, description? }`.

### 7.7 العملات 🔒 `ad.refs.currency`

صف قائمة: `{ id, iso, name, decimals, is_display_currency }`.  
إنشاء: `{ iso` (3 أحرف تُرفع)، `name, decimals` إلزامي 0–4، `symbol?`, `is_display_currency?` }. **لا `is_base`.**  
SYP `decimals = 0`. لا تعطّل عملة العرض دون بديل واضح في الحوار.

### 7.8 أسعار الصرف ✅

`GET /platform/refs/fx-rates?filter[currency_id]=2`  
`POST` 🔁

```json
{
  "currency_id": 2,
  "rate": 1500000,
  "effective_from": "2026-03-01T00:00:00+03:00",
  "source": "manual",
  "reason": "تسعير اليوم"
}
```

`rate` **integer ≥ 1** عند السلم 10^6. لا يعيد تسعير طلبات مفتوحة (BR-AD-19) — اكتب التنويه في النموذج.

### 7.9 استيراد ✅ 🔒 `ad.refs.import`

`POST /platform/refs/import` 🔁 `multipart`: `type` (مثل `zones`) + `file` + `dry_run`.  
رد: `{ preview[], errors[] }`. شغّل `dry_run=true` أولاً. لا تطبّق إن `errors` غير فارغة دون تأكيد.

---

## 8. القنوات — 9 مسارات حيّة ✅

الثابت الوحيد: `CHANNELS_BASE = '/platform/channels'`  
(PA-01 أنجز النقل من `/admin/channels`. إن بقي في الفرونت `/admin/channels` **استبدله**.)

حالات الآلة — الأزرار من `allowed_next` فقط:

```
provisioning → [active]
active       → [suspended]
suspended    → [active, archived]
archived     → []
```

لا زر `active → archived`. الانتقال إلى نفس الحالة ليس انتقالاً.

### 8.1 القائمة 🔒 `ad.channels.view`

`GET /platform/channels?page=&per_page=`

🟡 الكتالوج يذكر `filter[status|plan_id|governorate_id|…]` — **الكنترولر لا يطبّقها اليوم** (ترتيب بالاسم + صفحات). لا تعتمد على الفلتر حتى يُبنى. افلتر في العميل إن لزم للتجربة فقط بوضوح أنه محلي.

مورد القائمة الحيّ **أضيق من الكتالوج:**

```json
{
  "id": 1,
  "name": "شركة النور",
  "slug": "al-nour",
  "legal_name": null,
  "tax_number": null,
  "phone": null,
  "email": null,
  "status": "active",
  "allowed_next": ["suspended"],
  "settings": null,
  "created_at": "2026-09-01T00:00:00+00:00"
}
```

لا `gmv` لا `plan` لا `active_retailers`. لا تختلقها.

### 8.2 إنشاء 🔁 🔒 `ad.channels.create`

`POST /platform/channels` → 201 تحت 500ms وما زال `provisioning`. **لا ترسل `status`.**

```json
{
  "name": "شركة الشام",
  "slug": "al-sham",
  "legal_form": "llc",
  "cr_number": "C12345",
  "documents": [],
  "governorate_ids": [1],
  "zone_ids": [12, 13],
  "activity_type_ids": [3, 4],
  "logo": null,
  "internal_note": "شراكة تجريبية",
  "plan_id": 1,
  "billing_cycle": "yearly",
  "trial_days": 14,
  "limits": {
    "users": 25,
    "warehouses": 2,
    "reps": 20,
    "skus": 5000,
    "storage_mb": 2048
  },
  "custom_discount": 0,
  "manager": {
    "name": "محمد علي",
    "phone": "+963944000000",
    "email": "manager@alsham.sy",
    "invite_via": "whatsapp"
  }
}
```

`plan_id` يجب أن يكون باقة `is_active` في `channel_plans`. `billing_cycle`: `monthly|yearly`. `slug` `alpha_dash` فريد.

```json
{ "data": { "id": 9, "status": "provisioning", "provisioning_job_id": "job_prov_9" } }
```

لا تستطلع حتى `active` في هذا الطلب. `manager` في التفاصيل يبقى `null` حتى تذكرة الدعوة.

### 8.3 التفاصيل 🔒 `ad.channels.view`

`GET /platform/channels/{id}`

```json
{
  "data": {
    "channel": {
      "id": 1,
      "name": "شركة النور",
      "slug": "al-nour",
      "status": "active",
      "allowed_next": ["suspended"],
      "legal_form": "llc",
      "cr_number": "C12345"
    },
    "manager": null,
    "subscription": { "plan": "growth", "status": "trial", "next_renewal": "…" },
    "limits": { "users": 25, "warehouses": 2, "reps": 20, "skus": 5000, "storage_mb": 2048 },
    "coverage": { "governorate_ids": [1], "zone_ids": [12, 13] },
    "kpis": { "gmv_30d": 0, "orders_30d": 0 },
    "timeline": [{ "at": "…", "event": "created" }],
    "internal_notes": []
  }
}
```

أزرار الانتقال = `allowed_next` ∩ `can('ad.channels.suspend')`. أرشفة تحتاج أيضاً منطق `ad.channels.archive` داخل الإجراء — إن 403 أخفِ.

تبويبات التصميم (مستخدمون، تغطية محرّر، مستودعات، ميزات): ⛔ — `EmptyState` داخل البطاقة. التغطية **للقراءة** من `coverage` أعلاه فقط لا `GET …/coverage`.

### 8.4 تحديث 🔁 🔒 `ad.channels.update`

`PUT /platform/channels/{id}` جزئي + **`reason` إلزامي max 255**. لا `status`.

```json
{ "name": "شركة النور المحدودة", "reason": "تصحيح السجل" }
```

### 8.5 انتقال 🔁 🔒 `ad.channels.suspend`

`POST …/{id}/transition`

```json
{ "to_status": "suspended", "reason": "تجاوز شروط التشغيل" }
```

اسم حالة مجهول → 422. حالة غير مسموحة من المصفوفة → 409 `illegal_transition` مع `details.allowed_next`.  
نجاح: `{ status, allowed_next }`.

تعليق قناة نشطة يجمّد الطلبات الجديدة فقط (BR-AD-14) — اكتبه في حوار التأكيد.

### 8.6 إعادة تجهيز 🔁 🔒 `ad.channels.update`

`POST …/{id}/retry-provisioning` جسم `{}` → `{ job_id }`. آمن للتكرار. «في الانتظار» بلا استطلاع.

### 8.7 حدود 🔁 🔒 `ad.billing.assign_plan`

`PUT …/{id}/limits`

```json
{
  "limits": { "reps": 40 },
  "temporary_until": null,
  "reason": "موسم رمضان"
}
```

مفتاح واحد على الأقل من: `users|warehouses|reps|skus|storage_mb`. أعداد ≥ 0. `temporary_until` إن وُجد يجب أن يكون في المستقبل. الرد = الحدود الفعّالة بعد التغيير.

### 8.8 الاستخدام ✅

`GET …/{id}/usage?range=30d` — `7d|30d|90d`.

```json
{
  "data": {
    "range": "30d",
    "series": [{ "day": "2026-09-01", "orders": 0, "gmv": 0 }],
    "limit_usage": {
      "users": { "used": 3, "limit": 25 },
      "warehouses": { "used": 1, "limit": 2 },
      "reps": { "used": 2, "limit": 20 },
      "skus": { "used": 40, "limit": 5000 }
    },
    "failed_jobs": 0,
    "sync_status": "healthy"
  }
}
```

سلسلة أصفار = قناة جديدة. لا خطأ. `gmv` هنا من عقد الطلبات إن وُجد وإلا 0.

### 8.9 حذف 🔁 🔒 `ad.channels.delete`

`DELETE /platform/channels/{id}` → **204 بلا جسم.** حوار «نهائي».  
🟡 الكتالوج يريد أرشيفاً ≥ 30 يوماً + كلمة سر + OTP + كتابة الاسم + معتمد ثانٍ (**PA-18 ناقص**). اليوم حذف مباشر. لا تعد بأن الاستعادة ممكنة.

### 8.10 قنوات — ⛔ لا تبنِ استدعاء

| EP | مسار | تذكرة |
|---|---|---|
| 057 | `POST …/{id}/export` | PA-17 |
| 059A/B | `bulk-plan` | PA-06 |
| 059C | `POST /platform/channels/export` | PA-17 |
| 060/061 | طلبات انضمام | PA-04 |
| 063 | مستخدمو القناة | PA-03 |
| 064 | إعادة مدير | PA-05 |
| 065A/B | GET/PUT تغطية | PA-03 |
| 066 | مستودعات | PA-03 |
| 067 | ميزات القناة | PA-08 |
| 068 | إشعار مديرين | PA-13 |
| 102 | تعيين خطة | PA-06 |

---

## 9. الانترو الافتراضي للمنصة ✅

FR-AD-006 / تطبيقات المندوب والتاجر: انترو يُدار من لوحة التحكم.

`GET /platform/content/intro` 🔒 `ad.content.view`  
`PUT /platform/content/intro` 🔁 🔒 `ad.content.manage`

صف شاغر:

```json
{
  "data": {
    "enabled": false,
    "text": null,
    "media_type": null,
    "media_id": null,
    "duration": 0,
    "targeting": { "activity_type_ids": [], "zone_ids": [] }
  }
}
```

PUT:

```json
{
  "enabled": true,
  "text": "مرحباً بك في شبكة التوزيع",
  "media_type": "video",
  "media_id": "media_intro_default",
  "duration": 8,
  "targeting": { "activity_type_ids": [3], "zone_ids": [12] }
}
```

`media_type`: `image|video`. **PUT يعيد `{ enabled }` فقط** — أعد GET للنموذج.

`targeting.activity_type_ids` و`targeting.zone_ids` قوائم أعداد — في السنترال قوائم متعددة من `GET /platform/refs/activity-types` و`GET /platform/refs/zones` (تجميع بالمحافظة). مصفوفة فارغة = بلا تقييد.

هذا **ليس** `GET /channel/content/intro` (لوحة القناة). تطبيق المندوب **لا** يستدعي `/platform/content/intro` (`wrong_guard`) — انترو التطبيق محلي حتى `GET /public/app-config`.

✅ `status/01` EP-AD-141A/B حيّان (Content). قانوني/مساعدة/نسخ ما زالت ⛔.

⛔ قانوني، مساعدة، نسخ تطبيقات: §10.

---

## 10. شاشات SRS غير الحيّة — ابنِ الهيكل ولا تستدعِ

اربط كل شاشة بتذكرة `PA-*`. عندما ينقلب `status/01` إلى ✅ فكّ `RemoteNotReady`.

### 10.1 لوحة الإشراف ⛔ PA-16 — FR-AD-004

عقد الكتالوج `GET /platform/dashboard` (لا تستدعِه):

```json
{
  "cards": {
    "channels": { "active": 48, "suspended": 2 },
    "retailers": { "active": 12400 },
    "reps": { "on_duty": 310 },
    "orders": { "today": 1860 },
    "gmv": { "today": 420000000, "month": 9800000000 },
    "platform_revenue": { "month": 185000000 },
    "integrations_health": { "whatsapp": "ok", "sms": "degraded" },
    "sync": { "pending_operations": 12 }
  },
  "alerts": [{ "type": "channel_idle", "count": 3, "action_url": "/platform/channels?filter[idle]=1" }],
  "charts": { "daily_gmv": [], "adoption": [] }
}
```

أيضاً: `GET /dashboard/alerts` · `cards/{key}` · `charts/{key}` · `GET /reports/{type}` حيث `type ∈ gmv|adoption|operations|growth|quality` · تصدير + `GET /exports/{jobId}`.

**اليوم:** صفحة ترحيب بعد الدخول: عدد القنوات من `GET /platform/channels` `meta.total` فقط. لا GMV.

### 10.2 الباقات والاشتراكات والفواتير ⛔ PA-02/06/07 — FR-AD-001 جزء

`GET/POST /platform/plans` · `GET/PUT /plans/{id}`  
حدود الكتالوج: `users, warehouses, reps, skus, storage_mb, otp_monthly` + `features[]` + `on_exceed: warn|block|manual_approval` + أسعار int + `trial_days`.

`GET /subscriptions` · `POST /channels/{id}/plan` · `GET /platform-invoices` · waive (مزدوج + SOD) · credit-note · dunning · `GET /billing/revenue`.

`PUT /channels/{id}/limits` **حيّ** ويمكنه تغطية فرع «حدود» في بطاقة القناة دون شاشة باقات.

### 10.3 الفريق ⛔ PA-10

`GET /platform/team` · دعوات 72س · تعطيل/حذف. آخر `platform_admin` لا يُحذف (409).

### 10.4 الدعم ⛔ PA-14 — FR-AD-005

بحث · بطاقة 360 `GET /support/users/{type}/{id}` · انتحال ≤ 15د `impersonated` في التدقيق · إعادة OTP · سحب جلسات · تذاكر سبع حالات.

### 10.5 صحة النظام ⛔ PA-15 — FR-AD-007

طوابير Horizon · آخر أخطاء · sync (اليوم سيبقى `pending_operations: 0` بصدق) · تكاملات · تبديل قناة OTP · إعادة jobs · صيانة (حرج + مزدوج → 503 على الكتّاب) · مهام مجدولة · تخزين · نسخة احتياطية.

### 10.6 الإشعارات والحملات ⛔ PA-13

بث حرج مزدوج · إنشاء · معاينة مستلمين · قوالب · سجل · حملات.

### 10.7 قانوني ومساعدة ونسخ تطبيقات ⛔ PA-12/09 — FR-AD-006 ما عدا الانترو

`GET/POST /content/legal` (نسخة جديدة عند النشر) · help · `GET/POST /app-versions` · force-update مزدوج · `GET /public/app-config`.

### 10.8 إعدادات المنصة ⛔ PA-11 — FR-AD-003 سياسات

ملف عام · أمان (2FA إلزامي للأدوار، مهلة جلسة) · نسخ · افتراضات قنوات · تكاملات (الأسرار تُكتب وتُقرأ `***`) · test مزوّد.

### 10.9 الميزات ⛔ PA-08

أعلام عالمية · تجاوز لكل قناة · أسبقية: تجاوز ← باقة ← عالمي.

---

## 11. جدول المسارات

الأساس `/api/v1`. 🔁 = مفتاح تكرار. الحالة من الكود الحي 2026-09-19: **78✅** كتالوج على `/platform` (منها انترو 141A/B) + 7 GET refs/{id} خارج الكتالوج. `status/01`: 78 حيّ / 88 ناقص / 0 منحرف.

### 11.1 دخول وملف — 16/16 ✅

| EP | طريقة | مسار | 🔁 |
|---|---|---|---|
| AD-001 | POST | `/platform/auth/login` | لا |
| AD-002 | POST | `/platform/auth/2fa/verify` | لا |
| AD-003 | POST | `/platform/auth/logout` | ✔ |
| AD-004 | GET | `/platform/auth/me` | |
| AD-005 | POST | `/platform/auth/confirm-password` | ✔ |
| AD-006 | GET | `/platform/auth/sessions` | |
| AD-007 | DELETE | `/platform/auth/sessions/{id}` | ✔ |
| AD-159A | GET | `/platform/me` | |
| AD-159B | PUT | `/platform/me` | ✔ |
| AD-159C | PUT | `/platform/me/password` | ✔ |
| AD-159D | POST | `/platform/me/2fa/enable` | ✔ |
| AD-159E | POST | `/platform/me/2fa/confirm` | ✔ |
| AD-159F | GET | `/platform/me/2fa/recovery-codes` | |
| AD-159G | GET | `/platform/me/api-tokens` | |
| AD-159H | POST | `/platform/me/api-tokens` | ✔ |
| AD-159I | DELETE | `/platform/me/api-tokens/{id}` | ✔ |

### 11.2 IAM وتدقيق — 20/20 ✅

AD-010…029 كما في `status/01` (permissions, roles, assignments, simulate, temp-grants, sod, reviews, approval-requests, audit, export).

### 11.3 مراجع — 31/31 ✅ + 7 show خارج الكتالوج

AD-030…043G على `/platform/refs/*`.  
إضافي حي: `GET …/governorates/{id}` ونظائره للعملات والمناطق والأنشطة والفئات والوحدات والتجهيزات.

### 11.4 قنوات حيّة

AD-050 قائمة · 051 إنشاء · 052 تفاصيل · 053 إعادة تجهيز · 054 انتقال · 055 حدود · 056 استخدام · 058 حذف · 062 تحديث.  
كلها تحت `/platform/channels`.

### 11.5 محتوى جزئي

AD-141A/B انترو ✅ (حتى لو مولّد الحالة تخلّف). الباقي ⛔.

### 11.6 الباقي ⛔

فوترة AD-100…107 · ميزات 110–115 · إشعارات 080–085 · لوحة 090–096 · دعم 120–128 · نظام 130–139D · إعدادات 139A/B 150–158 · فريق 154–160 · طلبات انضمام 060–061 · بطاقة قناة 063–068 · تصدير/خطة 057 059 102 · قانوني/مساعدة 140 142.

ممنوع من هذه اللوحة: أي `/channel/*` `/warehouse/*` `/app/*` `auth:sanctum`.

---

## 12. عميل TypeScript (انسخ)

```ts
type Envelope<T> = { data: T; meta: { server_time: string; page?: number; per_page?: number; total?: number; last_page?: number } }
type ApiError = { error: { code: string; message: string; permission?: string; details?: Record<string, string[]> } }

export function moneySy(minor: number): string {
  return `${new Intl.NumberFormat('ar').format(minor)} ل.س`
}

export function fxDisplay(rate: number): string {
  // rate stored at 10^6
  return (rate / 1_000_000).toLocaleString('ar', { maximumFractionDigits: 6 })
}

export async function api<T>(
  path: string,
  init: RequestInit & { idempotencyKey?: string } = {},
): Promise<Envelope<T>> {
  const headers = new Headers(init.headers)
  headers.set('Accept', 'application/json')
  headers.set('Accept-Language', 'ar')
  headers.set('X-Client', 'platform-web')
  const token = sessionStorage.getItem('platform')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const method = (init.method ?? 'GET').toUpperCase()
  if (init.idempotencyKey && method !== 'GET') {
    headers.set('X-Idempotency-Key', init.idempotencyKey)
  }
  if (init.body) headers.set('Content-Type', 'application/json')
  const res = await fetch(`${process.env.NEXT_PUBLIC_API_BASE}${path}`, { ...init, headers })
  if (res.status === 204) return { data: undefined as T, meta: { server_time: '' } }
  const json = await res.json()
  if (!res.ok) throw json as ApiError
  return json as Envelope<T>
}
```

لا تولّد مفتاح تكرار داخل `api()` لكل إعادة شبكة — الصفحة تملك المفتاح.

---

## 13. قائمة قبول Cursor (مستودع Next.js)

1. عميل `api` + غلاف + مال int + FX 10^6 + ترجمة `error.code`.
2. دخول بذرة بلا 2FA + تخزين توكن `platform` + `GET /me`.
3. Layout RTL + شريط جانبي بـ `can()` + `platform_admin` يرى الكل الحيّ.
4. حسابي / كلمة مرور / 2FA / رموز / جلسات / خروج.
5. IAM: صلاحيات، أدوار مسودة+اعتماد، تعيين DELETE بجسم، محاكاة، منح، SOD، صندوق اعتماد، مراجعات keep|revoke.
6. تدقيق + تصدير يعرض job_id بلا استطلاع.
7. مراجع كاملة بما فيها إنشاء محافظة بـ `name_ar/name_en/code` وتعطيل + `affected`.
8. عملات + FX integer.
9. قنوات على **`/platform/channels`**: قائمة، إنشاء بجسم الكتالوج، تفاصيل، `allowed_next`، حدود، استخدام، حذف 204. **لا** `/admin/channels`.
10. انترو GET/PUT وإعادة GET بعد الحفظ.
11. لوحة/فوترة/دعم/فريق/نظام/إشعارات: صفحات EmptyState مربوطة بـ PA-* **بلا fetch**.
12. KPI القناة أصفار صادقة. لا GMV مختلق في القائمة.
13. 403 password confirm يعيد نفس المفتاح. 403 sod يخفي الزر. 409 يعرض allowed_next.

---

## 14. QA

1. `POST /platform/auth/login` بذرة → قائمة قنوات `meta.total ≥ 0`.  
2. إنشاء قناة → 201 `provisioning` → تفاصيل `manager: null` و`kpis` أصفار.  
3. انتقال `active → suspended` ثم `→ archived`. `active → archived` → 409.  
4. إنشاء محافظة بلا `name_ar` → 422. تعطيل محافظة → أعداد `affected`.  
5. FX `rate: 1.5` → 422؛ `1500000` → 200.  
6. مستخدم بلا `ad.refs.view` لا يرى المراجع (ما لم يكن `platform_admin`).  
7. توكن قناة على `/platform/channels` → 403 `wrong_guard`.

---

## 15. ما تغيّر منذ مواصفة 2026-09-17

- ✅ القنوات على `/platform/channels` لا `/admin/channels`.
- ✅ المراجع حيّة على `/platform/refs` (31). إنشاء المحافظة `name_ar`+`name_en`+`code`.
- ✅ حدود واستخدام القناة حيّان.
- ✅ انترو المنصة حيّ على `/platform/content/intro`.
- ⛔ اللوحة والفوترة والدعم والصحة والفريق والإشعارات والنسخ ما زالت PA-02…17.
- `platform.json` القديم: لا تستخدم `forbidden` للمراجع أو `/admin/channels`.

عندما يصل مستودع Next.js: راجع مقابل §13 وهذا الملف، وأكمل الناقص دون اختراع API ودون أرقام وهمية.
