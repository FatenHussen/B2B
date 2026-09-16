# لوحة قناة التوريد — حزمة فرونت React

**هذا الملف هو المدخل.** ابنِ SPA على الحارس `channel`. لا تغيّر الـ API. لا تخترع مسارًا ولا جسم طلب ولا شكل رد.

التاريخ: **2026-09-16**  
المسارات الحيّة: **77** = 73 تحت `/api/v1/channel` + 3 مراجع مشتركة + `health`

---

## ماذا تُرسل لفريق الواجهة / لـ Claude

أربعة ملفات في مجلد واحد. لا تُرسل كتالوج الـ 338 ولا Postman الكامل.

| # | الملف | ماذا يفعل |
|---|---|---|
| 0 | **`channel-README.md`** (هذا) | القواعد، المكدّس، الترتيب، برومبت Claude |
| 1 | **`channel-api.live.json`** | العقد الحي: method + path + permission + مثال body/response |
| 2 | **`channel-dashboard-spec.md`** | 16 شاشة، حقول، تصميم RTL، حالات فارغة، فجوات §7 |
| 3 | **`channel-web.md`** | حزمة `@b2b/api-client` (كود جاهز)، curl، مطبّات §8 |
| 4 | **`channel-STATUS.template.md`** | انسخه في فرونت باسم `CHANNEL-STATUS.md` حتى لا يُبنى من الصفر |

اختياري: `docs/sprints/frontend/channel-backend-status.md` — اعتماد مزدوج، SOD، أصفار صادقة.

### تعارض المصادر

```
channel-api.live.json          ← ما هو حي اليوم (live=true)
  → channel-dashboard-spec.md  ← الشاشات والحقول والتصميم
  → channel-web.md §2 §5 §8    ← fetch والغلاف والمطبّات
  → لا الكتالوج العام ولا الباك‌لوغ
```

`channel-web.md` مؤرَّخ 14 أيلول: رقم **47** وملاحظة أن صندوق الطلبات كان 500 **قديمة**. الصندوق والمخزون والمالية والمحتوى والولاء والإشعارات والتقارير **حيّة**. مجلد Postman `03` فيه 47 طلبًا — ناقص، لا تعتمد عليه كقائمة اكتمال.

`catalog.body` / `catalog.response` داخل JSON **مثال**. إن خالفا المواصفة أو الكنترولر، المواصفة تفوز.

---

## المشروع

| | |
|---|---|
| المسار | `apps/channel-web` |
| المنفذ | **3001** |
| الإطار | **React 19 + Vite 6 — SPA خالص. لا Next.js. لا SSR.** |
| الحارس | `channel` (Sanctum Bearer) |
| `X-Client` | `channel-web` |
| البذرة | هاتف **`+963900000001`** · قناة `demo-channel` (id `1`) · `channel_manager` |
| Staging | `https://api.sentraxsy.com/api/v1` — OTP **`000000`** · CORS يسمح بـ `localhost:3001` |
| محلي | `http://127.0.0.1:8000/api/v1` |

```dotenv
VITE_API_BASE_URL=https://api.sentraxsy.com/api/v1
VITE_X_CLIENT=channel-web
```

البادئة `VITE_` لا `NEXT_PUBLIC_`. اقرأ `import.meta.env.VITE_*`.

**لا ترسل `X-Channel-Id`.** القناة من عضوية التوكن.

### توكن في دقيقتين (staging: الرمز `000000`)

```bash
curl -s -X POST https://api.sentraxsy.com/api/v1/channel/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"phone":"+963900000001"}'
# { "data": { "otp_id": "otp_…" } }   — بلا expires_in ولا resend. لا مفتاح تكرار.

curl -s -X POST https://api.sentraxsy.com/api/v1/channel/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"otp_id":"otp_…","code":"000000"}'
# data.token + data.permissions[] + data.channels[]
```

ثم `Authorization: Bearer {token}` على كل شيء آخر. لا مسار إعادة إرسال — اطلب OTP جديدًا. لا مسار خروج — امسح `sessionStorage`.

`otp_invalid` / `otp_expired` = **401 بلا توكن** — ابقَ على شاشة الرمز، لا «تسجّل خروج». هاتف بلا عضوية قناة = **403 `insufficient_permission`** لا 404.

---

## المكدّس — مقفول

من `channel-web.md` §2.1. انسخ حزمة العميل من §2 كما هي.

