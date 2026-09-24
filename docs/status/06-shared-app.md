# المشترك بين التطبيقين — حالة الواجهات

guard `app` · prefix `/api/v1/app/*` (بلا retailer/rep) · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 18 | 18 | 0 | 0 | 0 |

## الجلسة — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CM-004 | SP-01 | `GET` | `/app/session` | — | Identity | جلسة الإقلاع |
| ✅ | EP-CM-005 | SP-01 | `POST` | `/app/auth/logout` | — | Identity | خروج التطبيق |

## التسعير والعروض — 3/3

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-APP-030 | SP-07 | `POST` | `/app/pricing/quote` | — | Pricing | تسعير الخادم |
| ✅ | EP-APP-040 | SP-08 | `GET` | `/app/offers` | — | Promotion | العروض |
| ✅ | EP-APP-041 | SP-08 | `GET` | `/app/offers/{id}` | — | Promotion | تفاصيل عرض |

## وصل الاستلام — 1/1

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CM-050 | SP-13 | `POST` | `/app/receipts/reserve` | `rp.payment.collect` | Finance | حجز رقم وصل |

## الإشعارات والأجهزة — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CM-060 | SP-14 | `GET` | `/app/notifications` | — | Notification | الإشعارات |
| ✅ | EP-CM-061 | SP-14 | `POST` | `/app/notifications/read-all` | — | Notification | تعليم الكل كمقروء |
| ✅ | EP-CM-062 | SP-14 | `DELETE` | `/app/notifications` | — | Notification | مسح عرض الإشعارات |
| ✅ | EP-CM-063 | SP-14 | `POST` | `/app/devices/push-token` | — | Notification | رمز الإشعارات |

## المزامنة — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SY-001 | SP-14 | `GET` | `/app/sync/pull` | — | Sync | سحب المزامنة |
| ✅ | EP-SY-002 | SP-14 | `POST` | `/app/sync/push` | — | Sync | دفع المزامنة |
| ✅ | EP-SY-003 | SP-14 | `GET` | `/app/sync/status` | — | Sync | حالة المزامنة |
| ✅ | EP-SY-004 | SP-14 | `POST` | `/app/sync/resolve-conflict` | — | Sync | حل تعارض |

## المحتوى والولاء — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-APP-100 | SP-15 | `GET` | `/app/content/home-blocks` | — | Content | بلوكات الرئيسية |
| ✅ | EP-APP-101 | SP-15 | `POST` | `/app/content/banners/{id}/click` | — | Content | تسجيل نقرة بانر |
| ✅ | EP-APP-110 | SP-15 | `GET` | `/app/loyalty` | — | Loyalty | النقاط |
| ✅ | EP-APP-111 | SP-15 | `POST` | `/app/loyalty/redeem` | — | Loyalty | استبدال نقاط |

