# تطبيق المندوب — عقد API (Flutter)

| | |
|---|---|
| الغرض | عقد المسارات الحية لتطبيق المندوب. مواصفة الشاشات العميقة: [`flutter-rep.md`](flutter-rep.md) · حقل: [`flutter-rep-field.md`](flutter-rep-field.md). |
| الحالة | **40/40** مندوب ✅ + مشترك/عام — `status/05` · `06` · `07` |
| الحارس | `auth:app` + `app.kind:rep` · `X-Client: rep-android` \| `rep-ios` |
| Postman | [`rep.postman_collection.json`](rep.postman_collection.json) · env `rep-android` |
| دورة الطلب | [`order-flow.md`](order-flow.md) §2.1 · §4 إسناد · §6–8 تسليم |
| عند التعارض | الكود ← status ← كتالوج `08-rep.php` ← هذا الملف ← flutter-rep للـ UI |

> **لا** `X-Channel-Id`. منتجات المندوب من **قناته** فقط بعد اعتماد الملف.

---

## المحتويات

| § | |
|---|---|
| 0 | قواعد · ترويسات |
| 1 | دخول · جلسة · حالة الملف |
| 2 | مسارات المندوب (40) — ملخص |
| 3 | **دورة الطلب — دور المندوب** |
| 4 | صلاحيات `rp.*` |
| 5 | ممنوع · قبول |

---

## 0. قواعد · ترويسات

نفس عقد الموبايل: غلاف JSON · مال `int` · `X-Idempotency-Key` على الكتابات · `X-Device-Id` ثابت · `error.code` للعقد.

جلسة المندوب تعيد `status` (`pending_review|active|rejected|disabled`) و`channel {id,name}` (BF-06) — اعرض السبب قبل الشاشات التشغيلية إن لم يكن `active`.

---

## 1. دخول · جلسة · حالة الملف

[`shared.md`](shared.md): OTP عام → `GET /app/session` → `POST /app/rep/register` إن لزم → `GET /app/rep/home`.

OTP مؤجّل في مواصفة المنتج كما في `flutter-rep.md` — لا تعِد بناء تدفق OTP خارج العقد الحي.

---

## 2. مسارات المندوب (40) — ملخص

المصدر: `docs/status/05-rep-app.md`.

| مجموعة | مسارات مفتاحية |
|---|---|
| ملف / خدمة | register · `PATCH /app/rep/status` · home |
| كتالوج / عملاء / مناطق | products · customers · zones · shops |
| سلة محل | cart lines · **`POST …/cart/sections/{retailer_id}/submit`** → sub_order `pending` + `rep_id` = أنت |
| طلباتي | `GET /app/rep/orders` |
| إسناد | assignments list · accept · reject · scheduled-orders |
| عهدة مستودع | `warehouse-receipts` · **confirm بـ رمز 4 خانات** |
| تسليم | deliveries · complete / fail · تعديل بنود |
| مرتجعات | return-requests |
| نقد | `POST /app/receipts/reserve` ثم payments · wallet · withdrawals · receivables |

الأجسام والـ UI الحرفي: `flutter-rep.md` §7–11.

---

## 3. دورة الطلب — دور المندوب (2026-11-03)

### 3.1 تسجيل طلب

`POST /app/rep/cart/sections/{retailer_id}/submit` (EP-RP-023)  
→ `pending` · المندوب يُحفظ على الطلب. الشاشة: «بانتظار الموافقة».

بعد موافقة القناة/المستودع: **إسناد تلقائي لنفس المندوب** حتى لو off-duty — لا تنتظر اختياراً من اللوحة لطلبك أنت.

### 3.2 قبول إسناد (طلبات محل أسندت لك)

`GET /app/rep/assignments` → `POST …/accept` | `…/reject`  
صلاحية: `rp.delivery.accept`.

### 3.3 استلام حمولة من المستودع

1. المستودع: `POST /warehouse/handovers` → `temp_code` من 4 خانات.  
2. أنت: `GET /app/rep/warehouse-receipts` → `POST …/{handoverId}/confirm` بالرمز.  
3. بعد الطرفين: الطلبات → `on_the_way`.

صفوف نفس `handover_id` تُؤكَّد معاً.

### 3.4 التسليم للمحل

| نتيجة المحل | فعل المندوب |
|---|---|
| كامل | `complete` بـ accept |
| أقل كمية | عدّل lines ثم complete — الفاتورة تتعدّل |
| رفض | `fail` + reason |

بعد نجاح قد يأتي `ask_payment: true` → افتح التحصيل بـ `receipts/reserve` ثم `payments` (لا تحتفظ برقم يدوي).

### 3.5 ما لا يفعله المندوب

- ضبط `order_approver` أو تأكيد طلبات القناة/المستودع.
- إدخال نقص كلي للصنف (مستودع EP-WH-063).
- قرار `accept_partial`/`cancel` على النقص (تاجر).

---

## 4. صلاحيات `rp.*` الشائعة

| رمز | أين |
|---|---|
| `rp.delivery.accept` | إسنادات |
| `rp.delivery.deliver` / postpone / return_request | مسار التسليم حسب الكتالوج |
| `rp.warehouse.receive` | تأكيد العهدة |
| `rp.payment.collect` | reserve + payments |
| `rp.wallet.view` / `rp.payment.withdraw` | محفظة وسحب |

أغلق الأزرار بـ `can()` من الجلسة/المنح — ليس باسم الدور فقط.

---

## 5. ممنوع · قبول

| ممنوع | البديل |
|---|---|
| `/app/retailer/*` | تطبيق التاجر |
| اختلاق مسار تسليم | فقط ما في `status/05` |
| محاكاة GMV قناة | لوحة القناة |

قبول:

1. 40/40 في `status/05`.  
2. submit محل يحفظ `rep_id`؛ الإسناد الذاتي بعد الموافقة مفهوم في UI.  
3. تأكيد العهدة برمز 4 خانات لا بزر أعمى.  
4. Postman `rep` + env `rep-android`.

*آخر تحديث: 2026-11-03 · مربوط بـ order-flow.*
