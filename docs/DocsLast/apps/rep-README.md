# تطبيق المندوب — حزمة فرونت Flutter

**هذا الملف هو المدخل.** ابنِ تطبيق Flutter على الحارس `app` والنوع `rep`. لا تغيّر الـ API. لا تخترع مسارًا ولا جسم طلب ولا شكل رد.

التاريخ: **2026-09-16**  
المسارات الحيّة: **39** = 29 تحت `/api/v1/app/rep` + 6 مشتركة (`session`, `logout`, `quote`, `offers`×2, `receipts/reserve`) + 3 OTP + `/health`

---

## ماذا تُرسل لفريق فلاتر / لـ Claude

أربعة ملفات في مجلد واحد. لا تُرسل كتالوج الـ 338 ولا Postman الكامل ذي الـ 178 طلبًا.

| # | الملف | ماذا يفعل |
|---|---|---|
| 0 | **`rep-README.md`** (هذا) | القواعد، المكدّس، الترتيب، برومبت Claude |
| 1 | **`rep-api.live.json`** | العقد الحي: method + path + body/response + ملاحظات الكنترولر |
| 2 | **`rep-api.postman.json`** | مجموعة Postman **للمندوب فقط** — كل طلب حي |
| 3 | **`rep-app-spec.md`** | 18 شاشة، حقول، تصميم RTL، حالات فارغة، فجوات §7 |
| 4 | **`rep-STATUS.template.md`** | انسخه في فرونت باسم `REP-STATUS.md` حتى لا يُبنى من الصفر |

مرجع إضافي (إعداد الخادم وDart): [rep.md](./rep.md) — مؤرَّخ 7 أيلول في أغلبه. **المحفظة والتحصيل حيّان منذ 16 أيلول.** عند التعارض: JSON ← المواصفة ← هذا الملف ← `rep.md`.

اختياري: بيئة Postman [`docs/api/environments/rep-android.postman_environment.json`](../../api/environments/rep-android.postman_environment.json).

### تعارض المصادر

```
rep-api.live.json              ← ما هو حي اليوم (live=true)
  → rep-app-spec.md            ← الشاشات والحقول والتصميم
  → rep.md §2 §5 §8            ← Dart والغلاف (صحّح §7: المال لم يعد 404)
  → لا الكتالوج العام ولا الباك‌لوغ
```

`catalog` داخل JSON **مثال**. إن خالفه `note` أو المواصفة أو الكنترولر، الكود يفوز.

---

## المشروع

| | |
|---|---|
| المسار | `apps/rep` |
| المنصة | **Flutter — Android + iOS** |
| الحارس | `app` (Sanctum Bearer) |
| بوابة النوع | `app.kind:rep` على كل `/app/rep/*` |
| `X-Client` | `rep-android` / `rep-ios` |
| البذرة | **لا حساب جاهز** — OTP لأي رقم سوري. على staging الرمز **`000000`** |
| Staging | `https://api.sentraxsy.com/api/v1` |
| محلي | `http://127.0.0.1:8000/api/v1` |

```dotenv
API_BASE_URL=https://api.sentraxsy.com/api/v1
X_CLIENT=rep-android
```

**لا ترسل `X-Channel-Id`.** مسار `/app/*` لا يضبط مستأجرًا. القناة من ملف المندوب على الخادم.

### توكن في دقيقتين (staging: الرمز `000000`)

```bash
curl -s -X POST https://api.sentraxsy.com/api/v1/public/auth/request-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'X-Client: rep-android' -H 'X-Device-Id: 11111111-1111-1111-1111-111111111111' \
  -d '{"phone":"+963933000000","purpose":"login","client":"rep-android"}'
# { "data": { "otp_id": "otp_…", "expires_in": 300, "resend_after": 60 } }
# لا مفتاح تكرار على طلب الرمز.

curl -s -X POST https://api.sentraxsy.com/api/v1/public/auth/verify-otp \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H 'X-Client: rep-android' -H 'X-Device-Id: 11111111-1111-1111-1111-111111111111' \
  -d '{"otp_id":"otp_…","code":"000000","device_id":"11111111-1111-1111-1111-111111111111","platform":"android"}'
# data.token + data.user_type + data.profile_completed
```

