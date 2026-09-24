# خطة إنهاء الباكند — Backend finish plan

| | |
|---|---|
| الغرض | ترتيب العمل المتبقي حتى يصير الباك **جاهزاً للإطلاق** ثم **منتهياً للنهاية** (عمق DOC + ديون + صلاحيات + v1.1) |
| الحالة الحيّة | [`../status/00-overview.md`](../status/00-overview.md) — أعد التوليد بعد دمج العقود الجديدة (`php docs/api/generate.php && php docs/status/generate.php`) |
| الديون المعلنة | [`../debt-ledger.md`](../debt-ledger.md) |
| v1.1 قناة/مستودع | [`channel-warehouse-v1.1.md`](channel-warehouse-v1.1.md) |
| DOC المرجع | DOC-07 (السنترال) · DOC-08 (170 صلاحية) · DOC-01 عمق قناة/مستودع عبر v1.1 |
| القاعدة | **لا EP → لا بناء.** عقد في `api/catalog/` أولاً، ثم تنفيذ، ثم `generate` + قلب التذكرة ✅ |

> **ما انتهى أصلاً:** تذاكر PA-01…18 و AP-01…06 و SC-CAT-1…3 ومسارات الكتالوج كلها حيّة.
> هذه الخطة **لا تعيد** بناء ما في `status/`؛ تعالج ما بقي بعده.

---

## كيف تُقرأ الخطة

ثلاث مراحل متسلسلة. لا تبدأ المرحلة التالية قبل قبول المرحلة الحالية إلا ما وُسم صراحةً «موازي».

| مرحلة | الهدف | يوقف إطلاق مستخدم؟ |
|---|---|---|
| **0** | تصفية الشجرة ونشر آمن | نعم إن بقي عمل مجهول أو سيرفر متأخر |
| **1 — إطلاق** | دومين + OTP + طوابير + حذف قناة بـ OTP حقيقي | **نعم** |
| **2 — عقد وصلابة** | ديون DOC-07/08 + جلسة مندوب + بوابات صلاحيات | لا لحجب الإطلاق؛ نعم لـ«باك منتهٍ» |
| **3 — عمق v1.1** | صفوف [`channel-warehouse-v1.1.md`](channel-warehouse-v1.1.md) | لا — بعد الإطلاق |
| **4 — جودة طويلة** | Larastan + بوابة OpenAPI | لا — مستمر |

كل تذكرة: وحدة · يعتمد على · قبول · يُغلق أي صف دين إن وُجد.

---

## لوحة الحالة السريعة

| # | التذكرة | مرحلة | وحدة | يعتمد على | الحالة |
|---|---|---|---|---|---|
| BF-00 | تصفية الشجرة المتسخة (~168 ملف) + تثبيت توثيق المندوب | 0 | — | — | ✅ 2026-09-24 |
| BF-01 | نشر `main` على السيرفر + مطابقة الهجرات | 1 | Deploy | BF-00 | ⬜ |
| BF-02 | إغلاق OTP على الدومين (`OTP_BYPASS=false`) | 1 | Ops | BF-01 | ⬜ |
| BF-03 | Horizon + طوابير + جدولة اللقطة اليومية على السيرفر | 1 | Ops | BF-01 | ⬜ |
| BF-04 | VirtualHosts لوحات Sentrax (platform/channel/warehouse) | 1 | Ops | BF-01 | ⬜ |
| BF-05 | حذف قناة: OTP منصة حقيقي (إكمال PA-18) | 1 | Tenancy + Identity | — | ✅ 2026-09-24 |
| BF-06 | جلسة المندوب: `status` + `channel` | 2 | Identity | — | ✅ 2026-09-24 |
| BF-07 | قرار `X-Channel-Id` على `/platform/*` | 2 | Tenancy / Core | — | ✅ 2026-09-24 |
| BF-08 | `ad.iam.role_update` في DOC-08 + بذر + gate | 2 | Access | — | ✅ 2026-09-24 |
| BF-09 | بوابة 17 مسار `/app/*` ذات صلاحية معروفة | 2 | Access + Ordering/… | — | ⬜ |
| BF-10 | جلسة عقد: تعيين permission لكل مسار بلا صلاحية في الكتالوج | 2 | Catalog + Access | — | ⬜ |
| BF-11 | بذر صلاحيات DOC-08 الناقصة **عند ظهور مسار** (~30) | 2 | Access | BF-10 جزئي | ⬜ مستمر |
| BF-12 | تكامل Push/WhatsApp إنتاجي (أو إعلان صريح «غير مضبوط») | 2 | Integration | BF-02 | ⬜ |
| BF-13…BF-21 | صفوف v1.1 المتبقية | 3 | انظر الجدول أدناه | كتالوج أولاً | ⬜ |
| BF-22 | بوابة `openapi:check` (السادسة) | 4 | Tooling | — | ⬜ |
| BF-23 | دفع Larastan baseline وحدة بوحدة | 4 | الكل | — | ⬜ مستمر |

