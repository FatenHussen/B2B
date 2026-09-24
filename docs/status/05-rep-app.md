# تطبيق المندوب — حالة الواجهات

guard `app` · prefix `/api/v1/app/rep/*` · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 37 | 37 | 0 | 0 | 0 |

## التسجيل والحالة — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-001 | SP-01 | `POST` | `/app/rep/register` | — | Identity | إكمال تسجيل المندوب |
| ✅ | EP-RP-034 | SP-10 | `PATCH` | `/app/rep/status` | — | Identity | داخل/خارج الخدمة |

## الرئيسية — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-002 | SP-01 | `GET` | `/app/rep/home` | — | Identity | رئيسية المندوب |

## الكتالوج والعملاء والمناطق — 8/8

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-010 | SP-06 | `GET` | `/app/rep/products` | — | Catalog | منتجات المندوب |
| ✅ | EP-RP-011 | SP-06 | `GET` | `/app/rep/products/{id}` | — | Catalog | تفاصيل منتج المندوب |
| ✅ | EP-RP-070A | SP-06 | `GET` | `/app/rep/customers` | — | Identity | زبائن المندوب |
| ✅ | EP-RP-070B | SP-06 | `POST` | `/app/rep/customers` | — | Identity | إضافة محل |
| ✅ | EP-RP-070C | SP-06 | `GET` | `/app/rep/customers/{id}` | — | Identity | تفاصيل محل |
| ✅ | EP-RP-071 | SP-06 | `POST` | `/app/rep/zones` | — | Identity | إضافة منطقة تغطية |
| ✅ | EP-RP-072 | SP-06 | `GET` | `/app/rep/zones` | — | Identity | مناطق تغطية المندوب |
| ✅ | EP-RP-020 | SP-09 | `GET` | `/app/rep/zones/{id}/shops` | — | Identity | محلات المنطقة |

## السلة — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-021 | SP-09 | `POST` | `/app/rep/cart/lines` | — | Ordering | إضافة لسلة محل |
| ✅ | EP-RP-022 | SP-09 | `GET` | `/app/rep/cart` | — | Ordering | سلة المندوب |
| ✅ | EP-RP-023 | SP-09 | `POST` | `/app/rep/cart/sections/{retailer_id}/submit` | — | Ordering | إرسال طلب محل |
| ✅ | EP-RP-025 | SP-09 | `PATCH` | `/app/rep/cart/lines/{id}` | — | Ordering | تعديل كمية سطر سلة محل |
| ✅ | EP-RP-026 | SP-09 | `DELETE` | `/app/rep/cart/lines/{id}` | — | Ordering | حذف سطر سلة محل |

## طلبات المندوب — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-024 | SP-09 | `GET` | `/app/rep/orders` | — | Ordering | طلبات المندوب |

## قبول الطلبات والمجدولة — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-030 | SP-10 | `GET` | `/app/rep/assignments` | `rp.delivery.accept` | Ordering | إسنادات بانتظار القبول |
| ✅ | EP-RP-031 | SP-10 | `POST` | `/app/rep/assignments/{id}/accept` | `rp.delivery.accept` | Ordering | قبول الإسناد |
| ✅ | EP-RP-032 | SP-10 | `POST` | `/app/rep/assignments/{id}/reject` | `rp.delivery.accept` | Ordering | رفض الإسناد |
| ✅ | EP-RP-033 | SP-10 | `GET` | `/app/rep/scheduled-orders` | `rp.delivery.accept` | Ordering | الطلبات المجدولة |

## استلام العهدة من المستودع — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-040 | SP-11 | `GET` | `/app/rep/warehouse-receipts` | `rp.warehouse.receive` | Fulfillment | استلام العهدة |
| ✅ | EP-RP-041 | SP-11 | `POST` | `/app/rep/warehouse-receipts/{handoverId}/confirm` | `rp.warehouse.receive` | Fulfillment | تأكيد استلام العهدة |

## التسليم والتتبع والمرتجعات — 8/8

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-050 | SP-12 | `GET` | `/app/rep/deliveries` | `rp.delivery.deliver` | Delivery | قائمة التسليم |
| ✅ | EP-RP-051 | SP-12 | `GET` | `/app/rep/deliveries/{id}` | `rp.delivery.deliver` | Delivery | تفاصيل التسليم |
| ✅ | EP-RP-052 | SP-12 | `PATCH` | `/app/rep/deliveries/{id}/lines/{lineId}` | `rp.delivery.deliver` | Delivery | تعديل بند التسليم |
| ✅ | EP-RP-053 | SP-12 | `POST` | `/app/rep/deliveries/{id}/complete` | `rp.delivery.deliver` | Delivery | إنهاء التسليم |
| ✅ | EP-RP-054 | SP-12 | `POST` | `/app/rep/deliveries/{id}/postpone` | `rp.delivery.postpone` | Delivery | تأجيل التسليم |
| ✅ | EP-RP-055 | SP-12 | `POST` | `/app/rep/deliveries/{id}/fail` | `rp.delivery.deliver` | Delivery | تعذر التسليم |
| ✅ | EP-RP-056 | SP-12 | `POST` | `/app/rep/locations/ping` | — | Delivery | نبضات الموقع |
| ✅ | EP-RP-057 | SP-12 | `POST` | `/app/rep/return-requests` | `rp.delivery.return_request` | Returns | طلب إرجاع ميداني |

## الدفعات والمحفظة — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-060 | SP-13 | `POST` | `/app/rep/payments` | `rp.payment.collect` | Finance | تحصيل دفعة |
| ✅ | EP-RP-061 | SP-13 | `GET` | `/app/rep/wallet` | `rp.wallet.view` | Finance | المحفظة |
| ✅ | EP-RP-062 | SP-13 | `POST` | `/app/rep/wallet/withdrawals` | `rp.payment.withdraw` | Finance | تسليم نقدية |
| ✅ | EP-RP-063 | SP-13 | `GET` | `/app/rep/wallet/withdrawals` | `rp.wallet.view` | Finance | سجل التسليمات |
| ✅ | EP-RP-064 | SP-13 | `GET` | `/app/rep/receivables` | `rp.wallet.view` | Finance | ذمم المحلات |

## أخرى — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-073 | SP-01 | `PATCH` | `/app/rep/profile` | — | Identity | تعديل ملف المندوب |

