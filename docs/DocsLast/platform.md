# لوحة إدارة المنصة (السنترال) — عقد Next.js 15 + الـ API الحي

| | |
|---|---|
| الغرض | العقد الوحيد لبناء **سوبر أدمن / لوحة المنصة** على حارس `platform`. فريق تشغيل ودعم — ليست مبيعات قناة. |
| الحالة | **174/174** مسار ✅ — `docs/status/01-platform-admin.md` (مولَّد 2026-11-03) |
| التطبيق | Next.js 15 · App Router · TypeScript · TanStack Query v5 · RTL · المنفذ **3000** |
| الحارس | `auth:platform` · Sanctum (توكن أو كوكي SPA على النطاق المشترك) |
| الشقيق | `platform.json` · الكتالوج `01/02/03/10/11-platform-*.php` · DOC-07 مرجع منتج · DOC-08 أسماء صلاحيات |
| عند التعارض | الكود الحي ← `status/01` ← الكتالوج ← هذا الملف |

> **لـ Cursor / مطوّر أول.** كل أقسام DOC-07 لها مسارات حية. لا تترك شاشة على `RemoteNotReady` إلا إن `status/01` يقول ❌ (اليوم: صفر). لا تختلق GMV. لا ترسل `X-Channel-Id` (BF-07: الرأس مُتجاهَل).

---

## المحتويات

| § | القسم |
|---|---|
| 0 | قواعد · مال · بيئة |
| 1 | المنتج مقابل DOC-07 |
| 2 | هيكل Next.js والتنقّل |
| 3 | العقد العابر · أخطاء · تكرار |
| 4 | الدخول · أنا · 2FA · جلسات |
| 5 | الصلاحيات (IAM) والتدقيق |
| 6 | المرجعيات |
| 7 | القنوات ودورة حياتها |
| 8 | الفوترة · الباقات · الإيراد |
| 9 | الميزات · المحتوى · الإشعارات |
| 10 | الدعم · الصحة · الإعدادات · الفريق |
| 11 | لوحة القيادة والتقارير |
| 12 | ما تغيّر وقرارات لا تُرجَع |
| 13 | قائمة قبول قبل الربط |

---

## 0. قواعد · مال · بيئة

### 0.1 قواعد لا تُكسر

1. عميل HTTP واحد. لا `fetch` من الصفحات.
2. غلاف `{ data, meta }` / `{ error: { code, message, details? } }`.
3. `error.code` للعقد؛ الرسالة العربية من خريطة الواجهة.
4. كل كتابة: `X-Idempotency-Key` إلا `login` و`2fa/verify`.
5. مال = `int` بأصغر وحدة. ل.س: `12000` → `12,000 ل.س`. لا ÷100.
6. سعر صرف = `int` بسلم ثابت `Money::FX_SCALE = 10^6` (1.0 = `1_000_000`). لا عمود سلم.
7. `Gate::before`: دور `platform_admin` يتجاوز فحوص الصلاحية — الواجهة ما زالت تستخدم `can()` لباقي الأدوار.
8. معرّف أجنبي → 404. توكن حارس آخر → `403 wrong_guard`.
9. حذف قناة وإجراءات حرجة: كلمة سر + اسم مكتوب + OTP منصة (EP-AD-005A) أو TOTP إن وُجد 2FA — لا تختصر الخطوة.
10. **لا `X-Channel-Id`.** لا تبديل مستأجر من لوحة المنصة.

### 0.2 البيئة

| | |
|---|---|
| أساس محلي | `http://127.0.0.1:8000/api/v1` |
| `X-Client` | `platform-web` |
| توكن | `sessionStorage` مفتاح `platform` |
| بذرة | `admin@platform.sy` / `password` — `platform_admin` · 2FA مطفأ محلياً |
| منطقة زمنية | `Asia/Damascus` |

```ts
export const apiHost = process.env.NEXT_PUBLIC_API_HOST ?? 'http://127.0.0.1:8000';
export const baseUrl = `${apiHost}/api/v1`;
export const xClient = 'platform-web';
```