- مستخدم جديد: `is_new_user=true` والتوكن بقدرة `registration` فقط → `POST /app/rep/register` ثم **استبدل التوكن** بـ `data.token` العائد من التسجيل.
- مندوب مكتمل: `user_type=rep` و`profile_completed=true` → `GET /app/session`.
- تاجر على نفس الهاتف: `user_type=retailer` → أظهر «هذا الرقم لتطبيق التاجر» واخرج. لا مبدّل نوع.

ثم `Authorization: Bearer {token}` على كل شيء آخر. OTP محليًا في `storage/logs/laravel.log` سطر `[OTP]`.

`otp_invalid` / `otp_expired` = **401 بلا توكن** — ابقَ على شاشة الرمز.

---

## المكدّس — مقفول

- Flutter stable، Material 3، `locale: ar`, `textDirection: rtl`
- Dio أو `http` **في طبقة عميل واحدة** (`packages/b2b_api` أو ما يوازي `@b2b/api-client`) — لا استدعاءات مبعثرة من الشاشات
- التوكن: تخزين آمن (`flutter_secure_storage`) بمفتاح الحارس `app`
- `X-Device-Id`: UUID ثابت لكل تثبيت، يُولَّد مرة
- أرقام غربية 0–9 و`tabular-nums` في المال
- حقول الهاتف والرمز: `textDirection: ltr` و`keyboardType: number`

اللغة الظاهرة عربية RTL أصيل. لا تعكس الواجهة آليًا من LTR.

---

## كيف تقرأ `rep-api.live.json`

استدعِ فقط عنصرًا `"live": true`. لا تستدعِ مصفوفة `forbidden`.

| الحقل | المعنى |
|---|---|
| `method` + `path` | النداء حرفيًا. المسار يبدأ بـ `/api/v1/...` |
| `permission` | اسم من الكتالوج **للمرجع**. مسارات `/app/rep` **لا تفحص** `rp.*`. لا تخفِ شاشة خلف `can(rp.wallet.view)` |
| `write` + `idempotency` | إن `idempotency: true` أرسل `X-Idempotency-Key` (UUID **واحد لكل نيّة مستخدم**) |
| `body` / `response` | مثال. `note` يصف ما يفعله الكنترولر فعلًا |
| `query` | المسموح اليوم — لا `sort`، لا `filter[offer_only]` |
| `headers` / `envelope` / `gotchas` | في رأس الملف |

`request-otp` و`verify-otp` و`resend-otp` **ليست** idempotent.

`API_BASE_URL` إن انتهت بـ `/api/v1` لا تُكرّر البادئة في المسار.

---

## قواعد لا تُكسر

1. **لا تخترع API.** `forbidden` أو ⛔ في المواصفة §7 = حالة فارغة. بلا رصيد مختلق وبلا صندوق إشعارات وهمي.
2. **المال عدد صحيح** بأصغر وحدة. الليرة `decimals = 0` — اعرض العدد، لا تقسم على 100.
3. **الصلاحيات `rp.*` في الجلسة للعرض فقط.** البوابة الحقيقية: التوكن + `user_type === rep` + `profile_completed`.
4. **مفتاح تكرار = نيّة مستخدم** لا محاولة HTTP. Postman `{{$guid}}` يُجدَّد في كل إرسال — هذا صحيح هناك وخطأ في التطبيق.
5. **لا ترسل `sort`.** Spatie يرمي أو يلغي الترتيب الافتراضي.
6. **404 `not_found` = غير موجود أو ليس لك.** لا تعرض «ممنوع».
7. **`Accept-Language` لا يضمن ترجمة الرسالة.** ترجم `error.code` من قاموس المواصفة §3.
8. **أونلاين أولًا.** لا outbox للمزامنة. الاستثناء الوحيد: `client_op_id` على `POST /customers` و`POST /payments`.
9. **`retailer_id` = معرّف ملف التاجر** من `GET /customers` و`GET /zones/{id}/shops`. معرّف `POST /customers` **ليس** `retailer_id`.
10. **سلة المندوب بلا حذف/تعديل بند.** `POST /cart/lines` يزيد الكمية. لا `PATCH` ولا `DELETE`.