---

## المرحلة 0 — تصفية قبل أي إنهاء

### BF-00 — الشجرة المتسخة وتوثيق المندوب ✅ 2026-09-24

- **المشكلة:** ~168 ملفاً معدّلاً غير ملتزم (جلسة قناة/عمليات) + فرق محلي في `flutter-rep.md` (62 مسار + حارس) غير مرفوع مع `bbcccf2`.
- **ما حصل:** جلسة القناة دخلت في `69b2ec5`؛ حارس المندوب + `flutter-rep.md` مع `bbcccf2`/`69b2ec5`؛ هذه الخطة + فهرس README في نفس دفعة BF-00.
- **قبول:**
  1. كل ملف إمّا ملتزم في تذكرة باسم واضح، أو معاد لـ `HEAD`، أو منقول لـ worktree منفصل.
  2. لا خلط بين جلسة التوثيق وجلسة القناة في نفس الـ commit.
  3. `docs/DocsLast/flutter-rep.md` مطابق لـ `flutter-rep.json` (62 حيّ / 6 ممنوع + EP-APP-101) ومرفوع إن لزم.
- **ملاحظة عمل:** الجلسة القادمة في worktree مستقل (`git worktree add`) حتى لا تتكرر deadlocks يوم 19.

---

## المرحلة 1 — إطلاق مستخدم (حاجز)

### BF-01 — نشر الكود

- **وحدة:** Deploy (`docs/deploy/current-state.md`).
- **عمل:** رفع `main` إلى `/var/www/sentrax/backend` · `composer install --no-dev` · migrate · `config|route|event:cache` · التحقق من `GET /api/v1/health`.
- **قبول:** commit السيرفر = `origin/main`؛ هجرات بلا pending؛ health ok على الثلاثة (db/cache/queue).

### BF-02 — إغلاق OTP على الدومين

- **يغلق صف دين:** OTP على `api.sentraxsy.com` (2026-09-19 / بقايا ops).
- **عمل:** `OTP_BYPASS=false` · يفضّل `APP_ENV=production` · قناة OTP حقيقية (ليس `log` فقط إن وُجد مستخدمون).
- **قبول:** طلب OTP على الدومين لا يقبل `0000`/أي رمز؛ اختبار `OtpBypassTest` يبقى أخضر محلياً.

### BF-03 — الطوابير واللقطة

- **عمل:** Supervisor/Horizon يشغّل `critical` · `default` · `media` · `reports`؛ cron/schedule لـ `reports:daily-snapshots` @ 00:05 `Asia/Damascus`.
- **قبول:** مهمة تجريبية على `reports` تكتمل؛ بعد منتصف الليل الدمشقي تظهر صفوف `DailySnapshot` أو يُشغَّل الأمر يدوياً مرة ويُثبت.

### BF-04 — أسماء لوحات Sentrax

- **عمل:** VirtualHost + شهادة لكل من `platform.` / `channel.` / `warehouse.` (اليوم تُخدم TickMart بالخطأ).
- **قبول:** كل اسم يعرض منتج Sentrax الصحيح بلا خطأ شهادة؛ يُحدَّث `deploy/current-state.md`.

### BF-05 — حذف قناة: OTP منصة حقيقي ✅ 2026-09-24

- **وحدة:** Tenancy (`RequestChannelDeletion`) + Identity (`VerifiesPlatformStepUpOtp`).
- **ما حصل:** عقد Core + تنفيذ Identity (bypass محلي · TOTP إن وُجد 2FA · وإلا OTP هاتف) · `POST /platform/auth/request-otp` (EP-AD-005A) · إزالة `platform_delete_code` · اختبار TOTP بلا bypass.
- **قبول:** على `local` مع bypass يعمل كما اليوم؛ على بيئة بلا bypass يرفض رمزاً خاطئاً ويقبل بعد OTP حقيقي؛ اختبار ميزة واحد على الأقل؛ `deletion_request_id` كما العقد.

**بعد BF-01…05:** الباك **جاهز لإطلاق مستخدم** من جهة الخادم (الواجهات منفصلة). BF-05 شيفرة ✅؛ BF-01…04 تشغيل على السيرفر.

