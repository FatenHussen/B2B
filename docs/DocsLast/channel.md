# لوحة قناة التوريد — عقد Next.js 15 + الـ API الحي

| | |
|---|---|
| الغرض | الملف الوحيد الذي يحتاجه وكيل Cursor أو مطوّر Next.js لربط **لوحة القناة** بهذا الخادم. كل مسار وجسم واستجابة من `route:list` والكتالوج في **2026-09-24** (116/116 ✅). |
| التطبيق | Next.js 15 · App Router · TypeScript · TanStack Query v5 · RTL عربي · المنفذ **3001** |
| الحارس | `auth:channel` · التوكن = Sanctum personal access token |
| الملفات الشقيقة | `channel.json` (عقد آلي) · `docs/status/02-channel-dashboard.md` · الكتالوج `04-channel-catalog.php` + `05-channel-ops.php` |
| عند التعارض | `route:list` → الكنترولر/FormRequest → **`channel.json`** → **هذا الملف** → الكتالوج |

> **لـ Cursor.** اقرأ §0 أولاً. ابنِ ما يدرجه §5 فقط، بعميل §3. لا تختلق مساراً ولا حقلاً ولا مفتاح استجابة. إن احتاجت الشاشة شيئاً لا يعيده الخادم، اعرض حالة فارغة صادقة (§8) — لا تُحاكِ بيانات.
---

## المحتويات

| § | القسم |
|---|---|
| 0 | قواعد الوكيل |
| 1 | البيئة، الترويسات، البذرة |
| 2 | هيكل Next.js والصلاحيات والتنقّل |
| 3 | الغلاف، الأخطاء، العميل، التكرار، المال، الوقت |
| 4 | الدخول والجلسة |
| 5 | مرجع المسارات الحية — JSON + TypeScript |
| 6 | رحلات مركّبة |
| 7 | كتالوج الأخطاء → نصوص عربية |
| 8 | ما ليس حيّاً (لا تبنِ، لا تُحاكِ) |
| 9 | خريطة الشاشات + قائمة الإكمال |
| 10 | قائمة مراجعة عند إعادة الملفات |

---

## 0. قواعد الوكيل

1. **عميل HTTP واحد** (`apiClient`، §3.3). لا شاشة تستدعي `fetch` مباشرة. الـ hooks تستدعي الدوال؛ الصفحات تقرأ الـ hooks.
2. **كل استجابة غلاف.** نجاح `{data, meta}`، خطأ `{error:{code,message,details?}}`. التحليل عبر `parseEnvelope` / `ApiError` فقط.
3. **`error.code` هو العقد، `error.message` ليس كذلك.** الوسيط `SetAcceptLanguage` يفرض `en` على كل طلب. الواجهة تعرض النص العربي من §7 حسب `code`؛ تسجّل `message` في الـ log ولا تطبعه للمستخدم.
4. **كل كتابة تحمل `X-Idempotency-Key`** إلا `POST /channel/auth/request-otp` و`verify-otp`. الناقص → `400 idempotency_key_required`. مفتاح واحد لكل *نية مستخدم*؛ إعادة نفس النقرة تعيد **نفس** المفتاح؛ نقرة جديدة = مفتاح جديد.
5. **المال `int`** بأصغر وحدة. الليرة `decimals = 0`: `12000` تُعرض `12,000 ل.س`. لا `number` عشري على مسار مال. **الاستثناء الوحيد:** `delivery_fee` و`min_order_value` في `/channel/zones` — سلسلتان عشريتان بمكانين (`"50000.00"`).
6. **لا ترسل `X-Channel-Id`.** الترويسة تُكرَم فقط لـ `platform_admin`. مستأجر القناة من عضوية المستخدم (`defaultChannelId`). `channels[]` العائدة من الدخول **للعرض فقط** — لا تبديل قناة على هذا الحارس.
7. **الشاشات تُغلق بـ `permissions[]` من `verify-otp`.** `can('sc.orders.confirm')` يخفي الزر. **لا تبنِ على اسم الدور** (`channel_manager` / `sales_manager` / …) — القناة الحقيقية ستعدّل الأدوار.
8. **معرّف أجنبي أو مفقود → 404** لا 403. اعرض «غير موجود».
9. **توكن من حارس آخر على مسار قناة → `403 wrong_guard`.** امسح التوكن وأعد إلى الدخول.
10. **لوحة القيادة من اللقطة.** `GET /channel/dashboard` يقرأ `DailySnapshot` (مجدول 00:05 Asia/Damascus أو `php artisan reports:daily-snapshots`). قبل أول لقطة تظهر أصفار صادقة — لا تختلق GMV. بعد اللقطة: KPI + `charts` + `alerts` حقيقية من الطلبات.
11. **التجار 360 وIAM القناة حيّان.** `GET /channel/retailers/{id}` · `GET/POST /channel/users` · `GET /channel/reps/{id}/live` · `GET /channel/zones/coverage`. اربطها؛ لا تُحاكِ. رفع الوسائط عبر `POST /channel/media/upload` فقط.
12. **لا `sort` على أي قائمة.** Spatie يرفض الترتيب غير المصرّح. `per_page` افتراضي 25، سقف 100.
---

## 1. البيئة

### 1.1 المضيفون

| الهدف | الأساس | ملاحظة |
|---|---|---|
| API محلي | `http://127.0.0.1:8000` | `php artisan serve` |
| الإنتاج | `https://api.sentraxsy.com` | أكّد مع الفريق قبل الإطلاق |
| هذه اللوحة | المنفذ **3001** | ليست المنصة (3000) ولا المستودع |

كل المسارات أدناه نسبة إلى **`/api/v1`**.

```ts
// lib/config.ts
export const apiHost = process.env.NEXT_PUBLIC_API_HOST ?? 'http://127.0.0.1:8000';
export const basePath = '/api/v1';
export const baseUrl = `${apiHost}${basePath}`;
export const xClient = 'channel-web';
```

`.env.local`: `NEXT_PUBLIC_API_HOST=http://127.0.0.1:8000`

### 1.2 الترويسات — على كل طلب

| الترويسة | القيمة | متى |
|---|---|---|
| `Accept` | `application/json` | دائماً |
| `Content-Type` | `application/json` | الكتابات ما عدا الاستيراد |
| `Authorization` | `Bearer <token>` | كل شيء إلا الصحة و`/public/refs` وOTP |
| `X-Client` | `channel-web` | دائماً |
| `X-Device-Id` | UUID ثابت لكل متصفح | دائماً (مفتاح حدّ OTP) |
| `X-App-Version` | `1.0.0 (1)` | دائماً |
| `X-Idempotency-Key` | UUID v4 | كل `POST`/`PUT`/`PATCH`/`DELETE` إلا مساري OTP |
| `X-Channel-Id` | — | **أبداً** |

إعادة كتابة مخزّنة: ترويسة الاستجابة `Idempotent-Replayed: true`.

استيراد الكتالوج: `Content-Type` يتركه المتصفح (`multipart/form-data`).

### 1.3 OTP