### 0.3 الرموز

| رمز | المعنى |
|---|---|
| ✅ | حي — استدعِه |
| 🔁 | يحتاج مفتاح تكرار |
| 🔒 | صلاحية Spatie — أخفِ الزر إن `!can(code)` |
| ⚠ | إجراء حرج / مزدوج / OTP |

---

## 1. المنتج مقابل DOC-07

لوحة المنصة تملك المستأجر (القناة)، الحدود، المرجعيات المشتركة، الدعم، الفوترة على مستوى المنصة، والصحة. **لا** تملك سلة تاجر ولا التقاط مستودع ولا تسليم مندوب.

| قسم DOC-07 | حالة API (2026-11-03) | أين في الواجهة |
|---|---|---|
| 4.1 لوحة القيادة | ✅ EP-AD-090…096 | `/dashboard` |
| 4.2 قنوات التوريد | ✅ EP-AD-050…068 + applications | `/channels` |
| 4.3 المرجعيات | ✅ EP-AD-030…043G | `/refs/*` |
| 4.4 المستخدمون والدعم | ✅ EP-AD-120…128 | `/support` |
| 4.5 الاشتراكات والفوترة | ✅ EP-AD-100…107 | `/billing` · `/plans` |
| 4.6 الميزات والطرح | ✅ feature flags + overrides | `/features` |
| 4.7 المحتوى ونسخ التطبيقات | ✅ legal · help · app-versions · intro | `/content` · `/app-versions` |
| 4.8 إشعارات المنصة | ✅ broadcast · campaigns · templates | `/notifications` |
| 4.9 صحة النظام | ✅ queues · jobs · maintenance · integrations health | `/system` |
| 4.10 التدقيق | ✅ audit · export · reviews | `/audit` · IAM reviews |
| 4.11 التقارير | ✅ reports + exports | `/reports` |
| 4.12 إعدادات وفريق | ✅ settings* · team · invites | `/settings` · `/team` |

الجرد التفصيلي مسارًا-مسارًا: **`docs/status/01-platform-admin.md`** (174 صف ✅). لا تكرر الجدول هنا يدوياً.

الصلاحيات: أسماء DOC-08 عبر `PermissionCatalog`. ما ليس مبذوراً بعد لا يظهر على مسار حي — يُبذر عند الحاجة (BF-11)، لا تخترع رمزاً في الواجهة.

---

## 2. هيكل Next.js والتنقّل

```
app/
  (auth)/login/page.tsx
  (auth)/2fa/page.tsx
  (console)/layout.tsx              # شريط تنقّل من permissions
  (console)/dashboard/page.tsx
  (console)/channels/...
  (console)/channel-applications/...
  (console)/refs/...
  (console)/billing/...
  (console)/plans/...
  (console)/features/...
  (console)/content/...
  (console)/app-versions/...
  (console)/notifications/...
  (console)/support/...
  (console)/system/...
  (console)/audit/...
  (console)/reports/...
  (console)/iam/...
  (console)/team/...
  (console)/settings/...
  (console)/me/...
lib/api/client.ts
lib/api/permissions.ts
```

أغلق روابط التنقّل بـ `can('ad.…')`. لا تعتمد على اسم الدور إلا كاختصار بصري.

---

## 3. العقد العابر · أخطاء · تكرار

| HTTP | `error.code` | سلوك |
|---|---|---|
| 401 | `unauthenticated`, `token_revoked` | امسح → دخول |
| 403 | `wrong_guard`, `insufficient_permission`, `requires_2fa`, `requires_password_confirm`, `sod_violation` | حسب الرمز |
| 404 | `not_found` | «غير موجود» (يشمل خارج المستأجر المنطقي) |
| 409 | `illegal_transition`, `operation_in_progress`, `stale_version`, `idempotency_key_conflict` | حدّث / انتظر |
| 422 | `validation_failed`, `ref_in_use` | تحت الحقل؛ مرجع مستخدم لا يُحذَف |
| 423 | `plan_limit_exceeded` | حوار حد خطة |
| 429 | `rate_limited` | تراجع |
| 503 | `maintenance_mode` | صيانة |