---

## المرحلة 2 — عقد وصلابة (باك «منتهٍ» دون v1.1)

### BF-06 — جلسة المندوب تحمل السبب ✅ 2026-09-24

- **يغلق صف دين:** session صامتة (2026-09-19).
- **وحدة:** Identity — `AppSession` / `GET /app/session`.
- **ما حصل:** مندوب يحصل على `status` (`pending_review|active|rejected|disabled`) و`channel` `{id, name}` · تحديث كتالوج EP-CM-004 · DocsLast · اختبار في `RepProfileStatusTest` / `RepSessionLimitsTest`.
- **قبول:** مندوب `pending`/`disabled` يقرأ السبب من الجلسة قبل/مع 403؛ اختبار يثبت الحقول؛ لا كسر لعملاء يرجعون حقولاً قديمة (إضافة فقط).

### BF-07 — `X-Channel-Id` على المنصة ✅ 2026-09-24

- **قرار أ:** إغلاق الفرع نهائياً. الرأس مُتجاهَل؛ اختبار `PlatformTenantHeaderTest`؛
  `FeatureFlagOverride` و`ChannelEvent` → relaxed؛ صف الدين محذوف.

### BF-08 — `ad.iam.role_update` ✅ 2026-09-24

- DOC-08 + `PermissionCatalog` + gate EP-AD-015 + اختبار 403 بالمفتاح الصحيح.

### BF-09 — بوابة مسارات التطبيق ذات الصلاحية المعروفة

- **المرجع:** permission-gate-audit §1 (17 مساراً: `rp.delivery.*` · `rp.warehouse.receive` · `rt.receive.*`).
- **عمل:** middleware `permission:…` + اختبار 403 بالمفتاح الصحيح؛ لا يعتمد على فشل لاحق للملف الشخصي وحده.
- **قبول:** تاجر على مسار مندوب → 403 `insufficient_permission` مع `permission`؛ العكس كذلك حيث ينطبق.

### BF-10 — جلسة عقد للمسارات بلا permission في الكتالوج

- **المرجع:** permission-gate-audit §2.
- **عمل:** لكل مسار حيّ بلا `permission` في `ep()`: إمّا تعيين كود DOC-08 موجود، أو قرار مكتوب «عمداً بلا صلاحية (حارس فقط)» في الكتالوج.
- **قبول:** تقرير قصير في PR؛ صفر مسار «نسيانه»؛ لا أسماء مخترعة في الكود.

### BF-11 — بذر المتبقي من DOC-08 (~30)

- **الوضع:** ~140 مبذورة / 170 في DOC-08. الناقص يُبذر **عند الحاجة لمسار** فقط (`CLAUDE.md`).
- **أمثلة ناقصة اليوم:** `ad.channels.archive` · `ad.system.integrations` · عائلات `sc.retailers.*` / `sc.reps.*` / `sc.iam.*` / `wh.picking.batch` · …  
- **قبول لكل دفعة:** مسار (أو تذكرة BF-13+) يذكر الكود · بذر · gate · اختبار.

### BF-12 — إشعارات خارج in_app

- **عمل:** ضبط مقدّم Push و/أو WhatsApp في Integration على السيرفر، أو الإبقاء على الرسالة الحالية `push_whatsapp_provider_not_configured` مع جملة صريحة في DocsLast أن التسليم المضمون = `in_app` فقط حتى يُضبط.
- **قبول:** لا يدّعي السجل «مُسلَّم» لقناة غير مضبوطة؛ اختبار أو دليل تشغيل في `deploy/current-state.md`.

---

## المرحلة 3 — عمق v1.1 (بعد الإطلاق)

كل تذكرة = صف في [`channel-warehouse-v1.1.md`](channel-warehouse-v1.1.md).  
**ترتيب التنفيذ المقترح للمنتج:**

| # | تذكرة | EP | سطح | قدرة |
|---|---|---|---|---|
| BF-13 | BF-WH-040 | EP-WH-040 | warehouse | قائمة/تعديل stock lots |
| BF-14 | BF-WH-041 | EP-WH-041A–C | warehouse | ✅ offline pick/pack + تعارضات (2026-09-24) |
| BF-15 | BF-WH-042 | EP-WH-042 / 042A | warehouse | إكمال batch/waves إن بقي نقص بعد 2026-09-24 |
| BF-16 | BF-SC-160 | EP-SC-160 | channel | خريطة كل المندوبين on-duty |
| BF-17 | BF-SC-161 | EP-SC-161 | channel | تقويم توصيل أسبوعي |
| BF-18 | BF-SC-162 | EP-SC-162 | channel | تصعيد SLA مرتجعات |
| BF-19 | BF-SC-163 | EP-SC-163 | channel | مضلعات مناطق + نوافذ وقت |
| BF-20 | BF-WH-050 | EP-WH-050 | warehouse | ✅ تقارير إنتاجية/دقة (2026-09-24) |
| BF-21 | BF-SC-165 | EP-SC-165 | channel | طابور موافقة ائتمان |

