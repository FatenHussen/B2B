# أدمن المنصة — مواصفة الشاشات

التاريخ: **2026-09-17** · الحارس `platform` · المنفذ **3000** · RTL عربي.

عقد المسارات: **`platform.json` فقط** (`live:true`). الفجوات في الملحق أسفل هذا الملف.

المنفذ **3000** · حارس `platform` · بذرة `admin@platform.sy` / `password`.  
**ليست** لوحة قناة التوريد (3001 / `channel`).

---

## 0. عقد عابر

- غلاف `{ data, meta.server_time }` · الخطأ `{ error: { code, message, permission?, details } }`. اربط الواجهة بـ `data` وفرّع على `error.code`.
- `204` بلا جسم (حذف قناة).
- مال: عدد صحيح. لا GMV مختلق.
- مفتاح تكرار على كل كتابة ما عدا `login` و`2fa/verify`. عند `403 requires_password_confirm`: أكّد ثم أعد **نفس** المفتاح والجسم.
- `can(code)` من `user.permissions`. عنصر القنوات أيضًا `roles.includes('platform_admin')`.
- 404 = غير موجود أو ليس لك. 403 `wrong_guard` = توكن حارس آخر.
- رسائل الخادم قد تكون إنكليزية — ترجم `error.code`.
- `created_at` القناة UTC؛ باقي الأوقات `Asia/Damascus` (`+03:00`).

أرقام غربية 0–9 و`tabular-nums`. لغة الظاهرة عربية RTL.

---

## 1. دخول

`POST /platform/auth/login` `{ email, password }`.

- نجاح بذرة: `data.token` + `user.roles` + `user.permissions`. خزّن التوكن بمفتاح `platform`.
- 2FA: `data.requires_2fa` + `challenge_token` → شاشة منفصلة في **حالة الراوتر فقط**.
- `POST /platform/auth/2fa/verify` `{ challenge_token, code }` (6 أرقام، `dir=ltr`).
- خطأ الدخول: 401 عام — «بيانات غير صحيحة».
- لا تبنِ autologout على `expires_at`.

---

## 2. الهيكل

بعد الدخول: `GET /platform/me` لتحديث الصلاحيات.

تنقّل مقترح (أخفِ ما لا `can` / لا دور):

| بند | شرط | شاشة |
|---|---|---|
| نظرة IAM | `ad.iam.view_catalog` | 8 |
| أدوار | نفسها | 9–11 |
| تعيينات | `ad.iam.role_assign` | 12 |
| محاكاة | `ad.iam.simulate` | 13 |
| منح مؤقت | `ad.iam.grant_temp` | 14 |
| SOD | `ad.iam.sod_rules` | 15 |
| اعتمادات | `ad.iam.view_catalog` | 16 |
| مراجعات | `ad.audit.review` | 17 |
| تدقيق | `ad.audit.view` | 18 |
| قنوات | **دور** `platform_admin` | 19 |
| لوحة أرقام / فوترة / فريق / دعم / مراجع عقد | — | لا عنصر، أو عنصر معطّل + EmptyState |

لا روابط `/channel/*`.

---

## 3. حسابي

`GET /platform/me` → `{ id, name, email, phone, roles, permissions, two_factor_enabled, last_login_at }`.

`PUT /platform/me` `{ name, phone? }` — **أرسل الاسم دائمًا**. الرد `{ updated: true }` ثم أعد GET. الهاتف سوري يُطبَّع في الخادم.

---

## 4. كلمة المرور

`PUT /platform/me/password` `{ current_password, password, password_confirmation }` `min:8`.

تدفق: 403 `requires_password_confirm` → مودال `POST /auth/confirm-password` `{ password }` → أعد PUT **بنفس** `X-Idempotency-Key`. كلمة غلط في التأكيد = 401.

---

## 5. 2FA

1. `POST /me/2fa/enable` بلا جسم → `secret` (رابط `otpauth://`) و`qr_svg` **فارغ**. ارسم QR من `secret`.
2. 2FA **ليس** مفعّلًا بعد.
3. `POST /me/2fa/confirm` `{ code }` → `enabled` + **8 رموز استعادة مرة واحدة**. لا تُغلق الحوار قبل نسخ/تنزيل.
4. `GET /me/2fa/recovery-codes` → `{ codes_remaining }` فقط.
5. رمز غلط = `401 otp_invalid` و2FA يبقى مغلقًا.