مرجع كامل للمسارات: ولّد `platform.json`:

```bash
php docs/api/generate-platform-live.php
```

---

## 4. الدخول · أنا · 2FA · جلسات

| EP | طريقة | مسار | ملاحظة |
|---|---|---|---|
| EP-AD-001 | POST | `/platform/auth/login` | إيميل + كلمة سر |
| EP-AD-002 | POST | `/platform/auth/2fa/verify` | إن لزم |
| EP-AD-003 | POST | `/platform/auth/logout` | |
| EP-AD-004 | GET | `/platform/auth/me` | |
| EP-AD-005 | POST | `/platform/auth/confirm-password` | قبل حرج |
| EP-AD-005A | POST | `/platform/auth/request-otp` | OTP منصة لخطوة حساسة |
| EP-AD-006/007 | GET/DELETE | `/platform/auth/sessions`… | |
| EP-AD-159A–I | GET/PUT/POST/DELETE | `/platform/me`… | ملف · كلمة سر · 2FA · API tokens |

---

## 5. الصلاحيات (IAM) والتدقيق

عائلة حية بالكامل (20 مسار): كتالوج صلاحيات · أدوار · اعتماد · تحديث صلاحيات الدور (`ad.iam.role_update` ≠ create — BF-08) · إسناد · محاكاة · منح مؤقت · SOD · طلبات اعتماد مزدوج · حملات مراجعة.

تدقيق: قائمة + تصدير. كل تدخل إداري يُسجَّل؛ الواجهة لا «تصلح» السجل.

---

## 6. المرجعيات

محافظات · مناطق · أنواع نشاط · فئات جذر · وحدات بيع · تجهيزات · عملات · FX · استيراد · تعطيل منطقي (لا hard delete).

قبل التعطيل: مسارات impact counts حيث وُجدت (كم قناة تمسّ الكيان) — الرقم فقط، بلا تسريب صفوف قنوات.

`ref_in_use` → 422: أوضح للمستخدم أن المرجع مستخدَم.

---

## 7. القنوات ودورة حياتها

| قدرة | مسارات مفتاحية |
|---|---|
| قائمة / إنشاء / تعديل / بطاقة | `GET/POST /platform/channels` · `GET/PUT …/{id}` |
| حدود واستخدام | `PUT …/limits` · `GET …/usage` |
| انتقال حالة | `POST …/transition` |
| تغطية · مستودعات · مستخدمون · ميزات | `coverage` · `warehouses` · `users` · `features` |
| إعادة تعيين مدير | `POST …/manager/reset` |
| طلبات انضمام | `channel-applications` + decide |
| تصدير | فردي وجماعي |
| حذف ⚠ | `DELETE …/{id}` — كلمة سر + اسم + OTP منصة (BF-05) |

آلة الحالة من الخادم؛ الأزرار من `allowed_actions` أو قواعد الكتالوج — لا خريطة حالات محلية متناقضة.

---

## 8. الفوترة · الباقات · الإيراد

خطط CRUD · اشتراكات · تعيين باقة لقناة · تغيير جماعي (preview ثم apply) · فواتير منصة · إعفاء / مذكرة دائنة (SOD + مزدوج حيث ينطبق) · dunning · `GET /platform/billing/revenue`.

المبالغ `int`. لا تعرض إيرادًا مختلقاً قبل بيانات حقيقية.

---

## 9. الميزات · المحتوى · الإشعارات

| مجال | ملاحظات تشغيل |
|---|---|
| Feature flags | عالمي + تجاوز لكل قناة؛ الأسبقية: تجاوز ← باقة ← عالمي |
| انترو المنصة | `GET/PUT` محتوى انترو — حي |
| قانوني / مساعدة | نسخ عند النشر |
| إصدارات تطبيقات | force-update يحتاج مساراً حرجاً/مزدوجاً حسب العقد |
| إشعارات | بث حرج مزدوج · معاينة مستلمين · قوالب · حملات · سجل |