| | محلي / testing (`OTP_BYPASS=true`) | staging / إنتاج |
|---|---|---|
| التحقق | **أي** `code` يمر، حتى الناقص | رمز عشوائي 6 أرقام (واتساب، SMS احتياطي) |
| أصفار ثابتة | **لا** — الأصفار لعملاء `rep-*` فقط | لا |
| شكل `code` | سلسلة `"000000"` لا عدداً | `size:6` |
| المحاولات / العمر / إعادة الإرسال | 5 · 300 ث · 60 ث | نفسها |

> **إطلاق:** `OTP_BYPASS` يُقبل فقط تحت `APP_ENV=local|testing`. على `staging`/`production` يُتجاهل. على المضيف الحقيقي اضبطوا `OTP_BYPASS=false` وفضّلوا `APP_ENV=production`.

لا يوجد `resend-otp` على حارس القناة. اطلب رمزاً جديداً من شاشة الهاتف.

### 1.4 حساب البذرة

| | |
|---|---|
| الهاتف | `+963900000001` (`0900000001` أيضاً — التطبيق يطبّع) |
| الاسم | Channel Admin |
| الدور | `channel_manager` — كل رموز `sc.*` المزروعة |
| القناة | `demo-channel`، id **1** بعد زرع جديد |
| الشكل الصحيح | `^\+9639\d{8}$` |

بعد `migrate:fresh` كل `otp_id` والتوكن ميت — اطلب رمزاً جديداً.

---

## 2. Next.js والصلاحيات والتنقّل

### 2.1 المجلدات

```
app/
  (auth)/login/page.tsx
  (shell)/layout.tsx          # شريط جانبي يمين + علوي — خلف can()
  (shell)/page.tsx            # لوحة القيادة
  (shell)/orders/...
  (shell)/catalog/{brands,categories,products}/...
  (shell)/pricing/...
  (shell)/offers/...
  (shell)/inventory/...
  (shell)/reps/...
  (shell)/returns/...
  (shell)/finance/{invoices,payments,aging,credit}/...
  (shell)/zones/page.tsx
  (shell)/notifications/...
  (shell)/content/{intro,banners,sliders}/...
  (shell)/loyalty/...
  (shell)/reports/...
  (shell)/settings/page.tsx
lib/
  api/client.ts               # fetch واحد + غلاف
  api/errors.ts
  api/idempotency.ts
  api/{auth,catalog,orders,reps,finance,...}.ts
  money.ts · phone.ts · time.ts
  session.ts                  # token + permissions في sessionStorage مفتاح `channel`
components/ui/                # DataTable, FilterBar, StatusBadge, MoneyText, EmptyState
```

التوكن في `sessionStorage` مفتاح **`channel`** — لا تخلطه مع `platform`. لا `localStorage` للتوكن.

### 2.2 الشريط الجانبي — العنصر يختفي إن لم توجد أي صلاحية قراءة

| القسم | يظهر إن | المسار |
|---|---|---|
| لوحة القيادة | `sc.dashboard.view` | `/` |
| الطلبات | `sc.orders.view` | `/orders` |
| الكتالوج | `sc.catalog.view` | `/catalog/products` |
| التسعير | `sc.pricing.view` | `/pricing` |
| العروض | `sc.offers.view` | `/offers` |
| المخزون | `sc.inventory.view` | `/inventory` |
| المندوبون | `sc.reps.view` | `/reps` |
| المرتجعات | `sc.returns.view` | `/returns` |
| المالية | `sc.finance.view` | `/finance/invoices` |
| المناطق | `sc.zones.view` | `/zones` |
| الإشعارات | `sc.notify.view` أو `sc.notify.templates` | `/notifications` |
| المحتوى | أي `sc.content.*` | `/content/intro` |
| الولاء | `sc.loyalty.manage` | `/loyalty` |
| التقارير | `sc.reports.view` | `/reports/sales` |
| التجّار | `sc.retailers.view` | `/retailers` |
| المستخدمون | `sc.iam.users_view` | `/users` |
| الإعدادات | `sc.settings.view` | `/settings` |

قسم بلا صلاحية **يختفي**، لا يُعطَّل. لا تبنِ أدواراً مخصّصة خارج `GET/POST /channel/users`.

### 2.3 الأدوار المزروعة (للمعرفة فقط — الواجهة لا تفرع عليها)

| الدور | ماذا يأخذ فعلياً بعد الزرع |
|---|---|
| `channel_manager` | كل `sc.*` المزروعة · يُستثنى من SOD-01 |
| `sales_manager` | وحدات orders / reps / dashboard / pricing — **ليس** `sc.offers.*` |
| `catalog_manager` | `sc.catalog.*` + `sc.content.*` + `sc.offers.*` + `sc.pricing.*` |
| `accountant` | `sc.finance.*` + `sc.returns.view` + `sc.returns.decide` |

البذرة الوحيدة ذات الحساب: `channel_manager`. IAM القناة حيّ عبر `GET /channel/users` و`POST /channel/users/invite` (§5.17).

### 2.4 SOD-01

القاعدة المزروعة الوحيدة: لا يجتمع `sc.orders.confirm` مع `sc.finance.payment`. التنفيذ → `403 sod_violation` إلا `channel_manager`. **أظهر الزر إن وُجدت الصلاحية** — إخفاؤه يخفي عطل الإسناد. الاعتماد المزدوج: المنشئ ≠ المعتمد، وإلا `403 sod_violation`.

SOD-02 وSOD-04 في DOC-08 **غير مزروعتين** — لا تحذيراً استباقياً عليهما.

### 2.5 التصميم (مختصر)

RTL أصيل: `<html lang="ar" dir="rtl">`. الشريط على اليمين. الأرقام غربية 0–9 و`tabular-nums`. المال عبر `MoneyText` فقط. الحالة بلون ثابت: `pending` تحذير، `confirmed`/`delivered`/`active` نجاح، `rejected`/`cancelled`/`disabled` خطر، `on_the_way` معلومات. الشاشة الفارغة أفضل من صف مختلق. غير مدعوم رسمياً تحت 768px.

---

## 3. العقد العابر

### 3.1 الغلاف

```jsonc
// نجاح
{ "data": { }, "meta": { "server_time": "2026-09-19T11:41:00+03:00" } }
// قائمة مرقّمة
{ "data": [ ], "meta": { "server_time": "...", "page": 1, "per_page": 25, "total": 412, "last_page": 17 } }
// خطأ
{ "error": { "code": "validation_failed", "message": "The zone id field is required.", "details": { "zone_id": ["..."] } } }
```

اعتماد مزدوج معلّق: `data.approval_request_id` + `meta.requires_dual_approval: true`.

غير مرقّمة (لا `page` في meta): شجرة الفئات، تغطية المناطق، السلايدر، قواعد الولاء، قوالب الإشعارات، اللوحة، الأعمار، التقرير، الهوامش، الإعدادات، الانترو، أداء العرض، إحصاء البنر، المحفظة.

`DELETE /channel/zones/{id}` → **204** جسم فارغ.

### 3.2 الأخطاء

```ts
// lib/api/errors.ts
export class ApiError extends Error {
  constructor(
    readonly status: number,          // 0 = لا استجابة
    readonly code: string,            // العقد
    readonly message: string,         // إنجليزي للـ log
    readonly details: Record<string, unknown> = {},
    readonly permission?: string,
  ) { super(message); }

  get userMessage(): string { return errorMessage(this); }
  fieldError(field: string): string | undefined {
    const v = this.details[field];
    return Array.isArray(v) && v.length ? String(v[0]) : undefined;
  }
}
```