---

## ممنوع بناؤه

| لا يوجد | بديل الواجهة |
|---|---|
| `/app/sync/*` | أونلاين. لا شريط مزامنة يعدّ عمليات معلّقة على الخادم |
| `/app/notifications*` و`push-token` | لا جرس ولا FCM |
| `/app/content/home-blocks` | الرئيسية تُركَّب من الإسنادات + التسليم + المحفظة |
| `/app/loyalty*` | لا نقاط |
| `GET /public/refs` و`/public/app-config` | لا دليل قنوات/مناطق قبل الدخول؛ لا فرض تحديث من الخادم |
| `GET /app/rep/zones` | احفظ `zone_ids` من التسجيل؛ أو استنتجها من `GET /customers` |
| `PATCH/DELETE /app/rep/cart/lines/{id}` | لا إنقاص كمية من الخادم |
| `GET /app/rep/return-requests` | إنشاء فقط؛ احفظ `request_no` محليًا |
| `GET /app/rep/discount-cap` | `session.commercial_limits.max_discount_percent` |

---

## الشاشات — بهذا الترتيب

المواصفة §5 ملزمة حقلًا بحقل.

| # | الشاشة | ملاحظة |
|---|---|---|
| 1 | وصول / صحة | `GET /health` |
| 2 | هاتف → OTP | مؤقّت `resend_after`؛ TTL `expires_in`؛ 5 محاولات |
| 3 | التسجيل | `POST /app/rep/register` ثم استبدال التوكن |
| 4 | إقلاع الجلسة | `GET /app/session` — اقرأ `commercial_limits` |
| 5 | الهيكل + مناوبة | `PATCH /status` يغلق النبضات إن `on_duty=false` |
| 6 | الرئيسية | تركيب حيّ — لا بلوكات محتوى |
| 7 | المناطق والمحلات | `search` علوي على المحلات؛ صفحات |
| 8 | الزبائن + تسجيل محل | `filter[search]`؛ `client_op_id` إلزامي |
| 9 | المنتجات | `zone` لتسعير الكمية 1؛ أعد `quote` قبل الوعد |
| 10 | العروض | قائمة قد تكون فارغة — هذا صحيح |
| 11 | السلة والإرسال | `sections/{retailer_id}/submit`؛ خصم ≤ السقف |
| 12 | الإسنادات | مصفوفة بلا صفحات |
| 13 | المجدولة | `?date=`؛ فيها `id` |
| 14 | استلام العهدة | `handover_id` في التأكيد؛ رمز 4 خانات |
| 15 | مسار التسليم | `GET /deliveries` له أثر كتابة — لا تستطلع كل ثانية |
| 16 | تفاصيل + إنهاء/تأجيل/تعذّر | `complete` يحجز `receipt_no` ويفتح التحصيل |
| 17 | مرتجع ميداني | إنشاء فقط |
| 18 | المحفظة والتحصيل | سقف الكاش 403 `cash_cap_exceeded`؛ `max_cash_hold=0` بلا سقف |

التصميم: كثافة ميدانية (إبهام، بطاقات، شريط سفلي)، رموز المواصفة §2 — `--primary` teal `#0F766E`، IBM Plex Sans Arabic.

---

## تعريف الانتهاء

