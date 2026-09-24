# تطبيق التاجر — حالة الواجهات

guard `app` · prefix `/api/v1/app/retailer/*` · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 33 | 33 | 0 | 0 | 0 |

## التسجيل — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-001 | SP-01 | `POST` | `/app/retailer/register` | — | Identity | إكمال تسجيل التاجر |

## الرئيسية والكتالوج — 7/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-010 | SP-06 | `GET` | `/app/retailer/home` | — | Catalog | الرئيسية |
| ✅ | EP-RT-011 | SP-06 | `GET` | `/app/retailer/categories` | — | Catalog | الفئات |
| ✅ | EP-RT-012 | SP-06 | `GET` | `/app/retailer/products` | — | Catalog | المنتجات |
| ✅ | EP-RT-013 | SP-06 | `GET` | `/app/retailer/products/{id}` | — | Catalog | تفاصيل منتج |
| ✅ | EP-RT-014 | SP-06 | `POST` | `/app/retailer/products/{id}/favorite` | — | Catalog | مفضلة |
| ✅ | EP-RT-015A | SP-06 | `GET` | `/app/retailer/brands` | — | Catalog | العلامات |
| ✅ | EP-RT-015B | SP-06 | `GET` | `/app/retailer/brands/{id}` | — | Catalog | صفحة العلامة |

## النواقص — 3/3

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-070A | SP-06 | `GET` | `/app/retailer/shortages` | — | Catalog | النواقص |
| ✅ | EP-RT-070B | SP-06 | `POST` | `/app/retailer/shortages` | — | Catalog | إضافة ناقص |
| ✅ | EP-RT-070C | SP-06 | `DELETE` | `/app/retailer/shortages/{id}` | — | Catalog | حذف ناقص |

## السلة — 6/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-020 | SP-09 | `GET` | `/app/retailer/cart` | — | Ordering | السلة |
| ✅ | EP-RT-021 | SP-09 | `POST` | `/app/retailer/cart/lines` | — | Ordering | إضافة للسلة |
| ✅ | EP-RT-022 | SP-09 | `PATCH` | `/app/retailer/cart/lines/{id}` | — | Ordering | تعديل كمية |
| ✅ | EP-RT-023 | SP-09 | `DELETE` | `/app/retailer/cart/lines/{id}` | — | Ordering | حذف بند |
| ✅ | EP-RT-024 | SP-09 | `PATCH` | `/app/retailer/cart/sections/{ref}` | — | Ordering | ملاحظة/جدولة قسم |
| ✅ | EP-RT-025 | SP-09 | `POST` | `/app/retailer/cart/submit` | — | Ordering | إرسال الطلب |

## طلباتي والتتبع — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-030 | SP-09 | `GET` | `/app/retailer/orders` | — | Ordering | طلباتي |
| ✅ | EP-RT-031 | SP-09 | `GET` | `/app/retailer/orders/{id}` | — | Ordering | تفاصيل طلب |
| ✅ | EP-RT-032 | SP-09 | `POST` | `/app/retailer/orders/{id}/cancel` | — | Ordering | إلغاء طلب |
| ✅ | EP-RT-033 | SP-09 | `POST` | `/app/retailer/orders/{id}/reorder` | — | Ordering | إعادة الطلب |
| ✅ | EP-RT-034 | SP-09 | `GET` | `/app/retailer/orders/{id}/tracking` | — | Ordering | تتبع الطلب |

## الاستلام والمرتجعات والتقييم — 6/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-040 | SP-12 | `GET` | `/app/retailer/receipts/{subOrderId}` | `rt.receive.confirm` | Delivery | قائمة الاستلام |
| ✅ | EP-RT-041 | SP-12 | `PATCH` | `/app/retailer/receipts/{id}/lines/{lineId}` | `rt.receive.confirm` | Delivery | تعديل بند الاستلام |
| ✅ | EP-RT-042 | SP-12 | `POST` | `/app/retailer/receipts/{id}/confirm` | `rt.receive.confirm` | Delivery | تأكيد الاستلام |
| ✅ | EP-RT-043 | SP-12 | `POST` | `/app/retailer/return-requests` | `rt.receive.return_request` | Returns | طلب إرجاع/استبدال |
| ✅ | EP-RT-044 | SP-12 | `GET` | `/app/retailer/return-requests` | — | Returns | مرتجعاتي |
| ✅ | EP-RT-045 | SP-12 | `POST` | `/app/retailer/reps/{id}/rate` | — | Delivery | تقييم المندوب |

## الدفعات والحساب — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RT-050 | SP-13 | `POST` | `/app/retailer/payments` | `rt.payment.record` | Finance | gated by `app.kind:retailer` — the kind implies `rt.payment.record` |
| ✅ | EP-RT-051 | SP-13 | `GET` | `/app/retailer/account/summary` | — | Finance | ملخص الحساب |
| ✅ | EP-RT-052 | SP-13 | `GET` | `/app/retailer/account/statement` | `rt.account.statement` | Finance | gated by `app.kind:retailer` — the kind implies `rt.account.statement` |
| ✅ | EP-RT-053 | SP-13 | `POST` | `/app/retailer/account/statement/export` | `rt.account.statement` | Finance | gated by `app.kind:retailer` — the kind implies `rt.account.statement` |
| ✅ | EP-RT-054 | SP-13 | `GET` | `/app/retailer/debts` | — | Finance | الذمم |