401 `unauthenticated` / `token_revoked` خارج OTP → امسح التوكن → `/login`. 403 `wrong_guard` كذلك. 401 على `/channel/auth/*` يبقى في شاشة الدخول.

### 3.3 العميل

```ts
// lib/api/client.ts
const TOKEN_KEY = 'channel';

export async function api<T>(
  method: string,
  path: string,
  opts?: { body?: unknown; query?: Record<string, unknown>; key?: string; form?: FormData },
): Promise<{ data: T; meta: Record<string, unknown>; replayed: boolean }> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Client': 'channel-web',
    'X-Device-Id': deviceId(),
    'X-App-Version': '1.0.0 (1)',
  };
  const token = sessionStorage.getItem(TOKEN_KEY);
  if (token && !path.startsWith('/health') && !path.startsWith('/public/') && !path.startsWith('/channel/auth/')) {
    headers.Authorization = `Bearer ${token}`;
  }
  if (opts?.body !== undefined) headers['Content-Type'] = 'application/json';
  if (opts?.key) headers['X-Idempotency-Key'] = opts.key;
  delete headers['X-Channel-Id'];

  const url = new URL(baseUrl + path);
  for (const [k, v] of Object.entries(opts?.query ?? {})) {
    if (v !== undefined && v !== '') url.searchParams.set(k, String(v));
  }

  let res: Response;
  try {
    res = await fetch(url, {
      method,
      headers,
      body: opts?.form ?? (opts?.body !== undefined ? JSON.stringify(opts.body) : undefined),
    });
  } catch {
    throw new ApiError(0, 'network', 'No connection');
  }

  if (res.status === 204) return { data: null as T, meta: {}, replayed: false };
  const json = await res.json();
  if (res.status < 200 || res.status >= 300) {
    const e = json.error ?? {};
    const err = new ApiError(res.status, e.code ?? 'http_error', e.message ?? '', e.details ?? {}, e.permission);
    if (err.status === 401 && !path.startsWith('/channel/auth/')) signOut();
    if (err.code === 'wrong_guard') signOut();
    throw err;
  }
  return {
    data: json.data as T,
    meta: json.meta ?? {},
    replayed: res.headers.get('Idempotent-Replayed') === 'true',
  };
}
```

نمط المفتاح في النموذج: يُولَّد عند أول إرسال، يُحتفظ به عند `operation_in_progress`، يُرمى عند نجاح أو عند 422/403/404 (الجسم سيتغيّر).

### 3.4 المال والوقت والهاتف

```ts
export const moneySyp = (n: number) => `${new Intl.NumberFormat('en').format(n)} ل.س`;
// مناطق التغطية فقط:
export const decimalFee = (s: string) => `${s} ل.س`;

export const damascusTime = (iso: string) =>
  new Intl.DateTimeFormat('en', { hour: '2-digit', minute: '2-digit', hour12: false }).format(new Date(iso));

export function normalizePhone(input: string): string {
  let d = input.replace(/[^\d+]/g, '').replace(/^\+/, '');
  if (d.startsWith('00963')) d = d.slice(2);
  if (d.startsWith('0')) d = '963' + d.slice(1);
  if (d.length === 9 && d.startsWith('9')) d = '963' + d;
  return '+' + d;
}
export const isSyrianMobile = (n: string) => /^\+9639\d{8}$/.test(n);
```

`created_at` في مورد الإعدادات قد يكون UTC؛ بقية الأوقات `+03:00`. اعرض ISO كما هو في المال والتسليم.

---

## 4. الدخول والجلسة

```
/login ──request-otp──▶ رمز ──verify-otp──▶ sessionStorage(token, permissions, channels)
                                              │
                                              ▼
                                         (shell)  can(code)
```

لا `GET /channel/me` ولا `POST /channel/auth/logout`. الجلسة = حمولة `verify-otp`. الخروج = امسح `sessionStorage` محلياً.

مستخدم بلا عضوية قناة → `403 insufficient_permission` على التحقق — ابقَ في الدخول، «لا عضوية قناة على هذا الرقم».

`channels[]` شارة اسم في الشريط العلوي. عنصر واحد في البذرة. لا قائمة تبديل.

---

## 5. مرجع المسارات

أسطورة: 🔁 يحتاج المفتاح · 🔓 بلا Bearer · 📄 مرقّم · 👥 اعتماد مزدوج محتمل.

كل كتلة `Response` هي `data` فقط. الحالة 200 إلا ما يُذكر (201 إنشاء، 204 حذف تغطية).

**95 مساراً حيّاً** في `channel.json`: 87 تحت `/channel` + `/health` + `/public/refs` + 6 مراجع مشتركة.

### 5.0 فهرس `/channel`

| مجموعة | العدد | صلاحيات القراءة |
|---|---:|---|
| دخول | 2 | — |
| إعدادات | 2 | `sc.settings.view` |
| لوحة + تقارير + jobs | 5 | `sc.dashboard.view` / `sc.reports.*` |
| كتالوج | 14 | `sc.catalog.view` |
| تسعير | 5 | `sc.pricing.view` |
| عروض | 6 | `sc.offers.view` |
| مخزون | 5 | `sc.inventory.view` |
| مستودعات | 1 | `sc.inventory.view` |
| طلبات | 10 | `sc.orders.view` |
| مرتجعات | 2 | `sc.returns.view` |
| مندوبون (+ محفظة/تسوية/سقف/حيّ) | 13 | `sc.reps.view` |
| مالية (+ تجّار 360 / مجموعات) | 9 | `sc.finance.view` / `sc.retailers.view` |
| تغطية مناطق + فجوات | 4 | `sc.zones.view` |
| إشعارات | 4 | `sc.notify.*` |
| محتوى | 7 | `sc.content.*` |
| ولاء | 5 | `sc.loyalty.manage` |
| مستخدمو القناة | 2 | `sc.iam.users_*` |
---

### 5.1 صحة ومراجع 🔓

`GET /health` — دائماً 200. `status`: `ok` \| `degraded`. خطأ النقل فقط يمنع الدخول.

`GET /public/refs?since=` — بلا مصادقة. لقطات `activity_types` و`sale_units` و`root_categories` و`equipments` و`zones` و`governorates`. **القنوات ليست هنا.** ادمج حسب `id` واحذف `status != active`.

`GET /governorates` · `GET /zones` · `GET /currencies` — تحتاج Bearer (أي حارس). مرقّمة. ابحث المحافظات بـ `search` أعلى المستوى و`filter[status]`. **لا** تستدعِ `/platform/refs/*` (حارس خطأ).

عملة العرض: `decimals` ينظّم العرض؛ المبلغ المخزَّن يبقى `int`. لا مفتاح `is_base`.

---

### 5.2 OTP 🔓 (بلا مفتاح تكرار)

`POST /channel/auth/request-otp` — `{ "phone": "+963900000001" }` → `{ "otp_id": "otp_ab12cd" }`

