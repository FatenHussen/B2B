# قائمة ما قبل الرفع — Pre-deploy checklist

| | |
|---|---|
| التاريخ | 2026-11-03 |
| الهدف | فصل **ما اكتمل في الشيفرة** عما يبقى **على السيرفر** قبل مستخدم حقيقي |
| عقود العملاء | [`../DocsLast/platform.md`](../DocsLast/platform.md) · [`channel.md`](../DocsLast/channel.md) · [`warehouse.md`](../DocsLast/warehouse.md) · [`order-flow.md`](../DocsLast/order-flow.md) |
| Postman | [`platform`](../DocsLast/platform.postman_collection.json) · [`channel`](../DocsLast/channel.postman_collection.json) · [`warehouse`](../DocsLast/warehouse.postman_collection.json) — مولَّد بـ `php docs/api/generate-dashboard-postman.php` |

---

## 1. شيفرة هذا الريبو — منتهية لهذا الإطلاق

| السطح | مسارات | ملاحظة |
|---|---|---|
| منصة (سوبر أدمن) | 174/174 ✅ | PA-01…18 ✅ |
| قناة توريد | 124/124 ✅ | v1.1 + order_approver ✅ |
| مستودع | 32/32 ✅ | EP-WH-060…063 ✅ |
| تاجر + مندوب + مشترك + عام | الباقي حتى **436/436** ✅ | |
| Order flow gaps | 7/7 ✅ | `OrderFlowGapsTest` |

لا تذكرة مسار ❌ مفتوحة في `docs/status/` اليوم.  
لا تُرجِع تعديلات الكتالوج/الصلاحيات اللاحقة إلى نص DOC-07/08 القديم.

---

## 2. على السيرفر فقط (BF-01…04) — حاجز الإطلاق

راجع الحقائق في [`../deploy/current-state.md`](../deploy/current-state.md).

| # | العمل | قبول |
|---|---|---|
| BF-01 | رفع `main` → `/var/www/sentrax/backend` · `composer install --no-dev` · migrate · cache · `GET /api/v1/health` | commit السيرفر = `origin/main` · هجرات بلا pending · health ok |
| BF-02 | `OTP_BYPASS=false` · فضّل `APP_ENV=production` | لا يقبل رمزاً عشوائياً على الدومين |
| BF-03 | Horizon على `critical`/`default`/`media`/`reports` · جدولة `reports:daily-snapshots` @ 00:05 Asia/Damascus | مهمة تجريبية تكتمل · لقطة تظهر |
| BF-04 | VirtualHosts + شهادات `platform.` / `channel.` / `warehouse.` | لا تُخدم TickMart بالخطأ |

---

## 3. ليس حاجز رفع باك — لاحقاً / موازي

| بند | وضع |
|---|---|
| BF-11 بذر باقي DOC-08 | عند ظهور مسار يحتاج الرمز فقط |
| BF-23 Larastan | مستمر — التالية `identity` · pin 267 |
| Push/WhatsApp إنتاجي | معلَن غير مضبوط (BF-12) |
| ريبو Next / Flutter | يبني من DocsLast أعلاه |

---

## 4. أوامر بوابة قبل الدفع لـ `main`

```bash
composer deptrac
./vendor/bin/pest --group=arch
./vendor/bin/phpstan analyse
./vendor/bin/pest
./vendor/bin/pint --test
php artisan openapi:generate --check
php docs/api/generate.php && php docs/status/generate.php
```

*لا ترفع للإنتاج إن بقيت بوابة حمراء. تشغيل السيرفر بعد اخضرار البوابات + BF-01…04.*