---

## 6. رموز API

`GET /me/api-tokens`.  
`POST` `{ name, password_confirmation }` — هنا كلمة السر الحالية **ليست** نافذة الـ 15 دقيقة. التوكن يظهر مرة.  
`DELETE /me/api-tokens/{id}`.

403 هنا ≠ مودال confirm-password العام.

---

## 7. الجلسات

`GET /auth/sessions` — بلا صفحات، الأحدث أولًا: `{ id, ip, agent, last_active_at, current }`.  
`DELETE /auth/sessions/{id}` — لا تلغِ `current` بلا حوار صريح. الخروج = `POST /auth/logout` (هذا التوكن فقط) ثم امسح `sessionStorage`.

---

## 8. كتالوج الصلاحيات

`GET /platform/iam/permissions` صفحات.  
`filter[system]=platform|channel|warehouse|app` · `filter[severity]` · `search` على الرمز أو `name_ar`.

صف: `{ code, name_ar, system, module, severity, dual_approval, delegatable, roles_count, users_count }`.

133 رمزًا مزروعًا (DOC-08: 170) — الناقص ليس عطلًا.  
`dual_approval: true` → التنفيذ عبر صندوق الاعتماد.

`GET /iam/permissions/{code}/holders` — **ليس** غلاف قائمة.  
`users[].name` = `"#"+id`. سقف 50 بلا إشارة. رمز مجهول 404.

---

## 9–11. الأدوار

`GET /iam/roles` صفحات: `{ id, key, name, system, status, is_builtin, permissions_count, users_count }`.  
`filter[system]` · `filter[status]` · `sort=id|name|created_at` (أو `-`). لا حذف لـ `is_builtin`.

**معاينة:** `POST /iam/roles/preview` `{ permissions }` عند **كل** تغيير. اطبع `summary_ar` كما هو. اعرض `sod_conflicts`.

**إنشاء:** `POST /iam/roles` → **201** `{ id, status: "draft", sod_conflicts }`. الدور **لا يمنح شيئًا** حتى اعتماد ثانٍ. أبرز `draft` في القائمة.

**اعتماد:** `POST /iam/roles/{id}/approve` `{ reason }` 3–500. المنشئ → `403 sod_violation` — أخفِ الزر له ولا تعرض إعادة محاولة.

**صلاحيات:** `PUT /iam/roles/{id}/permissions` **استبدال كامل** `{ permissions, reason }`. اعرض `changed` من الخادم. نظام الصلاحية ≠ نظام الدور → 422.

---

## 12. التعيينات

`POST /iam/assignments` `{ user_ids, role_id, expires_at?, reason }` حد 50.  
**200 دائمًا عند نجاح جزئي** — ارسم `assigned` و`rejected[].reason`.

`DELETE /iam/assignments` **بجسم** `{ user_id, role_id, reason }`. جسم ساقط = 422.

لا قائمة مستخدمي منصة مستقلة — `IdField` لمعرّف المستخدم حتى يوجد `GET /platform/team`.

---

## 13. المحاكي

`POST /iam/simulate` — خمس طبقات بالترتيب: `guard` · `permission` · `tenant` · `sod` · `temp-grant` (`pass|fail|skip`).

`allowed = guard && (permission || temp-grant) && !sod`.  
طبقة `tenant` **ثابتة pass** — اكتب ذلك في الواجهة.

---

## 14. منح مؤقتة

`POST /iam/temp-grants` `{ user_id, permission, duration_minutes (1–240), reason }` — **reason ≥ 20**.  
→ `pending_approval` + `expires_request_at` (مهلة الاعتماد لا المنحة).

`POST /iam/temp-grants/{id}/approve` `{ decision: approve|reject, reason }`. الطالب لا يعتمد.

---

## 15. SOD

`GET /iam/sod-rules` قراءة، بلا صفحات: `{ code, permission_a, permission_b, reason, exceptions }`.  
حمّل مرة. استخدمه لشرح `sod_violation`. لا إنشاء.

---

## 16. صندوق الاعتماد

`GET /iam/approval-requests` صفحات · `filter[status]` · `filter[type]`.  
`payload` JSON حر — اعرضه عامًا.