**قالب تذكرة v1.1 (انسخه لكل صف):**

1. Commit مستقل: `ep(...)` في `05-channel-ops.php` أو `06-warehouse.php` بـ `b`/`r`/`e`.
2. تنفيذ المسار + اختبارات القبول.
3. `php docs/api/generate.php && php docs/status/generate.php`.
4. تحديث DocsLast المعني + قلب الصف ✅ في `channel-warehouse-v1.1.md` وفي الجدول أعلاه.
5. إن لزم صلاحية جديدة → BF-11 في نفس السلسلة.

ما نزل 2026-09-24 (OTP بالكود، FEFO جزئي، CTR، SLA عرض، live لمندوب واحد، retailer 360 أغنى، waves جزئي، …) **لا يُعاد** هنا.

---

## المرحلة 4 — جودة مستمرة (موازي بعد المرحلة 1)

### BF-22 — البوابة السادسة OpenAPI

- **يغلق صف دين:** لا `openapi:generate --check` (2026-09-19).
- **عمل:** أمر يفشل إن اختلف `route:list` عن `b2b-api.openapi.json` (المسار/الطريقة/الحارس على الأقل؛ الوضع يفعلها نصفها).
- **قبول:** الأمر في CI؛ أخضر على `main`.

### BF-23 — Larastan pay-down

- **يغلق صف دين:** baseline (2026-09-16).
- **عمل:** وحدة واحدة لكل commit حسب جدول الأعراض في `debt-ledger.md` (ابدأ `ordering` ثم `delivery` ثم `fulfillment`…).
- **قبول:** كل هبوط يخفّض الرقم في `PhpstanBaselineTest` بنفس الـ commit؛ لا إيجاد جديد في شيفرة جديدة يُضاف للـ baseline.

**خارج الخطة عمداً:** إعادة تسمية طوابع الترحيل الستة عشر المشتركة — تبقى مثبتة ما لم يُثبت rename على كل بيئة.

---

## ترتيب التنفيذ المقترح (أسبوعي)

| أسبوع | ركّز على | مخرج |
|---|---|---|
| 0 | BF-00 | شجرة نظيفة / worktrees |
| 1 | BF-01…05 | **إطلاق خادم** |
| 2 | BF-06 · BF-08 · BF-09 | عقد مندوب + IAM أدق |
| 3 | BF-07 · BF-10 · BF-12 | عزل منصة + صلاحيات عقد + إشعار |
| 4+ | BF-13…21 حسب أولوية المنتج | v1.1 |
| مستمر | BF-11 · BF-22 · BF-23 | بذر عند الحاجة + بوابات + phpstan |

---

## تعريف «انتهى»

| المستوى | متى تقول انتهى |
|---|---|
| **إطلاق باك** | BF-00…05 ✅ و `status` = 410/410 (أو ما يعادله بعد أي EP جديد) |
| **باك منتهٍ دون v1.1** | + BF-06…12 ✅ وصفوف الدين المقابلة محذوفة من `debt-ledger.md` |
| **باك منتهٍ للنهاية حسب DOC** | + BF-13…21 ✅ و BF-22 ✅ و baseline في هبوط مستمر (BF-23 قد يبقى جارياً) |

---

## ما ليس في هذه الخطة

- بناء أو إكمال عملاء Flutter / Next — ريبو آخر؛ العقود في `DocsLast/`.
- DOC-WA0083 (بحث حقوق) — خارج المنتج.
- أي ميزة DOC بلا `ep()` — تُرفض حتى تدخل الكتالوج.

---

## أوامر الإغلاق لكل تذكرة شيفرة

```bash
composer deptrac
./vendor/bin/pest --group=arch
./vendor/bin/phpstan analyse
./vendor/bin/pest
./vendor/bin/pint --test
php docs/api/generate.php && php docs/status/generate.php
```

ثم اقلب صف التذكرة إلى ✅ مع التاريخ في هذا الملف.