الجسم `phone` فقط. `X-Device-Id` مفتاح الحدّ. أخطاء: `422` هاتف · `429 rate_limited`.

`POST /channel/auth/verify-otp` — `{ "otp_id": "otp_ab12cd", "code": "000000" }`

`code` **سلسلة**. لا `device_id` في الجسم.

```json
{ "token": "20|…", "channels": [{ "id": 1, "name": "Demo Channel" }],
  "permissions": ["sc.dashboard.view", "sc.orders.view"] }
```

احفظ التوكن والصلاحيات فوراً. `403 insufficient_permission` = لا عضوية. ابقَ في الدخول.

```ts
export const requestOtp = (phone: string) =>
  api<{ otp_id: string }>('POST', '/channel/auth/request-otp', { body: { phone: normalizePhone(phone) } });
export const verifyOtp = (otp_id: string, code: string) =>
  api<VerifyPayload>('POST', '/channel/auth/verify-otp', { body: { otp_id, code } });
```

---

### 5.3 إعدادات القناة 🔧 (حيّ بلا صف كتالوج)

`GET /channel` 🔒 `sc.settings.view`

```json
{ "id": 1, "name": "Demo Channel", "slug": "demo-channel", "legal_name": "Demo Channel LLC",
  "tax_number": null, "phone": "+963911000000", "email": null, "status": "active",
  "allowed_next": ["suspended", "archived"], "settings": {}, "created_at": "2026-09-01T00:00:00+00:00" }
```

`allowed_next` لانتقالات **المنصة**. لا تعرض أزرار إيقاف/أرشفة هنا — هذا الحارس لا يملك `POST /platform/channels/{id}/transition`.

`PUT /channel` 🔁 🔒 `sc.settings.update` — جزئي: `name` `legal_name` `tax_number` `phone` `email` `settings`. لا `status` ولا `slug`.

---

### 5.4 لوحة القيادة والتقارير

`GET /channel/dashboard` 🔒 `sc.dashboard.view`

الشكل من الكنترولر عند غياب اللقطة — **هذه الحقيقة**:

```json
{ "kpis": { "sales": 0, "orders_by_status": {}, "cash_collected": 0,
            "receivables": { "total": 0, "overdue": 0 },
            "retailers": { "active": 0, "registered": 0, "new": 0 },
            "avg_order_value": 0, "avg_confirm_time": 0, "avg_delivery_time": 0, "fill_rate": 0 },
  "alerts": [],
  "charts": { "daily_sales": [], "by_zone": [], "top_products": [], "top_retailers": [],
              "rep_performance": [], "heatmap": [] } }
```

`meta.snapshot_date` قد يكون `null` قبل أول لقطة. عيّنة الكتالوج ذات الـ 62 مليوناً **خطأ**. بعد `reports:daily-snapshots` (أو الجدول 00:05 Asia/Damascus): `kpis` و`charts` و`alerts` من الطلبات الحقيقية — لا تُصفّر يدوياً.
`GET /channel/reports/{type}` 🔒 `sc.reports.view` — `type` ∈ `sales|products|retailers|reps|zones|inventory|finance|offers|operations`. نوع مجهول → 200 وصفوف فارغة. `date_from`/`date_to` تختار `DailySnapshot` داخل المدى؛ إن لم توجد لقطة في المدى → آخر لقطة.

`POST /channel/reports/{type}/export` 🔁 🔒 `sc.reports.export` — `{ "format": "xlsx", "filters": {} }` → `{ "job_id": "job_rep_exp_…" }`. `format` ∈ `xlsx|pdf|csv`. راقب الحالة بـ `GET /channel/jobs/{id}` (§5.17) حتى `done` ثم افتح `result.download_url`.

`GET /channel/reports/margins?date_from=&date_to=` 🔒 `sc.reports.margins` → `{ "by_product": [], "by_zone": [] }` — نفس اختيار اللقطة. بعد اللقطة تُملأ من هوامش الطلبات.
---

### 5.5 الكتالوج

#### علامات 📄

`GET /channel/brands?filter[status]=active&filter[activity_type_id]=3&filter[search]=نور&sort=order` → `{ id, name_ar, name_en, status, order }`

`GET /channel/brands/{id}` → جسم المحرّر (`logo`/`banner` = media_id، `logo_url`/`banner_url` محلولة، `activity_type_ids`، `sliders[]`). قناة أخرى → 404.

`POST /channel/media/upload` 🔁 multipart: `file` + `type` ∈ `image|video` → `{ media_id, url, type, mime, size }`. صورة ≤8MB، فيديو ≤50MB.

`POST /channel/brands` 🔁 201 `{ id }`

`PUT /channel/brands/{id}` 🔁 `{ id }` — نفس جسم الإنشاء؛ `sliders[]` يستبدل المجموعة؛ `status: disabled` يخفي البراند ومنتجاته من تطبيقات التاجر/المندوب دون حذف بيانات القناة.

| الحقل | قاعدة |
|---|---|
| `name_ar` | إلزامي ≤160، فريد داخل القناة |
| `name_en` | اختياري ≤160 |
| `description` | إلزامي ≤300 |
| `logo` | إلزامي — media_id من الرفع؛ صورة مربعة ≥512×512 |
| `banner` | اختياري — media_id |
| `activity_type_ids` | إلزامي ≥1 من `GET /public/refs` |
| `order` | اختياري — ترتيب البطاقات |
| `status` | `active` \| `disabled` |
| `sliders[]` | `name` + `source` ∈ `algorithm\|manual` + `source_id?` + `count?` + `order?` |

#### فئات

`GET /channel/categories/tree` — شجرة غير مرقّمة. الجذور = فئات المنصة الجذرية (`level: 1`). `parent_id` للفئات التي تنشئها القناة إلزامي ≥1.

`POST /channel/categories` 🔁 201 — `{ name, parent_id, image?, icon?, order?, activity_type_ids? }`

`POST /channel/categories/reorder` 🔁 — `{ "moves": [{ "id": 341, "parent_id": 10, "order": 2 }] }`

#### منتجات 📄

`GET /channel/products?filter[search]=زيت&filter[brand_id]=12&filter[category_id]=340&filter[status]=active&filter[zone_id]=12`

الصف الحي **أربعة مفاتيح فقط:**

```json
{ "id": 880, "sku": "OIL-SUN-1L", "name_ar": "زيت دوار الشمس 1 لتر", "status": "active" }
```

لا صورة، لا سعر، لا مخزون في القائمة. `GET /channel/products/{id}` يعيد جسم المحرّر الكامل (`pricing` قد يكون `null` — استخدم `PUT /products/{id}/pricing`).

`POST /channel/products` 🔁 201 `{ id, sku? }` — `PUT /channel/products/{id}` 🔁 نفس FormRequest (`name_ar` + `sku` إلزاميان دائماً حتى في التحديث).

`pricing.type`: `simple` \| `tiered`. `pricing.base_price` و`tiers[].price` **int**. `status`: `draft` \| `active` \| `disabled`.

`POST /channel/products/{id}/variants/generate` 🔁 — `{ "axes": [{ "name": "الحجم", "values": ["1ل", "5ل"] }] }`