`POST …/{id}/decide` `{ decision: approve|reject, reason }` → اقرأ **`executed`** لا `status`.  
اعتماد بلا تنفيذ: `approved` + `executed: false`. تكرار → 409. اعتماد النفس → 403.

---

## 17. مراجعات الوصول

`POST /iam/reviews` `{ quarter, scope }` → `{ campaign_id, items_count }`.  
`GET /iam/reviews/{id}` **بلا صفحات** — ظاهِر القائمة. `decision: null` = غير محسوم.  
`POST …/items/{itemId}/decide` `{ decision: keep|revoke, reason }` — **ليس** approve/reject. حوار منفصل عن §16.

---

## 18. التدقيق

`GET /platform/audit` صفحات. المرشّحات في **`filter`**: `actor` · `action` · `channel_id` · `date_from` · `date_to`.

صف: `{ at, actor, action, entity_type, entity_id, before, after, ip, impersonated }`.  
`before`/`after` JSON حر. شارة إن `impersonated`.

`POST /platform/audit/export` جسم **`filters`** (جمع). رد `{ job_id }`.  
اعرض «في الانتظار» **وتوقف**. لا شريط تقدم. بلا Horizon لا ملف.

---

## 19. القنوات (`CHANNELS_BASE`)

مسار متحرّك. كل الاستدعاءات من ثابت واحد.

**قائمة:** `GET {CHANNELS_BASE}` — `page`/`per_page` فقط.  
`created_at` UTC. البوابة: دور `platform_admin`.

**إنشاء:** `POST {CHANNELS_BASE}` جسم الكتالوج (انظر live JSON):  
`name, slug, legal_form, cr_number, governorate_ids, zone_ids, activity_type_ids, plan_id, billing_cycle, trial_days, limits{}, manager{}`.  
**لا ترسل `status`.** 201 `{ id, status: "provisioning", provisioning_job_id }`. لا تنتظر `active` في هذا الطلب.

`governorate_ids` / `zone_ids` / `activity_type_ids` / `plan_id`: اليوم بلا قوائم عقد منصة — `IdField` أو كاش صامت من المسارات المتحرّكة إن وافق المنتج.

**تفاصيل:** `GET {CHANNELS_BASE}/{id}`  
`channel.{ id, name, slug, status, allowed_next, legal_form, cr_number }` · `manager: null` · خطة/حدود · KPI أصفار **صادقة** · خط زمني.

أزرار الانتقال = `allowed_next` ∩ ما يسمح به `ad.channels.suspend`.

**تحديث:** `PUT` جزئي + `reason` إلزامي. لا `status`.

**انتقال:** `POST …/{id}/transition` `{ to_status, reason }`. 422 / 409 `illegal_transition`.

**إعادة تجهيز:** `POST …/{id}/retry-provisioning` جسم `{}` → `{ job_id }`. «في الانتظار» بلا استطلاع.

**حذف:** `DELETE` → 204. حوار «نهائي بلا استعادة». دور `platform_admin`.

لا تبنِ: حدود، استخدام، تغطية، مستخدمين، مستودعات، ميزات، طلبات انضمام، خطة جماعية، تصدير — EmptyState في التفاصيل إن وُجد مكان في التصميم.

---

## 20+. ممنوع (empty)

| شاشة | لماذا |
|---|---|
| لوحة GMV / رسوم | `GET /platform/dashboard` 404 — لا رقم مختلق |
| مراجع CRUD على `/platform/refs` | 0 مسار عقد |
| فوترة، خطط، فريق، دعم، انتحال | SP-17 |
| إشعارات المنصة / حملات | SP-14 |
| انترو/قانوني المنصة | لا route — انترو القناة ليس هنا |
| حالة وظائف عامة | لا `GET …/exports/{jobId}` على هذا الحارس |

عنصر تنقّل معطّل + جملة «غير متاح في الـ API بعد» أفضل من بيانات وهمية.


---

# ملحق — حيّ / متحرّك / مهمَل

# أدمن المنصة — حيّ / متحرّك / مهمَل

| | |
|---|---|
| التاريخ | 2026-09-17 |
| لمن | React سنترال (`auth:platform` · منفذ **3000**) |
| مصدر المسار | `route:list` ← `platform.json` ← هذا الملف |
| ليس | لوحة **قناة التوريد** (`channel` / 3001) |

