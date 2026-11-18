# كل عقود العملاء — خريطة واحدة (2026-11-03)

| | |
|---|---|
| الغرض | فهرس تشغيلي لكل سطح يستهلك هذا الـ API. افتح ملف سطحك فقط للبناء؛ هنا لل Orient. |
| الحالة الحية | **436/436** — [`../status/00-overview.md`](../status/00-overview.md) |
| دورة الطلب | [`order-flow.md`](order-flow.md) · تذكرة [`../plan/order-flow-gaps.md`](../plan/order-flow-gaps.md) |
| قبل الرفع | [`../plan/pre-deploy-checklist.md`](../plan/pre-deploy-checklist.md) |
| عند التعارض | الكود ← `status/` ← الكتالوج ← ملف DocsLast للسطح |

---

## 1. الأسطح الخمسة + المشترك

| # | السطح | حارس | MD العقد | Postman | status | حيّ |
|---|---|---|---|---|---|---:|
| 1 | سوبر أدمن (منصة) | `platform` | [`platform.md`](platform.md) | [`platform.postman_collection.json`](platform.postman_collection.json) | [`01`](../status/01-platform-admin.md) | 174 |
| 2 | قناة توريد | `channel` | [`channel.md`](channel.md) | [`channel.postman_collection.json`](channel.postman_collection.json) | [`02`](../status/02-channel-dashboard.md) | 124 |
| 3 | مستودع | `warehouse` | [`warehouse.md`](warehouse.md) | [`warehouse.postman_collection.json`](warehouse.postman_collection.json) | [`03`](../status/03-warehouse.md) | 32 |
| 4 | تطبيق التاجر | `app` + `retailer` | [`flutter-retailer.md`](flutter-retailer.md) · ملخص [`retailer.md`](retailer.md) | [`retailer.postman_collection.json`](retailer.postman_collection.json) | [`04`](../status/04-retailer-app.md) | 37 |
| 5 | تطبيق المندوب | `app` + `rep` | [`rep.md`](rep.md) · عمق UI [`flutter-rep.md`](flutter-rep.md) | [`rep.postman_collection.json`](rep.postman_collection.json) | [`05`](../status/05-rep-app.md) | 40 |
| — | مشترك التطبيقين | `app` | داخل retailer/rep + [`shared.md`](shared.md) | ضمن مجموعات الموبايل | [`06`](../status/06-shared-app.md) | 18 |
| — | عام (بلا حارس) | — | [`shared.md`](shared.md) | ضمن الموبايل / القناة | [`07`](../status/07-public.md) | 6 |
| — | خارج البادئات / صحة | — | [`shared.md`](shared.md) | الكل | [`08`](../status/08-other.md) | 7 |

**المجموع الكتالوج = 436.** لا مسار ❌ اليوم.

---

## 2. ماذا تبني أين

| تريد… | اقرأ |
|---|---|
| لوحة Next/Vite منصة | `platform.md` (§9B ما يصل للتاجر والمندوب) + Postman platform + env `platform-web` |
| لوحة قناة | `channel.md` + §0B order-flow + §0C حقول التاجر/المندوب + Postman channel |
| لوحة مستودع أرضية | `warehouse.md` + §5 order-flow + Postman warehouse |
| Flutter تاجر | [`flutter-retailer.md`](flutter-retailer.md) |
| Flutter مندوب | `rep.md` ثم `flutter-rep.md` / `flutter-rep-field.md` |
| تسلسل طلب كامل بين الأطراف | `order-flow.md` أولاً |
| كل الطلبات في Postman واحد | `../api/b2b-api.live.postman_collection.json` |

---

## 3. قواعد مشتركة (كل الأسطح)

1. غلاف `{ data, meta }` / `{ error: { code, message, details? } }` — العقد هو `code`.
2. مال = `int` بأصغر وحدة. ل.س بدون ÷100. FX سلم ثابت `10^6`.
3. كتابات: `X-Idempotency-Key` إلا مسارات الدخول/OTP المعفاة.
4. معرّف أجنبي → **404** لا 403. توكن حارس خطأ → `wrong_guard`.
5. لا تختلق مساراً خارج الكتالوج. لا تحاكِ GMV.
6. تعديلات لاحقة على الكتالوج (مثل `wh.orders.*`, `order_approver`) **تبقى** — لا تُرجع لـ DOC قديم.

---

## 4. دورة الطلب — Reminder سريع

```text
تسجيل (مندوب أو محل) → pending
  → موافقة قناة أو مستودع (settings.order_approver)
  → إسناد (تلقائي لمندوب / يدوي لمحل)
  → تجهيز · نقص كلي FIFO · قرار محل accept_partial|cancel
  → عهدة رمز 4 خانات → on_the_way → تسليم → عودة يوم
```

التفاصيل والمسارات: [`order-flow.md`](order-flow.md).

---

## 5. إعادة التوليد

```bash
php docs/api/generate.php && php docs/status/generate.php
php docs/api/generate-platform-live.php
php docs/api/generate-channel-live.php
php docs/api/generate-rep-live.php
php docs/api/generate-dashboard-postman.php
php docs/api/generate-live-postman.php
```

*آخر تحديث: 2026-11-03.*