- الشاشات الـ 18 من المواصفة موجودة، بما فيها الفراغ الصادق لـ ⛔
- كل كتابة (ما عدا OTP) ترسل `X-Idempotency-Key` ثابتًا لكل نيّة
- الدخول ينجح على staging بالرمز `000000`
- التحصيل يعمل من `ask_payment` بعد `complete`
- لا قوائم مختلقة، لا مال عشري، لا صندوق إشعارات، لا مزامنة وهمية
- RTL أصيل

حقل أو مسار غير موجود في الـ JSON ولا في المواصفة: **قف واسأل. لا تخمّن.**

---

## خطوتان حتى لا يُبنى المشروع من الصفر كل مرة

مستودع الـ API **هذا** لا يحتوي تطبيق فلاتر. افتح Claude **في مستودع الفرونت**.

1. انسخ الحزمة الخمسة إلى مجلد معروف هناك (مثل `docs/backend-pack/rep/`) **أو** أرفقها في الدردشة.
2. انسخ `rep-STATUS.template.md` إلى جذر الفرونت باسم `REP-STATUS.md`.
3. الجلسة الأولى: برومبت **الجرد**. النتيجة جدول حالة.
4. كل جلسة لاحقة: برومبت **التابع** + أرفق `REP-STATUS.md` المحدَّث.

### ماذا يفعل كل ملف — لا تخلط الأدوار

| الملف | اقرأه متى | افعله | لا تفعله |
|---|---|---|---|
| `rep-README.md` | أول شيء في كل جلسة بعد `REP-STATUS.md` | القواعد، المكدّس، ترتيب الشاشات، تعريف الانتهاء | لا تستخرج منه مسارات؛ المسار من JSON |
| `rep-api.live.json` | قبل أي `Dio`/`http` | ولّد دوال العميل من `endpoints[]` ذات `"live": true`. الترويسات والغلاف من رأس الملف. احترم `note` و`gotchas` | لا تستدعِ `forbidden`. لا تضف مسارًا لأن الكتالوج يذكره. `body`/`response` مثال لا عقد TypeScript صارم إن خالفه `note` |
| `rep-app-spec.md` | قبل رسم أي شاشة | §2 تصميم، §3 أخطاء، §4 مال، §5 حقول الشاشة حرفيًا، §6 رحلات، §7 فراغ صادق | لا تبنِ شاشة من ذاكرتك. لا تحاكِ ⛔. لا تخفِ تبويبًا خلف `rp.*` |
| `rep-api.postman.json` | للتحقق اليدوي ضد staging/محلي | استورد + بيئة `rep-android`. نفّذ المجلدات 00→09 قبل أن تعلن الشاشة `done` | لا تنسخ `{{$guid}}` إلى فلاتر — مفتاح واحد لكل نيّة مستخدم |
| `rep-STATUS.template.md` | مرة واحدة | انسخه `REP-STATUS.md` | لا تعدّل القالب في حزمة الباك |
| `REP-STATUS.md` | **أول ملف في كل جلسة** | حدّث الصف + سطر «التالي» في نهاية الجلسة | لا تبدأ شاشة `done`. لا تختار ملحمة جديدة إن «التالي» فارغ |
| `rep.md` | اختياري — Dart والغلاف | §2 عميل، §3 فخ التوكن، §8 مطبّات | §7 القديم عن المحفظة **ملغى**. المال حي. عند التعارض: JSON يفوز |

ترتيب المصادر عند التعارض:

```
REP-STATUS.md                 ← أين وصلنا
rep-api.live.json             ← ما يُستدعى اليوم
rep-app-spec.md               ← ماذا تُظهر الشاشة
rep-README.md                 ← القواعد
rep.md                        ← Dart فقط؛ المال فيه حي منذ 16 أيلول
لا docs/api/b2b-api.catalog.json
لا docs/backlog
```

---

### برومبت 1 — جرد ثم أكمل (أول مرة، أو بعد غياب)