**59** مسارًا حيًا في الحزمة · **37** مستقرة على `/platform` · **22** متحرّكة · **112** عقد كتالوج بلا route على مسار المنصة.

ملاحظة قديمة: مستند العميل مؤرَّخ 7 أيلول: «41 مسارًا، 5 قنوات». اليوم: **retry-provisioning** و**transition** حيّان على `/admin/channels`، وجسم الإنشاء هو عقد الكتالوج لا `name+slug` فقط.

---

## 0. ماذا تفتح

| دورك | الملف |
|---|---|
| فرونت سنترال | هذا الملف |
| عقد حي | `platform.json` |
| شاشات | أعلى هذا الملف |
| عميل TS | (محذوف — القواعد في أعلى هذا الملف) |

---

## 1. حيّ اليوم — ابنِ عليه

### 1.1 صحة ودخول وحساب — مستقرة

| مسار | ملاحظة |
|---|---|
| `GET /health` | |
| `POST /platform/auth/login` | معفى من التكرار. بذرة بلا 2FA → توكن |
| `POST /platform/auth/2fa/verify` | معفى. `challenge_token` 10د / 5 محاولات |
| `POST /platform/auth/logout` | التوكن الحالي فقط |
| `GET /platform/auth/me` | مطابق `GET /platform/me` |
| `POST /platform/auth/confirm-password` | نافذة 15د لـ `PUT /me/password` فقط |
| `GET /platform/auth/sessions` · `DELETE …/{id}` | بلا صفحات |
| `PUT /platform/me` | جسم كامل — `name` إلزامي |
| `PUT /platform/me/password` | بعد التأكيد؛ أعد الكتابة **بنفس** المفتاح |
| `POST /platform/me/2fa/enable` · `confirm` · `GET recovery-codes` | `qr_svg` فارغ؛ الرموز مرة واحدة؛ GET عدّاد فقط |
| `GET/POST/DELETE /platform/me/api-tokens` | كلمة السر في `password_confirmation`، فحص فوري |

### 1.2 IAM والتدقيق — مستقرة · 20 مسارًا

صلاحيات `ad.iam.*` و`ad.audit.*`. كل الأزرار خلف `can()`.

قائمة الأدوار، معاينة، إنشاء (مسودة)، اعتماد (مستخدم ثانٍ)، استبدال صلاحيات، تعيين/سحب (DELETE بجسم)، محاكاة، منح مؤقت، قواعد SOD، صندوق اعتماد، مراجعات `keep|revoke`، سجل تدقيق، تصدير `job_id` بلا استطلاع.

### 1.3 قنوات — حيّة على مسار **متحرّك**

ثابت واحد: `CHANNELS_BASE = '/admin/channels'` → سيصبح `/platform/channels`.

| مسار حي | بوابة | ملاحظة |
|---|---|---|
| `GET /admin/channels` | دور `platform_admin` | صفحات فقط — بلا بحث. `created_at` **UTC** |
| `POST /admin/channels` | `ad.channels.create` | جسم الكتالوج الكامل. `status` ممنوع. الرد 201 `{id, status:provisioning, provisioning_job_id}` |
| `GET /admin/channels/{id}` | `ad.channels.view` | `channel.allowed_next` · `manager: null` حتى BE-T07 · KPI أصفار صادقة |
| `PUT /admin/channels/{id}` | `ad.channels.update` | جزئي + `reason` إلزامي. `status` ممنوع |
| `DELETE /admin/channels/{id}` | دور `platform_admin` | **204 بلا جسم**. حذف منطقي بلا استعادة |
| `POST …/{id}/retry-provisioning` | `ad.channels.update` | `{job_id}` — بلا استطلاع |
| `POST …/{id}/transition` | `ad.channels.suspend` | `{to_status, reason}` → `{status, allowed_next}`. 422 حالة مجهولة · 409 انتقال ممنوع |

حالات القناة: `provisioning` · `active` · `suspended` · `archived`.  
الأزرار من `allowed_next` لا من قائمة ثابتة في الواجهة.

---

## 2. متحرّك — لا شاشة مراجع بعد

`/governorates` · `/zones` · `/currencies` (15 مسارًا) حيّة على **جذر** `/api/v1` بحراس متعددة (`platform,channel,warehouse,app`). العقد: `/platform/refs/…`.