`POST /channel/products/bulk` 🔁 — `{ "product_ids": [880], "action": "activate"|"disable"|"delete_draft", "payload"? }` سقف 200.

`POST /channel/catalog/import` 🔁 — **multipart**: `file` + `type=products` + `dry_run` اختياري. جسم JSON → 422.

`GET /channel/catalog/export?filter[status]=active` — غلاف JSON، ليس تنزيلاً.

---

### 5.6 التسعير

`GET /channel/price-lists?filter[type]=zone` 📄 → `{ id, name, type, status }`

`type` ∈ `base|zone|group|retailer`. `status` ∈ `active|scheduled|expired|disabled`.

`POST /channel/price-lists` 🔁 201

```json
{ "name": "قائمة المزة", "type": "zone", "zone_ids": [12],
  "adjustment": { "mode": "percent", "value": -5 },
  "effective_from": "2026-10-01", "reason": "موسم" }
```

`adjustment.value` int (سالب لتخفيض نسبة). `mode` ∈ `percent|fixed`.

`PUT /channel/products/{id}/pricing` 🔁 — `{ type, base_price, currency_id?, tiers?, reason? }` نفس تسعير المنتج.

`POST /channel/price-lists/{id}/schedule` 🔁 — `{ "effective_from": "2026-10-01", "changes": [{ "product_id": 880, "base_price": 11000 }] }`

`POST /channel/pricing/bulk-update` 🔁 — `{ "product_ids": [880], "mode": "percent", "value": -5, "reason": "…" }` `reason` إلزامي، سقف 200.

`GET /channel/pricing/change-log?filter[product_id]=880&filter[date]=2026-09-19` 📄 → `{ at, user, product, before, after, reason }` المبالغ int.

`PUT /channel/reps/{id}/discount-cap` 🔁 🔒 `sc.reps.update` — `{ "max_discount_percent": 10, "max_cash_hold": 5000000 }`. النسبة 0–100. `max_cash_hold` 0 = بلا سقف. `{id}` = AppUser id من قائمة المندوبين.

---

### 5.7 العروض

`GET /channel/offers?filter[status]=active&filter[type]=buy_x_get_y` 📄 → `{ id, name, type, status }` فقط.

`type` ∈ `product_discount|invoice_discount|buy_x_get_y|bundle|tiered_discount|gift`.

`POST /channel/offers` 🔁 201 `{ id }` — الجسم الكامل في `channel.json` / FormRequest: `name`, `type`, `components[]`, `rules`, `rewards`, `targeting`, `constraints`, `stackable`, `priority`, `status`.

`PATCH /channel/offers/{id}/stop` 🔁 — `{ "reason": "…" }` إلزامي ≤255.

`GET /channel/offers/{id}/performance` — `net_margin` = `linked_sales − cost` (عندما `product_base_prices.cost_price` مضبوط؛ وإلا التكلفة 0). `conversion_rate` سلم 10^4 من مشاهدات التجار الفريدة ÷ المستفيدين.

**لا `GET /channel/offers/{id}` ولا PUT.**

---

### 5.8 المخزون

`GET /channel/inventory/levels?filter[warehouse_id]=1&filter[product_id]=880` 📄

```json
{ "product": { "id": 880, "name_ar": "زيت دوار الشمس 1 لتر" }, "variant": null,
  "warehouse": { "id": 1, "name": "مستودع المزة" },
  "available": 420, "reserved": 30, "in_transit": 12, "damaged": 2 }
```

`GET /channel/warehouses` — منتقي `{ id, name, status }`. `warehouse_id` من القائمة.

`POST /channel/inventory/adjust` 🔁 👥 🔒 `sc.inventory.adjust`

```json
{ "product_id": 880, "variant_id": null, "warehouse_id": 1, "qty_delta": -3, "reason": "تلف" }
```

نجاح مباشر: `{ "movement_id": 7001, "available": 417 }`. وإلا `{ "approval_request_id": 9 }` + `meta.requires_dual_approval`. النداء الثاني من مستخدم آخر: نفس الجسم + `approval_request_id` + `approval_reason`.

`POST /channel/inventory/transfers` 🔁 201 — `{ from_warehouse_id, to_warehouse_id, lines: [{ product_id, variant_id?, qty }] }`

`GET /channel/inventory/movements` 📄 → `{ id, at, type, qty_before, qty_after }`

`PUT /channel/inventory/reorder-points` 🔁 — `{ "items": [{ "product_id": 880, "warehouse_id": 1, "point": 50 }] }`

---

### 5.9 الطلبات الفرعية

`GET /channel/sub-orders?filter[status]=pending&filter[zone_id]=12&filter[retailer_id]=481&filter[rep_id]=70&filter[source]=retailer_app&filter[waiting_over_minutes]=30` 📄

```json
{ "id": 9001, "sub_order_no": "SO-9001", "status": "pending", "zone_id": 12, "total": 48000 }
```

`filter[waiting_over_minutes]` يعمل رغم أنه ليس Spatie AllowedFilter. **لا `sort`.** `total` int.

`GET /channel/sub-orders/{id}` — أزرار الحالة من `allowed_actions` لا من خريطة محلية:

```json
{ "header": { "sub_order_no": "SO-9001", "status": "pending", "shop": "بقالية النور" },
  "lines": [{ "id": 1, "product_id": 880, "qty": 4, "unit_price": 12000 }],
  "financials": { "subtotal": 48000, "discount": 0, "total": 48000 },
  "note": null,
  "timeline": [{ "stage": "pending", "at": "2026-09-19T09:10:00+03:00" }],
  "allowed_actions": ["confirm", "reject", "edit_lines"] }
```

أسماء المنتجات: كاش من قائمة الكتالوج (`id → name_ar`). السطر لا يحمل اسماً.

| الإجراء | المسار | الجسم |
|---|---|---|
| تأكيد 🔁 | `POST …/{id}/confirm` | `{}` |
| تأكيد جماعي 🔁 | `POST …/bulk-confirm` | `{ ids: number[] }` سقف 50. 200 جزئي: `confirmed[]` + `failed[]` |
| رفض 🔁 | `POST …/{id}/reject` | `{ reason }` إلزامي ≤255 |
| تعديل بنود 🔁 | `PATCH …/{id}/lines` | `{ changes: [{ line_id, qty, removed? }], reason }` `reason` إلزامي |
| إسناد 🔁 | `POST …/assign` | `{ sub_order_ids, rep_id, mode?: "manual"\|"auto"\|"bulk_zone" }` |
| إعادة إسناد 🔁 | `POST …/{id}/reassign` | `{ rep_id, reason? }` 409 بعد استلام العهدة |
| جدولة 🔁 | `POST …/{id}/schedule` | `{ scheduled_at, reason? }` → `status: postponed` |
| إلغاء 🔁 | `POST …/{id}/cancel` | `{ reason }` إلزامي |

`rep_id` = AppUser id من `GET /channel/reps`. 409 `illegal_transition` · 423 `credit_limit_exceeded` على التأكيد.

---

### 5.10 المرتجعات

`GET /channel/return-requests?filter[type]=return&filter[status]=pending&filter[rep_id]=70&filter[zone_id]=12` 📄 → `{ id, request_no, type, status }`