الصقه في **مستودع فلاتر** بعد إرفاق الحزمة. أرفق أيضًا `REP-STATUS.md` إن وُجد.

```
You are continuing an existing Flutter field-rep app (guard `app`, kind `rep`).
Do NOT run `flutter create` if a project already exists.

════════════════════════════════════════
STEP 0 — ORIENT (mandatory, no code yet)
════════════════════════════════════════
1. Find the app: look for apps/rep, apps/field_rep, lib/main.dart, pubspec.yaml, a package like b2b_api / dio client.
2. If REP-STATUS.md exists, READ IT FIRST. If not, copy the attached rep-STATUS.template.md to the repo root as REP-STATUS.md.
3. Map each of the 18 screens in REP-STATUS.md to real Dart files (routes, pages, api calls).
4. Reply with a short table only:

   # | screen | done|partial|empty|missing | dart path | api wired?

   Then work ONLY the first missing/partial row (or the line in «التالي»). Do not start all 18.

Never scaffold a new Flutter app if one exists.
Never rewrite the HTTP client if it already sends the headers in rep-api.live.json → "headers".
Never rebuild a screen marked done unless I name a bug.

════════════════════════════════════════
FILES — what to do with each (attached)
════════════════════════════════════════
0. REP-STATUS.md
   Progress. Update at end of session: date, the row you touched, one-line «التالي».

1. rep-README.md
   Rules and locked stack. Read after STATUS. Do not invent routes from it.

2. rep-api.live.json  ← SOURCE OF TRUTH FOR HTTP
   - Call ONLY objects with "live": true.
   - NEVER call anything in "forbidden". Those 404. Show EmptyState, do not mock data.
   - Generate/update the API client from endpoints[]: method, path, query, body, headers.
   - Read top-level "headers", "envelope", "gotchas", and each endpoint "note".
   - "permission" is catalog names only. /app/rep does NOT enforce rp.* — do not hide screens behind can(rp.wallet.view).
   - write + idempotency true → send X-Idempotency-Key. One UUID per user intent, reused on retry. OTP paths are exempt.
   - If API_BASE_URL already ends with /api/v1, do not prefix paths again.

3. rep-app-spec.md  ← SOURCE OF TRUTH FOR UI
   - §0 symbols (✅ live, 🟡 caveat, ⛔ do not build)
   - §2 design tokens, bottom nav, RTL, border colors
   - §3 envelope, errors in Arabic via error.code (not raw error.message)
   - §4 money = int; SYP never /100; max_cash_hold 0 = no cap
   - §5 screens 1–18: field tables are binding (JSON keys, types, required, validation)
   - §6 journeys (first launch, shop order, delivery day, end of day cash)
   - §7 gaps = EmptyState only

4. rep-api.postman.json
   Import to verify a screen against staging before marking it done.
   Postman {{$guid}} regenerates every send — that is WRONG in Flutter.

5. rep.md (optional)
   Dart snippets and HTTP contract. IGNORE any leftover “wallet is 404” wording.
   Conflict order: live JSON → spec → README → rep.md.

Do not open docs/api/b2b-api.catalog.json. Do not implement backlog tickets.

════════════════════════════════════════
STACK (locked)
════════════════════════════════════════
Flutter stable, Material 3, locale ar, textDirection rtl.
One HTTP layer (Dio or http). Token in flutter_secure_storage, key for guard `app`.
X-Client: rep-android or rep-ios.
X-Device-Id: stable UUID per install.
Do not send X-Channel-Id.
Seed: no seeded account. Staging OTP is 000000 at https://api.sentraxsy.com/api/v1.
Local OTP: storage/logs/laravel.log line [OTP].

════════════════════════════════════════
HARD RULES
════════════════════════════════════════
- No invented paths, bodies, or response shapes.
- Money is integer minor units. SYP decimals = 0. Display the integer. Never / 100.
- Gate the app on token + user_type==rep + profile_completed. Not on rp.* permissions.
- After POST /app/rep/register, REPLACE the stored token with data.token (ability *).
- retailer_id = id from GET /customers and GET /zones/{id}/shops.
  POST /customers returns a sourced-shop id — NOT retailer_id. Reload the list.
- POST /app/rep/cart/lines INCREMENTS qty. There is no PATCH/DELETE for rep cart lines.
- Submit path is /app/rep/cart/sections/{retailer_id}/submit — retailer_id, not section id.
- GET /deliveries has write side effects. Do not poll it.
- Warehouse confirm uses handover_id, not sub_order_id. Wrong id → 409 not 404.
- complete → receipt_no + ask_payment:true → open collect. Do not mint a fake receipt.
- cash_cap_exceeded is HTTP 403. max_cash_hold 0 means no cap.
- client_op_id required on POST /customers and POST /payments. Generate once per intent.
- Never send sort. Customers search is filter[search]. Zone shops search is top-level search.
  Products: filter[search], top-level zone for price. Do not send filter[zone_id], offer_only, available_only.
- 404 not_found = missing OR not yours. Render as missing, never “forbidden”.
- Online-first. No sync outbox, no notification bell, no FCM, no loyalty, no home-blocks, no GET /public/refs.
- Persist zone_ids from register; there is no GET /app/rep/zones.
- Map error.code using spec §3. Accept-Language does not guarantee Arabic server messages.
- RTL authentic. Phone/OTP/ids LTR. Western digits 0-9. Tabular figures on money.

════════════════════════════════════════
THIS SESSION
════════════════════════════════════════
Finish ONE screen: the «التالي» line in REP-STATUS.md, or the first missing/partial row.
Do not start the next screen in the same session.

End of session (mandatory):
Update REP-STATUS.md — date, that screen’s status, new one-line «التالي».
If a field/path is in neither the JSON nor the spec: STOP and ask. Do not guess.
```

