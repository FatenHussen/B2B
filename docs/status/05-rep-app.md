# تطبيق المندوب — حالة الواجهات

guard `app` · prefix `/api/v1/app/rep/*` · مولَّد آلياً في 2026-09-19 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 30 | 30 | 0 | 0 | 0 |

## التسجيل والحالة — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-001 | SP-01 | `POST` | `/app/rep/register` | — | Identity | إكمال تسجيل المندوب |
| ✅ | EP-RP-034 | SP-10 | `PATCH` | `/app/rep/status` | — | Identity | داخل/خارج الخدمة |

## الرئيسية — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-002 | SP-01 | `GET` | `/app/rep/home` | — | Identity | رئيسية المندوب |

## الكتالوج والعملاء والمناطق — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-010 | SP-06 | `GET` | `/app/rep/products` | — | Catalog | منتجات المندوب |
| ✅ | EP-RP-070A | SP-06 | `GET` | `/app/rep/customers` | — | Identity | زبائن المندوب |
| ✅ | EP-RP-070B | SP-06 | `POST` | `/app/rep/customers` | — | Identity | إضافة محل |
| ✅ | EP-RP-071 | SP-06 | `POST` | `/app/rep/zones` | — | Identity | إضافة منطقة تغطية |
| ✅ | EP-RP-020 | SP-09 | `GET` | `/app/rep/zones/{id}/shops` | — | Identity | محلات المنطقة |

## السلة — 3/3

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-021 | SP-09 | `POST` | `/app/rep/cart/lines` | — | Ordering | إضافة لسلة محل |
| ✅ | EP-RP-022 | SP-09 | `GET` | `/app/rep/cart` | — | Ordering | سلة المندوب |
| ✅ | EP-RP-023 | SP-09 | `POST` | `/app/rep/cart/sections/{retailer_id}/submit` | — | Ordering | إرسال طلب محل |

## قبول الطلبات والمجدولة — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-030 | SP-10 | `GET` | `/app/rep/assignments` | `rp.delivery.accept` | Ordering | gated by `app.kind:rep` — the kind implies `rp.delivery.accept` |
| ✅ | EP-RP-031 | SP-10 | `POST` | `/app/rep/assignments/{id}/accept` | `rp.delivery.accept` | Ordering | gated by `app.kind:rep` — the kind implies `rp.delivery.accept` |
| ✅ | EP-RP-032 | SP-10 | `POST` | `/app/rep/assignments/{id}/reject` | `rp.delivery.accept` | Ordering | gated by `app.kind:rep` — the kind implies `rp.delivery.accept` |
| ✅ | EP-RP-033 | SP-10 | `GET` | `/app/rep/scheduled-orders` | `rp.delivery.accept` | Ordering | gated by `app.kind:rep` — the kind implies `rp.delivery.accept` |

## استلام العهدة من المستودع — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-040 | SP-11 | `GET` | `/app/rep/warehouse-receipts` | `rp.warehouse.receive` | Fulfillment | gated by `app.kind:rep` — the kind implies `rp.warehouse.receive` |
| ✅ | EP-RP-041 | SP-11 | `POST` | `/app/rep/warehouse-receipts/{handoverId}/confirm` | `rp.warehouse.receive` | Fulfillment | gated by `app.kind:rep` — the kind implies `rp.warehouse.receive` |

## التسليم والتتبع والمرتجعات — 8/8

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-050 | SP-12 | `GET` | `/app/rep/deliveries` | `rp.delivery.deliver` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.deliver` |
| ✅ | EP-RP-051 | SP-12 | `GET` | `/app/rep/deliveries/{id}` | `rp.delivery.deliver` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.deliver` |
| ✅ | EP-RP-052 | SP-12 | `PATCH` | `/app/rep/deliveries/{id}/lines/{lineId}` | `rp.delivery.deliver` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.deliver` |
| ✅ | EP-RP-053 | SP-12 | `POST` | `/app/rep/deliveries/{id}/complete` | `rp.delivery.deliver` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.deliver` |
| ✅ | EP-RP-054 | SP-12 | `POST` | `/app/rep/deliveries/{id}/postpone` | `rp.delivery.postpone` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.postpone` |
| ✅ | EP-RP-055 | SP-12 | `POST` | `/app/rep/deliveries/{id}/fail` | `rp.delivery.deliver` | Delivery | gated by `app.kind:rep` — the kind implies `rp.delivery.deliver` |
| ✅ | EP-RP-056 | SP-12 | `POST` | `/app/rep/locations/ping` | — | Delivery | نبضات الموقع |
| ✅ | EP-RP-057 | SP-12 | `POST` | `/app/rep/return-requests` | `rp.delivery.return_request` | Returns | gated by `app.kind:rep` — the kind implies `rp.delivery.return_request` |

## الدفعات والمحفظة — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-RP-060 | SP-13 | `POST` | `/app/rep/payments` | `rp.payment.collect` | Finance | gated by `app.kind:rep` — the kind implies `rp.payment.collect` |
| ✅ | EP-RP-061 | SP-13 | `GET` | `/app/rep/wallet` | `rp.wallet.view` | Finance | gated by `app.kind:rep` — the kind implies `rp.wallet.view` |
| ✅ | EP-RP-062 | SP-13 | `POST` | `/app/rep/wallet/withdrawals` | `rp.payment.withdraw` | Finance | gated by `app.kind:rep` — the kind implies `rp.payment.withdraw` |
| ✅ | EP-RP-063 | SP-13 | `GET` | `/app/rep/wallet/withdrawals` | `rp.wallet.view` | Finance | gated by `app.kind:rep` — the kind implies `rp.wallet.view` |
| ✅ | EP-RP-064 | SP-13 | `GET` | `/app/rep/receivables` | `rp.wallet.view` | Finance | gated by `app.kind:rep` — the kind implies `rp.wallet.view` |