Push/WhatsApp على مستوى القناة معلَن غير مضبوط (BF-12) — لا تعرض تسليم push كنجاح مؤكد إن السجل يقول `push_whatsapp_provider_not_configured`.

---

## 10. الدعم · الصحة · الإعدادات · الفريق

### دعم

بحث موحّد · بطاقة 360 · انتحال ≤ مدة العقد (موثّق في التدقيق) · إعادة OTP · سحب جلسات · تعطيل · إعادة جهاز · تذاكر بحالات الكتالوج.

### صحة النظام

طوابير · أخطاء · مهام · صيانة (كتّاب → 503) · اختبار تكاملات · تخزين / نسخ حسب settings.

### إعدادات

ملف عام · أمان (2FA سياسات، مهلة جلسة) · نسخ · افتراضات قنوات · تكاملات (أسرار تُكتب وتُقرأ مقنَّعة).

### فريق

قائمة · دعوات (انتهاء وفق العقد) · لا تحذف آخر `platform_admin` (409).

---

## 11. لوحة القيادة والتقارير

```http
GET /platform/dashboard
GET /platform/dashboard/alerts
GET /platform/dashboard/cards/{key}
GET /platform/dashboard/charts/{key}
GET /platform/reports/{type}
```

`type` حسب الكتالوج (`gmv` | `adoption` | …). التصدير عبر job ثم polling.

**لا تختلق أرقاماً.** إن اللقطة/التجميع فارغ: أصفار صادقة أو حالة فارغة — نفس فلسفة لوحة القناة مع `DailySnapshot`.

---

## 12. ما تغيّر وقرارات لا تُرجَع

| تاريخ | قرار | لا تفعل |
|---|---|---|
| 2026-09-19+ | المسارات تحت `/platform/*` لا `/admin/*` | لا تبقِ عميل `/admin/channels` |
| 2026-09-24 | BF-07: `X-Channel-Id` مُتجاهَل على المنصة | لا تبنِ تبديل قناة |
| 2026-09-24 | BF-05: حذف قناة بـ OTP حقيقي | لا تختصر بـ «كود ثابت» |
| 2026-09-24 | BF-08: `ad.iam.role_update` منفصل عن create | لا تجمع الزرّين تحت صلاحية واحدة |
| 2026-09-23… | PA-01…18 كلها منفَّذة | لا تترك § «غير حيّ» في الواجهة |
| 2026-11-03 | order-flow gaps للمستودع/القناة/التاجر | خارج هذه اللوحة — انظر `order-flow.md` |

تعديلات لاحقة على الكتالوج والصلاحيات المضافة خارج DOC-08 **تبقى**؛ لا تُحذف لإرضاء مسودة DOC قديمة.

---

## 13. قائمة قبول قبل الربط

1. `status/01` = 174/174 و`platform.json` مولَّد حديثاً.
2. كل قسم تنقّل يستدعي مساراً حيّاً — صفر `RemoteNotReady` بسبب «PA ناقص».
3. لا `X-Channel-Id` · مفتاح تكرار على الكتابات · مال `int` · FX سلم 10^6.
4. حذف قناة يمرّ بكل بوابات الخطوة الحساسة.
5. `can(ad.*)` من حمولة الجلسة/الأدوار — ليس hardcode أسماء أدوار فقط.
6. التوكن في `sessionStorage['platform']` فقط.
7. أخطاء عبر `code`؛ 404 لا يفصح عن وجود مورد أجنبي بـ 403.

```bash
php docs/api/generate.php && php docs/status/generate.php
php docs/api/generate-platform-live.php
```

*آخر تحديث: 2026-11-03 · الحالة: 174/174 حيّ · DOC-07 مغطى بالـ API · ما قبل الرفع من جهة الشيفرة: لا نقص مسار على هذا السطح.*