`POST /channel/return-requests/{id}/decide` 🔁 — `{ "decision": "approve"|"reject", "reason"? }`

---

### 5.11 المندوبون

`GET /channel/reps?filter[status]=pending_review` 📄

```json
{ "id": 70, "name": "عمر الشامي", "phone": "+963932000001", "status": "active",
  "zone_ids": [12, 13], "on_duty": true, "max_discount_percent": 10, "max_cash_hold": 5000000 }
```

`id` = **AppUser id** (نفسه في الإسناد والتسوية والسقف والمحفظة). `status` ∈ `pending_review|active|rejected|disabled`.

`GET /channel/reps/{id}` — نفس البطاقة. مندوب قناة أخرى → 404.

`POST /channel/reps/{id}/approve` 🔁 — `{ reason? }` → `{ id, status: "active" }` من `pending_review`. 409 انتقال غير قانوني.

`POST /channel/reps/{id}/reject` 🔁 — `{ reason }` إلزامي ≤500 → `rejected`.

`POST /channel/reps/{id}/disable` 🔁 — `{ reason }` إلزامي → `disabled`.

`GET /channel/rep-zone-requests?filter[status]=pending_approval` 📄 → `{ id, rep_user_id, zone_id, note, status }`

`POST /channel/rep-zone-requests/{id}/decide` 🔁 — `{ decision: "approve"|"reject", reason? }`

`GET /channel/rep-sourced-shops?filter[status]=pending_sync` 📄 → `{ id, rep_user_id, shop_name, phone, zone_id, status, retailer_id }`

`POST /channel/rep-sourced-shops/{id}/decide` 🔁 — `{ decision, reason? }`. الموافقة تفعّل التاجر وتربط المحل.

`GET /channel/reps/{id}/wallet` 🔒 `sc.reps.wallet` — نفس حساب تطبيق المندوب:

```json
{ "net_balance": 250000,
  "stats": { "invoices_delivered": 12, "collected_total": 1750000, "receivables": 48000 },
  "today": { "invoices": 2, "collected": 90000, "receivables": 12000 } }
```

`POST /channel/reps/{id}/settle` 🔁 🔒 `sc.reps.settle` — `{ "amount": 1500000, "operation_no": "OP-7781" }` `operation_no` ≤32. المبلغ int ≥1.

---

### 5.12 المالية

`GET /channel/invoices?filter[status]=open&filter[retailer_id]=481&filter[rep_id]=70&filter[date]=2026-09-19` 📄

```json
{ "id": 501, "no": "INV-501", "total": 48000, "status": "open" }
```

**قائمة فقط أربعة مفاتيح.** `GET /channel/invoices/{id}` → `{ id, no, status, retailer_id, rep_id, total, paid_total, credited_total, remaining, lines[], created_at }`. `status` ∈ `open|void|credited`.

`GET /channel/retailers?filter[zone_id]=12&filter[status]=active&filter[search]=نور` 📄 🔒 `sc.retailers.view` — `{ id, shop_name, phone, zone_id, status }` (`id` = `retailer_profile`).

`POST /channel/invoices/{id}/credit-note` 🔁 👥 — `{ lines: [{ line_id, qty, amount }], reason }`. `amount` int. قد يعود `approval_request_id`. بنود الفاتورة من `GET /channel/invoices/{id}` → `lines[]`.

`POST /channel/invoices/{id}/void` 🔁 👥 — `{ reason }` · نفس الاعتماد المزدوج.

`POST /channel/payments` 🔁 🔒 `sc.finance.payment` — SOD-01.

```json
{ "retailer_id": 481, "amount": 20000, "method": "cash", "invoice_id": 501 }
```

`method` ∈ `cash|bank|card`. `invoice_id` اختياري. `retailer_id` من `GET /channel/retailers` (معرّف `retailer_profile`).

`GET /channel/finance/aging?group_by=zone` — `group_by` ∈ `zone|rep`. `buckets` مجاميع القناة؛ `groups[]` يقسم حسب `zone_id` أو `rep_id` (`key` / `label` / `buckets`).

`PUT /channel/retailers/{id}/credit` 🔁 🔒 `sc.retailers.credit`

```json
{ "credit_limit": 500000, "grace_days": 7, "on_exceed": "block" }
```

`on_exceed` ∈ `warn|block|manual_approval`. `{id}` يُكتب. التأكيد اللاحق قد 423 `credit_limit_exceeded`.

---

### 5.13 تغطية المناطق 🔧

`GET /channel/zones` — مصفوفة غير مرقّمة (ليست `GET /zones` العامة).

```json
{ "id": 1, "zone_id": 12, "zone_name": "المزة",
  "delivery_days": ["sun", "tue", "thu"],
  "delivery_fee": "0.00", "min_order_value": "50000.00" }
```

`delivery_fee` / `min_order_value` **سلاسل عشرية**. `delivery_days[]` ∈ `sun…sat`.

`POST /channel/zones` 🔁 201 — upsert حسب `zone_id`. نفس الحقول. `zone_id` من `GET /zones` أو `/public/refs`.

`DELETE /channel/zones/{channelZone}` 🔁 → **204**. المعرّف = صف التغطية (`id` أعلاه) لا `zone_id`.

---

### 5.14 الإشعارات

`POST /channel/notifications` 🔁 🔒 `sc.notify.send`

```json
{ "title": "عرض جديد", "body": "اشترِ 10 واحصل على 1",
  "targeting": { "type": "zone", "ids": [12] },
  "channels": ["push", "in_app"] }
```

`channels[]` ∈ `push|in_app|whatsapp` ≥1. → `{ id, status: "queued" }`. الإرسال الفعلي للطابور — لا تضمن وصولاً فورياً.

`GET /channel/notifications/templates` — مصفوفة: `{ event_key, title, enabled, channels }`.

`PUT /channel/notifications/templates` 🔁 — `{ event_key, title, body?, enabled, channels }` → `{ event_key }`.

`GET /channel/notifications/log` 📄 → `{ at, recipient, template, status, failure_reason }`. `recipient` قد يكون `null`.

---

### 5.15 المحتوى

`GET /channel/content/intro` 🔒 `sc.content.intro` — مخزن فارغ:

```json
{ "enabled": false, "text": null, "media_type": null, "media_id": null, "duration": 0,
  "targeting": { "activity_type_ids": [], "zone_ids": [] } }
```

`PUT /channel/content/intro` 🔁 — `enabled` إلزامي. `media_type` ∈ `image|video`. **الاستجابة `{ enabled }` فقط** — أعد الجلب للنموذج.

تطبيق المندوب **لا** يستدعي هذا المسار (`wrong_guard`). يقرأ `GET /public/content/intro` (انترو المنصة الافتراضي).

`GET /channel/content/banners` 📄 → `{ id, media_type, placements }`.

`POST /channel/content/banners` 🔁 → `{ id }`. `media_id` سلسلة. `placements` مصفوفة ≥1. لا تحديث ولا حذف.

`GET /channel/content/banners/{id}/stats` — `{ impressions, clicks, ctr }`. **`ctr` عدد صحيح بنقاط أساس 10^4** (840/12000 = 700). الكتالوج `0.07` خطأ. عند صفر مشاهدات `ctr = 0`.