- Vite 6 + React 19 SPA
- React Router v7 `createBrowserRouter` — حراسة المسار في الراوتر (`tokenStore.get('channel')`)
- TypeScript 5 `strict` — لا `any` في `lib/`
- Tailwind، `<html lang="ar" dir="rtl">`
- TanStack Query v5
- التوكن: `sessionStorage` بمفتاح الحارس `channel`
- **`fetch` فقط داخل `packages/b2b-api-client` (`@b2b/api-client`)** — لا Axios، لا Redux، لا NextAuth
- CSS منطقي: `margin-inline`، `padding-inline`، `text-start`
- هاتف وOTP: `dir="ltr"` و`inputMode="numeric"`

اللغة الظاهرة عربية RTL أصيل (ليس انعكاس LTR). الأرقام غربية **0–9** و`tabular-nums` في أعمدة المال.

---

## كيف تقرأ `channel-api.live.json`

استدعِ فقط عنصرًا `"live": true`. لا تستدعِ مصفوفة `forbidden`.

| الحقل | المعنى |
|---|---|
| `method` + `path` | النداء حرفيًا. المسار يبدأ بـ `/api/v1/...` فوق `VITE_API_BASE_URL` إن كانت القاعدة أصلًا `/api/v1` — لا تكرّر البادئة |
| `permission` | غلّف الزر بـ `can(code)` من مصفوفة `permissions` بعد الدخول |
| `write` + `idempotency` | إن `idempotency: true` أرسل `X-Idempotency-Key` (UUID **واحد لكل نيّة مستخدم**، يُعاد في إعادة المحاولة) |
| `catalog` | مثال جسم/رد/أخطاء، أو `null` = حي بلا صف كتالوج (`GET/PUT /channel`، `/channel/zones`، المراجع) |
| `people_writes_no_list` | كتابات على `{id}` يُكتب يدويًا — بلا قائمة تجّار/مندوبين |
| `headers` / `envelope` | في رأس الملف: الغلاف، المال، ممنوع `sort` |

`request-otp` **ليس** idempotent. لا تُعد إرساله بنفس المفتاح وتتوقع عدم تكرار الإرسال.

`VITE_API_BASE_URL` في الأمثلة ينتهي بـ `/api/v1`. مسارات JSON كاملة (`/api/v1/channel/...`). في العميل: إمّا قاعدة بلا لاحقة ومسار كامل، أو قاعدة `/api/v1` ومسار `/channel/...` — اختر واحدًا ولا تُنتج `/api/v1/api/v1`.

---

## قواعد لا تُكسر

1. **لا تخترع API.** `forbidden` أو ⛔ في المواصفة §7 = EmptyState أو `IdField`. بلا صفوف مختلقة.
2. **المال عدد صحيح** بأصغر وحدة. الليرة `decimals = 0` — اعرض العدد، لا تقسم على 100. الاستثناء الوحيد: `delivery_fee` و`min_order_value` على `/channel/zones` **نصوص عشرية**.
3. **الصلاحيات من `permissions` بعد `verify-otp` فقط.** لا تفرّع على اسم الدور. `sales_manager` لا يأخذ `sc.offers.*`.
4. **SOD-01:** تأكيد طلب + تسجيل دفعة على نفس المستخدم → `403 sod_violation` إلا `channel_manager`. أظهر الزر إن وُجدت الصلاحية؛ أظهر رسالة الخادم عند 403. لا تخفِ الزر.
5. **اعتماد مزدوج** (تسوية مخزون، إشعار دائن، إبطال فاتورة): أول POST يعيد `approval_request_id` و`meta.requires_dual_approval`. مستخدم **آخر** يعيد نفس الجسم + `approval_reason`. نفس المستخدم → `403 sod_violation`.
6. **لا ترسل `sort` على أي قائمة** — يُتجاهل ويلغي الترتيب الافتراضي.
7. **404 `not_found` = غير موجود أو ليس لك.** لا تعرض «ممنوع».
8. **مفتاح تكرار = نيّة مستخدم** لا محاولة HTTP. CORS لا يكشف `Idempotent-Replayed` — لا تنتظره.
9. **أصفار صادقة.** لا تختلق `fill_rate` ولا CTR ولا `net_margin` ولا `conversion_rate`. إن لم تكن صفرًا: `conversion_rate` و`fill_rate` و`ctr` أعداد صحيحة بمقياس 10^4.
10. أزرار الطلب من `allowed_actions` — **ليست** فحص صلاحية؛ غلّف أيضًا بـ `can()`.
11. **`Accept-Language` مُتجاهَل.** رسائل الخادم إنكليزية. لا تعرض `error.message` نيئًا. ترجم `error.code` من قاموس المواصفة §3.
12. **500 بلا غلاف:** `{"message":"Server Error"}` — عطل حقيقي، لا تعِد في حلقة.

