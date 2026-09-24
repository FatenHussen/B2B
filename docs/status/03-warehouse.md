# واجهة المستودع — حالة الواجهات

guard `warehouse` · prefix `/api/v1/warehouse/*` · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 24 | 24 | 0 | 0 | 0 |

## الدخول — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-001 | SP-01 | `POST` | `/warehouse/auth/device-login` | — | Identity | دخول جهاز المستودع |

## صفوف العمل والتجهيز — 7/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-010 | SP-11 | `GET` | `/warehouse/queues` | `wh.queue.view` | Fulfillment | طوابير المستودع |
| ✅ | EP-WH-011 | SP-11 | `GET` | `/warehouse/picking-lists/{id}` | `wh.picking.execute` | Fulfillment | قائمة الالتقاط |
| ✅ | EP-WH-012 | SP-11 | `POST` | `/warehouse/picking-lists/{id}/scan` | `wh.picking.execute` | Fulfillment | مسح صنف |
| ✅ | EP-WH-013 | SP-11 | `POST` | `/warehouse/picking-lists/{id}/lines/{lineId}/manual` | `wh.picking.execute` | Fulfillment | إدخال يدوي |
| ✅ | EP-WH-014 | SP-11 | `POST` | `/warehouse/picking-lists/{id}/shortage` | `wh.picking.shortage` | Fulfillment | نقص أثناء الالتقاط |
| ✅ | EP-WH-014A | SP-11 | `POST` | `/warehouse/picking-lists/batch` | `wh.picking.execute` | Fulfillment | إنشاء قوائم التقاط دفعة |
| ✅ | EP-WH-015 | SP-11 | `POST` | `/warehouse/picking-lists/{id}/complete` | `wh.picking.execute` | Fulfillment | إنهاء الالتقاط |

## التغليف — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-016 | SP-11 | `POST` | `/warehouse/packing/{id}/verify` | `wh.packing.execute` | Fulfillment | تحقق التغليف |
| ✅ | EP-WH-017 | SP-11 | `POST` | `/warehouse/packing/{id}/complete` | `wh.packing.execute` | Fulfillment | إنهاء التغليف |

## تسليم العهدة — 3/3

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-018 | SP-11 | `GET` | `/warehouse/handovers/pending` | `wh.handover.execute` | Fulfillment | عهد بانتظار المندوب |
| ✅ | EP-WH-019 | SP-11 | `POST` | `/warehouse/handovers` | `wh.handover.execute` | Fulfillment | إنشاء عهدة |
| ✅ | EP-WH-020 | SP-11 | `POST` | `/warehouse/handovers/{id}/return-trip` | `wh.handover.return_trip` | Fulfillment | عودة المندوب |

## الاستلام الوارد — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-021 | SP-11 | `POST` | `/warehouse/receiving` | `wh.receiving.execute` | Fulfillment | استلام وارد |
| ✅ | EP-WH-022 | SP-11 | `POST` | `/warehouse/receiving/{id}/qc` | `wh.receiving.qc` | Fulfillment | فحص الوارد |

## الجرد — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-023A | SP-11 | `POST` | `/warehouse/stocktakes` | `wh.stocktake.execute` | Fulfillment | بدء جرد |
| ✅ | EP-WH-023B | SP-11 | `POST` | `/warehouse/stocktakes/{id}/lines` | `wh.stocktake.execute` | Fulfillment | تسجيل العد |
| ✅ | EP-WH-023C | SP-11 | `POST` | `/warehouse/stocktakes/{id}/submit` | `wh.stocktake.execute` | Fulfillment | تسليم الجرد |
| ✅ | EP-WH-024 | SP-11 | `POST` | `/warehouse/stocktakes/{id}/approve` | `wh.stocktake.approve` | Fulfillment | اعتماد الجرد |

## المرتجعات — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-030 | SP-12 | `POST` | `/warehouse/returns/{id}/sort` | `wh.returns.sort` | Returns | فرز المرتجع |

## أخرى — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-WH-040 | SP-11 | `GET` | `/warehouse/stock-lots` | `wh.receiving.lots` | Fulfillment | تسجيل الدفعات وتواريخ الصلاحية |
| ✅ | EP-WH-040A | SP-11 | `POST` | `/warehouse/stock-lots/{id}/adjust` | `wh.receiving.lots` | Fulfillment | تسوية دفعة مخزون |
| ✅ | EP-WH-042 | SP-11 | `POST` | `/warehouse/picking-waves` | `wh.picking.execute` | Fulfillment | إنشاء موجة التقاط |
| ✅ | EP-WH-042A | SP-11 | `GET` | `/warehouse/picking-waves/{id}` | `wh.picking.execute` | Fulfillment | عرض موجة التقاط |