`GET /channel/content/sliders` — مصفوفة غير مرقّمة: `{ id, name, source, algorithm }`.

`POST /channel/content/sliders` 🔁 → `{ id }`. `source` ∈ `manual|brand|category|offers|algorithm`.

الوسائط للكتالوج عبر `POST /channel/media/upload`؛ بنر المحتوى ما زال يقبل `media_id` من نفس الرفع.

---

### 5.16 الولاء

`GET /channel/loyalty/rules` — مخزن فارغ `{ retailer_rules: [], rep_rules: [], tiers: [] }`.

`PUT /channel/loyalty/rules` 🔁 — الثلاثة المفاتيح **إلزامية مصفوفات** → `{ updated: true }`.

`GET /channel/loyalty/rewards` 📄 → `{ id, name, points_cost, stock }`.

`POST /channel/loyalty/rewards` 🔁 → `{ id }`. `points_cost` ≥1 int، `stock` ≥0 int، `expires_at` تاريخ اختياري.

`PUT /channel/loyalty/rewards/{id}` 🔁 · `PATCH /channel/loyalty/rewards/{id}/stop` 🔁 — إيقاف مع سبب.

---

### 5.17 تاجر 360 · مجموعات · تغطية · IAM · مهمة

`GET /channel/retailers` 📄 🔒 `sc.retailers.view` — قائمة تغطية.

`GET /channel/retailers/{id}` 🔒 `sc.retailers.view` — **بطاقة 360**:

```json
{ "id": 481, "shop_name": "بقالية النور", "phone": "+963931000002",
  "zone_id": 12, "activity_type_id": 3, "status": "active",
  "credit": { "credit_limit": 500000, "grace_days": 7, "on_exceed": "block" },
  "recent_orders": [{ "id": 9001, "sub_order_no": "SO-9001", "status": "delivered", "total": 120000 }],
  "top_products": [{ "product_id": 880, "name": "زيت", "qty": 40 }] }
```

أجنبي / خارج التغطية → 404.

`GET /channel/reps/{id}/live` 🔒 `sc.reps.view` → `{ lat, lng, at, on_duty }`. بلا ping: `lat`/`lng`/`at` = null و`on_duty` يبقى.

`GET /channel/zones/coverage` 🔒 `sc.zones.view` → `{ without_reps: number[], without_warehouse: number[] }`.

`GET /channel/users` 🔒 `sc.iam.users_view` — مصفوفة `{ id, name, phone, role, status, last_login_at }`.

`POST /channel/users/invite` 🔁 🔒 `sc.iam.users_manage`

```json
{ "name": "سارة", "phone": "+963933000001", "invite_via": "sms" }
```

→ `{ invite_id, expires_at }` (دعوة 72 ساعة).

`GET /channel/jobs/{id}` 🔒 `sc.reports.view` — حالة تصدير كتالوج/تقرير:

```json
{ "id": "job_…", "type": "report_export", "status": "done", "progress": 100,
  "result": { "download_url": "…" }, "error": null, "created_at": "…", "finished_at": "…" }
```

`status` ∈ `queued|running|done|failed`. أجنبي → 404.

### إعدادات القناة — ساعات هدوء وSLA

`PUT /channel` يقبل `settings`:

```json
{ "quiet_hours": { "from": "22:00", "to": "08:00" }, "returns_sla_hours": 24 }
```

(أو `start`/`end` بدل `from`/`to`). يؤجّل إرسال الإشعار؛ يضبط `sla_due_at` على طلبات الإرجاع.

`GET /channel/return-requests` يعيد أيضاً `sla_due_at` و`overdue`.

`POST /channel/sub-orders/assign` — `mode`: `manual` (يتطلب `rep_id`) | `auto` | `bulk_zone` (+ `zone_id` اختياري). بلا مندوب on-duty يغطي المنطقة → 422.

---

## 6. رحلات مركّبة

### 6.1 أول تشغيل

`GET /health` → `GET /public/refs` (كاش pickers) → `request-otp` → `verify-otp` (احفظ التوكن + `permissions`) → `GET /channel/dashboard` (أصفار حتى اللقطة؛ بعدها KPI حقيقية) بالتوازي مع `GET /channel/sub-orders?filter[status]=pending&per_page=1` لعدّاد الشريط (`meta.total`).

### 6.2 تأكيد طلب صباح

قائمة `filter[status]=pending` → تفاصيل (`allowed_actions`) → `confirm` أو `bulk-confirm` حتى 50 → إن 423 ائتمان، افتح سقف التاجر بمعرّف مكتوب → إن 409 حدّث القائمة. بعد التأكيد: إسناد `rep_id` من `GET /reps?filter[status]=active`.

### 6.3 اعتماد مندوب جديد

`GET /reps?filter[status]=pending_review` → بطاقة → `approve` (reason اختياري) أو `reject` (reason إلزامي) → طابور المناطق `pending_approval` → طابور المحلات `pending_sync` → `decide`.

### 6.4 تسوية نقد المندوب

`GET /reps/{id}/wallet` → `settle` بـ `operation_no` من المحاسب. نفس الرقم يعيد نفس التسوية (مفتاح تكرار + `operation_no`).

### 6.5 نهاية اليوم المالية

فواتير `filter[status]=open` → دفعة مكتبية (SOD-01) → أعمار الذمم (`buckets` + `groups[]`) → تصدير تقرير → احتفظ بـ `job_id` وراقب `GET /channel/jobs/{id}` حتى `done` ثم نزّل الملف.
---

## 7. الأخطاء → العربية

```ts
const byCode: Record<string, string> = {
  network: 'تعذّر الاتصال بالخادم. تحقق من الشبكة وأعد المحاولة.',
  unauthenticated: 'انتهت الجلسة. سجّل الدخول من جديد.',
  token_revoked: 'انتهت الجلسة. سجّل الدخول من جديد.',
  otp_invalid: 'رمز التحقق غير صحيح.',
  otp_expired: 'انتهت صلاحية الرمز. اطلب رمزاً جديداً.',
  wrong_guard: 'هذا الحساب ليس حساب لوحة القناة.',
  insufficient_permission: 'هذا الإجراء غير متاح لحسابك.',
  sod_violation: 'فصل المهام: لا يجتمع تأكيد الطلب مع تسجيل الدفعة، أو المعتمد هو المنشئ.',
  not_found: 'غير موجود.',
  illegal_transition: 'هذه الخطوة غير مسموحة الآن.',
  conflict: 'تعذّر تنفيذ الطلب بسبب تعارض.',
  idempotency_key_conflict: 'أُعيد إرسال الطلب ببيانات مختلفة. ابدأ من جديد.',
  idempotency_key_required: 'خطأ داخلي (مفتاح التكرار مفقود).',
  operation_in_progress: 'العملية ما زالت قيد التنفيذ. انتظر قليلاً.',
  validation_failed: 'تحقق من الحقول المدخلة.',
  plan_limit_exceeded: 'وصلت القناة إلى حد الخطة.',
  credit_limit_exceeded: 'التاجر تجاوز سقف الائتمان.',
  rate_limited: 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.',
  maintenance_mode: 'الخدمة قيد الصيانة.',
  http_error: 'حدث خطأ غير متوقع.',
};
```