---

## ممنوع بناؤه

| لا يوجد | بديل الواجهة |
|---|---|
| `GET /channel/retailers` | `IdField` — لا قائمة تجّار ولا موافقة ولا 360 |
| `GET /channel/reps` | `IdField` — لا قائمة مندوبين |
| `GET /channel/warehouses` | `IdField` |
| `GET /channel/products/{id}` | محرّر من الكاش أو إعادة ملء مع تحذير المسح |
| `GET /channel/offers/{id}` | إنشاء + إيقاف فقط |
| `GET /channel/invoices/{id}` | قائمة `{id, no, total, status}` = `open` / `void` / `credited` — لا بنود |
| `/channel/iam/*` | لا عنصر تنقّل |
| رفع وسائط | لا `<input type=file>` إلا Excel استيراد الكتالوج؛ `media_id` نص |
| SOD-02 وSOD-04 | غير مفروضين — لا تحذير استباقي |
| مجموعات تجّار | `group_ids` على العروض تُسقط |

كتابات الأشخاص الحيّة (معرّف يدوي):

- `PUT /channel/reps/{id}/discount-cap`
- `POST /channel/reps/{id}/settle`
- `PUT /channel/retailers/{id}/credit`

---

## الشاشات — بهذا الترتيب

المواصفة §5 ملزمة حقلًا بحقل. `channel-web.md` §6 يتوقف قبل المالية — **أكمل من المواصفة.**

| # | الشاشة | ملاحظة |
|---|---|---|
| 1 | دخول | `request-otp` ثم `verify-otp`. مؤقّت 60ث محلي. TTL الرمز 300ث، 5 محاولات |
| 2 | الهيكل | كل بند خلف `can()`. لوحة مفاتيح: J/K، Enter، C للتأكيد في التفاصيل |
| 3 | مراجع | `GET /governorates` `/zones` `/currencies` **بدون** `/channel`. صفِّ `status === 'active'` في المنتقي. `decimals` يغذّي منسّق المال |
| 4 | لوحة القيادة | `GET /channel/dashboard` من اللقطة اليومية. الصفر حقيقي |
| 5 | صندوق الطلبات | `GET /channel/sub-orders` صفحات. بلا `sort` |
| 6 | تفاصيل الطلب | `allowed_actions`. `bulk-confirm` دائمًا 200 — اعرض `confirmed` و`failed`. `schedule` → `postponed` |
| 7 | الكتالوج | شجرة ≤ 5. `PUT` منتج = استبدال كامل. استيراد: معاينة أولًا |
| 8 | التسعير | سجل التغيير مفاتيحه أعداد بلا أسماء. سقف خصم المندوب بدون صف = 0 = كل خصم يفشل |
| 9 | العروض | مكافأة بلا `product_id` تُهمَل. `PATCH .../stop`. `net_margin` و`conversion_rate` تبقى 0 |
| 10 | المخزون | التسوية اعتماد مزدوج. التحويل/نقاط إعادة الطلب معاملة واحدة |
| 11 | المرتجعات | `decide` ثانية على غير `pending` → `409 illegal_transition` |
| 12 | المالية | FIFO، أعمار أعداد صحيحة، SOD-01 على الدفعة |
| 13 | تغطية المناطق | مال **نصي**. POST upsert **201**. DELETE **204** بلا جسم |
| 14 | الإشعارات | قوالب + سجل. `sc.notify.view` مزروع |
| 15 | المحتوى | انترو، بنرات، سلايدر. لا رفع ملف. `ctr` من الدفتر المخزَّن |
| 16 | الولاء | قواعد JSON + مكافآت |
| 17 | التقارير | سجّل `GET .../reports/margins` **قبل** `{type}`. `job_id` بلا استطلاع — «في الانتظار» بلا شريط كاذب |
| 18 | الإعدادات | `GET/PUT /channel`. تجاهل `allowed_next`. `created_at` UTC؛ الباقي آسيا/دمشق |