---

### برومبت 2 — جلسة تالية (انسخ كل مرة)

أرفق `REP-STATUS.md` المحدَّث + نفس الحزمة إن تغيّرت.

```
Continue the existing Flutter field-rep app. Do not flutter create. Do not replace the API client.

Read in this order:
1. REP-STATUS.md
2. rep-README.md (rules only)
3. The §5 section in rep-app-spec.md for the screen named in «التالي»
4. The matching endpoints in rep-api.live.json (live:true only, plus that endpoint’s "note")

Work ONLY the screen in REP-STATUS.md → «التالي».
Do not touch screens marked done. Do not implement forbidden[] / spec §7.

Wire real HTTP. Verify with rep-api.postman.json against staging (OTP 000000) or local.

When done: update REP-STATUS.md (that row + the new «التالي» line).
If «التالي» is empty, stop and list remaining missing/partial rows — do not pick a new epic.

Conflict: live JSON → spec → README. Money is live. No mocked wallet. No /100 on SYP.
retailer_id from GET lists, not from POST /customers. Cart lines only increment.
Idempotency key = one per user intent, not per HTTP attempt.
```

---

### برومبت 3 — لصقه فوق أي طلب تصميم/شاشة (اختياري)

إذا طلب المصمم «ابنِ شاشة المحفظة» دون الحزمة:

```
قبل الكود: اقرأ REP-STATUS.md ثم rep-app-spec.md §5 لهذه الشاشة ثم كائناتها في rep-api.live.json.
لا مسار إلا live:true. لا بيانات وهمية لـ forbidden.
التزم جداول الحقول (المفتاح JSON حرفيًا). المال int. حدّث REP-STATUS.md في النهاية.
```

---

## إعادة توليد العقد الحي

```bash
php artisan route:list --json > docs/api/.live-routes.json
php docs/api/generate-rep-live.php
```

على Windows تجنّب BOM في `.live-routes.json` — المولّد يتجاهله إن وُجد.