| HTTP | السلوك |
|---|---|
| 400 | `idempotency_key_required` — عطل عميل |
| 401 | امسح التوكن إلا على `/channel/auth/*` |
| 403 | `wrong_guard` خروج · `insufficient_permission` على OTP = لا عضوية · `sod_violation` أبقِ النموذج |
| 404 | «غير موجود» |
| 409 | حوار + حدّث القائمة · `operation_in_progress` احتفظ بالمفتاح |
| 422 | ظلل الحقول من `details` |
| 423 | سقف خطة أو ائتمان — حوار |
| 429 | تراجع؛ مؤقّت OTP |
| 204 | نجاح حذف تغطية — لا JSON |
| 0 | شريط «تعذّر الاتصال» + إعادة |

---

## 8. ليس حيّاً — لا تبنِ ولا تُحاكِ

| المسار / الميزة | البديل |
|---|---|
| `POST /channel/auth/logout` · `GET /channel/me` | امسح التوكن محلياً؛ الجلسة = verify-otp |
| `POST /channel/media` | `POST /channel/media/upload` |
| `/platform/*` و `/app/*` (من لوحة القناة) | حارس خطأ — ما عدا معرفة أن CTR البنر يُملأ من تطبيق التاجر |
| `GET /channel/activity-types` | `GET /public/refs` |
| `on_exceed: manual_approval` للائتمان | يُحفظ؛ السلوك = **block** (423) بلا طابور موافقة |
| مستودع offline كامل · مضلعات مناطق · تقويم قناة · طابور موافقة ائتمان | مؤجّل (`docs/plan/channel-warehouse-v1.1.md`) |
| خريطة مندوبين مجمّعة | نقطة واحدة: `GET /channel/reps/{id}/live` |
| Push/WhatsApp فعلي | القنوات مقبولة؛ السجل يصرّح `push_whatsapp_provider_not_configured` — لا تعرض «مُسلَّم» لهما |

### صدقية العمق (حيّ — اعرف الحدود)

| الموضوع | الحقيقة |
|---|---|
| لوحة / تقارير / هوامش | من `DailySnapshot`؛ شغّل اللقطة أو انتظر الجدول |
| `mode: auto` / `bulk_zone` | حيّ — أول مندوب on-duty يغطي المنطقة |
| أولوية التسعير | stop-at-first-match: تاجر ← مجموعة ← منطقة ← شرائح ← أساسي |
| FEFO | lots عند الاستلام؛ الصرف يفضّل أقرب صلاحية |
| CTR البنرات | impressions من `/app/content/home-blocks`؛ clicks من `/app/content/banners/{id}/click` |
| قوالب الإشعار | أحداث النظام تقرأ EP-SC-091؛ `in_app` فقط مضمون التسليم |
| قرار مرتجع | approve يصدر مذكرة دائنة (BR-13)؛ المخزون عند فرز المستودع |
| مسار الالتقاط | ترتيب aisle ثم shelf |

حيّ بالكامل للكتالوج: عروض show/update/activate · jobs · retailers 360 · users IAM · zones coverage · warehouses · invoices/{id} · content CRUD · loyalty stop.

---

## 9. الشاشة ↔ المسار ↔ الإكمال

| # | الشاشة | يستدعي | الحالة الفارغة |
|---|---|---|---|
| 1 | دخول | OTP | — |
| 2 | لوحة | dashboard + عدّاد pending | أصفار حتى اللقطة؛ بعدها KPI/تنبيهات حقيقية |
| 3 | طلبات + تفاصيل | sub-orders · assign modes | «لا طلبات تطابق المرشّح.» |
| 4 | علامات / فئات / منتجات | catalog | «لا منتجات بعد.» |
| 5 | تسعير + سجل | price-lists, change-log | «لا قوائم أسعار.» |
| 6 | عروض + أداء | offers CRUD + performance | «لا عروض.» |
| 7 | مخزون + تحويلات | inventory + warehouses | «لا أرصدة.» |
| 8 | مندوبون + حيّ + محفظة | reps* + `/live` | «لا مندوبين.» خريطة نقطة واحدة |
| 9 | مرتجعات | return-requests (+ SLA) | «لا طلبات إرجاع.» لوّن overdue |
| 10 | فواتير / دفعات / أعمار | finance | «لا فواتير مفتوحة.» |
| 11 | تجّار 360 + مجموعات + ائتمان | retailers/{id} · groups · credit | «لا تجّار في التغطية.» |
| 12 | تغطية مناطق + فجوات | zones + coverage | «لا تغطية.» اعرض without_reps |
| 13 | إشعارات + قوالب | notifications* | «لا سجل بعد.» |
| 14 | انترو / بنرات / سلايدر | content | انترو `enabled: false` |
| 15 | ولاء | loyalty rules/rewards/stop | قواعد فارغة صحيحة |
| 16 | تقارير + تصدير + jobs | reports + jobs/{id} | صفوف فارغة؛ job_id ثم polling |
| 17 | مستخدمو القناة | users + invite | «لا مستخدمين.» |
| 18 | إعدادات | GET/PUT /channel (+ quiet_hours) | — |

لكل شاشة: `can()` على الأزرار · مفتاح تكرار في النموذج · هيكل تحميل · خطأ عبر `ApiError.userMessage` · مال عبر `moneySyp` · 422 تحت الحقل · لا مسار من §8 · ترقيم على 📄 · سحب للتحديث · RTL مع LTR للأرقام.

---

## 10. قائمة المراجعة (عند إعادة الملفات)

1. المسار والطريقة يطابقان `channel.json` حرفياً. لا `/app/*` ولا `/platform/*`.
2. الترويسات: `X-Client: channel-web` · `X-Device-Id` ثابت · مفتاح التكرار على كل كتابة إلا OTP · لا `X-Channel-Id`.
3. الحقول تطابق FormRequest (الأنواع: `code` سلسلة، المال int، رسوم المناطق عشرية).
4. التحليل عبر الغلاف فقط. لا مفتاح لا يرسله الخادم. قائمة المنتجات 4 مفاتيح. `ctr` البنر int. اللوحة أصفار حتى اللقطة؛ بعدها KPI/charts/alerts حقيقية. تصدير → polling `GET /channel/jobs/{id}`.
5. الأخطاء: `ApiError` فقط · §7 · 401 على OTP لا يخرج · `wrong_guard` يخرج.
6. التكرار: يُعاد استخدامه في إعادة المحاولة، يُستبدل بعد نجاح أو تعديل النموذج.
7. المال int؛ لا ÷100؛ لا اختراع GMV.
8. الممنوعات غائبة (§8). لا قائمة تجّار وهمية ولا خريطة مندوبين مجمّعة.9. `can(permission)` من حمولة الدخول، لا اسم الدور.
10. `per_page ≤ 100` · لا `sort`.
11. التوكن في `sessionStorage` مفتاح `channel` فقط. يُمسح عند 401/خروج.
12. الاستيراد `FormData` لا JSON. حذف المنطقة يتعامل مع 204.

إعادة توليد العقد الآلي بعد أي مسار جديد:

```bash
php docs/api/generate-channel-live.php
```