التصميم: كثافة تشغيلية، رموز المواصفة §2 — `--primary` teal `#0F766E`، IBM Plex Sans Arabic، وضع داكن `[data-theme="dark"]`. لا تلوّن المبلغ إلا الدين (`--danger`) والرصيد (`--success`).

---

## تعريف الانتهاء

- الشاشات الـ 16 من المواصفة موجودة، بما فيها الفراغ الصادق لـ ⛔
- كل كتابة (ما عدا OTP/دخول) ترسل `X-Idempotency-Key`
- التنقّل والإجراءات خلف `can(permission)`
- الدخول ينجح: `+963900000001` / `000000` على staging
- لا قوائم مختلقة، لا مال عشري إلا مناطق القناة، لا Next.js
- TypeScript strict يمرّ، RTL أصيل
- الاعتماد المزدوج مستخدمان مختلفان، ليس مربع تأكيد لنفس المستخدم

حقل أو مسار غير موجود في الـ JSON ولا في المواصفة: **قف واسأل. لا تخمّن.**

---

## خطوتان حتى لا يُبنى المشروع من الصفر كل مرة

هذا الريبو **API فقط**. فرونت React في مستودع آخر. افتح Claude **هناك**.

1. انسخ `channel-STATUS.template.md` إلى جذر الفرونت باسم `CHANNEL-STATUS.md`.
2. الجلسة الأولى: برومبت **الجرد** (أسفل). النتيجة جدول حالة محدَّث.
3. كل جلسة لاحقة: برومبت **التابع** + أرفق `CHANNEL-STATUS.md` المحدَّث.

---

### برومبت 1 — جرد ثم أكمل (أول مرة، أو بعد غياب)

الصقه في **مستودع الفرونت** بعد إرفاق الحزمة الأربعة + `CHANNEL-STATUS.md` إن وُجد.

```
You are continuing an existing React channel dashboard. Do NOT scaffold a new app if one already exists.

STEP 0 — ORIENT (mandatory, before any code)
1. Find the frontend: look for apps/channel-web, packages/*api-client*, vite.config, port 3001.
2. If CHANNEL-STATUS.md exists, read it first. If not, create it from the attached template.
3. Map each of the 18 screens in CHANNEL-STATUS.md to real files (routes, pages, api calls).
4. Reply with a short table: screen → done|partial|empty|missing → file path. Then wait if nothing is buildable, or continue only the NEXT row that is missing/partial.

Never run create-vite / npm create if a Vite React app already exists.
Never rewrite @b2b/api-client if it already matches channel-web.md §2.
Never rebuild screens marked done unless I name a bug.

Sources (attached, this order):
0. channel-README.md
1. channel-api.live.json — call only live:true; never forbidden
2. channel-dashboard-spec.md
3. channel-web.md §2 client, §8 gotchas
4. CHANNEL-STATUS.md or channel-STATUS.template.md

Conflict: live JSON → spec → channel-web HTTP. channel-web.md (2026-09-14) counts/500s are STALE. Inbox, inventory, finance, content, loyalty, notify, reports are LIVE.

Stack: React 19 + Vite 6 SPA, no Next.js, port 3001, fetch only inside @b2b/api-client.
Seed: +963900000001 / 000000 at https://api.sentraxsy.com/api/v1. Do not send X-Channel-Id.

Rules: no invented routes; money integer except /channel/zones strings; can(permissions) not role names; no sort; 404 = not yours; idempotency UUID per intent except OTP; dual approval = two users; honest zeros; map error.code (Accept-Language ignored); Arabic RTL.

End of session: update CHANNEL-STATUS.md (dates, screen table, "التالي" one line).
This session: finish ONE screen from "التالي" or the first missing row. Do not start all 16.
```

---

### برومبت 2 — جلسة تالية (انسخ كل مرة)

```
Continue the existing channel-web React app. Do not create a new project.

Read CHANNEL-STATUS.md first. Then the pack: channel-README.md, channel-api.live.json, channel-dashboard-spec.md, channel-web.md §8.

Work only on the screen named in CHANNEL-STATUS.md → "التالي".
Do not touch screens marked done. Do not scaffold. Do not replace the api client.

When done: update CHANNEL-STATUS.md (that row + the new "التالي" line).
If "التالي" is empty, stop and list remaining missing/partial rows — do not pick a new epic yourself.
```