- الشكل أرفع/أضعف من SP-03 (لا `zones_count` كما في الكتالوج بالضرورة).
- الكتابة `ad.refs.*` على حارس المنصة فقط؛ البقية 403.
- **لا تبنِ شاشات أدمن عليها** إلا بثابت واحد وبعد اتفاق المنتج. من يبني على المسار الحالي يعيد العمل عند النقل.

`GET /currencies` يفيد **منسّق المال** (`decimals`) — يمكن كاش صامت بلا شاشة CRUD.

---

## 3. مهمَل — لا تبنِ ولا تحاكِ

كل صف = 404 اليوم. القائمة الكاملة في `platform.json` → `forbidden` (112).

### مراجع على عقد المنصة (SP-03)

`GET/POST/PUT/PATCH /platform/refs/governorates|zones|activity-types|root-categories|sale-units|equipments|currencies` · `fx-rates` · `import` · `GET /public/refs`

الجداول موجودة في الباك لجوءًا؛ **لا HTTP على هذا البادئ**.

### قنوات على عقد `/platform/channels` ما زال غائبًا

حيّ على `/admin` (CRUD+انتقال+إعادة تجهيز). ما زال 404 حتى على المسار المتحرّك:

| موضوع | مسار الكتالوج |
|---|---|
| حدود | `PUT /platform/channels/{id}/limits` |
| استخدام | `GET …/usage` |
| تصدير قناة | `POST …/export` |
| مستخدمون / إعادة مدير | `GET …/users` · `POST …/manager/reset` |
| تغطية | `GET/PUT …/coverage` |
| مستودعات | `GET …/warehouses` |
| ميزات | `GET …/features` |
| إشعار مديرين | `POST /platform/channels/notify-managers` |
| خطة / خطة جماعية | `POST …/plan` · `bulk-plan` · `bulk-plan/preview` · `channels/export` |
| طلبات انضمام | `GET/POST /platform/channel-applications…` |

لا استطلاع تجهيز. لا محرّر تغطية. لا بطاقة «360» للقناة.

### لوحة وتقارير المنصة (SP-16)

`GET /platform/dashboard` · `alerts` · `cards/{key}` · `charts/{key}` · `GET /platform/reports/{type}` · تصدير · `GET /platform/exports/{jobId}`

**لا تختلق GMV.** EmptyState.

### إشعارات المنصة (SP-14)

بث، إنشاء، معاينة، قوالب، سجل، حملات — كلها 404. (إشعارات **القناة** على اللوحة الأخرى.)

### محتوى المنصة

`GET/PUT /platform/content/intro` · legal · help · app-versions — كتالوج فقط.  
انترو حي = `GET/PUT /channel/content/intro` لفريق القناة.

### SP-17 بالكامل

خطط، اشتراكات، فواتير منصة، دائن، دَين تحصيل، إيراد، فريق، دعم، انتحال، أعلام ميزات، إعدادات نظام، نسخ احتياطي، صحة تكامل، وظائف مجدولة، تخزين.

---

## 4. هيكل بلا أثر كامل

| الشيء | الواقع |
|---|---|
| تصدير تدقيق | `job_id` بلا GET حالة |
| إعادة التجهيز | `job_id` بلا استطلاع |
| محاكاة IAM | طبقة `tenant` = `pass` ثابت |
| حاملو صلاحية | الاسم `"#id"` · سقف 50 صامت |
| مدير القناة في التفاصيل | `null` حتى تذكرة الدعوة |
| `Gate::before` | `platform_admin` يتجاوز فحوصات الصلاحية |
| انترو/بنرات القناة | لا يقرأها هذا الحارس |

---

## 5. ترتيب مقترح (فرونت ثم باك)

**فرونت الآن:** 1 دخول → 2 هيكل → حساب/2FA/رموز → IAM 8–17 → تدقيق → قنوات على `CHANNELS_BASE` (قائمة، تفاصيل، انتقال من `allowed_next`، إنشاء بجسم الكتالوج).

**لا فرونت:** لوحة أرقام، مراجع عقد، فوترة، فريق، دعم، إشعارات منصة، انترو منصة.

**باك بعد ذلك:** نقل `/admin/channels` و`/governorates` إلى عقد الكتالوج · بقية SP-04 · SP-03 الناقص (أنواع نشاط، FX، استيراد) · SP-16/17.

