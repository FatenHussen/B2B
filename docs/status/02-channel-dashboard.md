# لوحة قناة التوريد — حالة الواجهات

guard `channel` · prefix `/api/v1/channel/*` · مولَّد آلياً في 2026-09-19 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 78 | 78 | 0 | 0 | 5 |

## الدخول — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CH-001 | SP-01 | `POST` | `/channel/auth/request-otp` | — | Identity | طلب رمز دخول القناة |
| ✅ | EP-CH-002 | SP-01 | `POST` | `/channel/auth/verify-otp` | — | Identity | تحقق دخول القناة |

## الكتالوج — 13/13

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-010A | SP-06 | `GET` | `/channel/brands` | `sc.catalog.view` | Catalog | العلامات |
| ✅ | EP-SC-010B | SP-06 | `POST` | `/channel/brands` | `sc.catalog.create` | Catalog | إنشاء علامة |
| ✅ | EP-SC-011 | SP-06 | `GET` | `/channel/categories/tree` | `sc.catalog.view` | Catalog | شجرة الفئات |
| ✅ | EP-SC-012 | SP-06 | `POST` | `/channel/categories` | `sc.catalog.create` | Catalog | إنشاء فئة |
| ✅ | EP-SC-013 | SP-06 | `POST` | `/channel/categories/reorder` | `sc.catalog.update` | Catalog | إعادة ترتيب الفئات |
| ✅ | EP-SC-014 | SP-06 | `GET` | `/channel/products` | `sc.catalog.view` | Catalog | المنتجات |
| ✅ | EP-SC-015 | SP-06 | `POST` | `/channel/products` | `sc.catalog.create` | Catalog | إنشاء منتج |
| ✅ | EP-SC-016 | SP-06 | `PUT` | `/channel/products/{id}` | `sc.catalog.update` | Catalog | تحديث منتج |
| ✅ | EP-SC-017 | SP-06 | `POST` | `/channel/products/{id}/variants/generate` | `sc.catalog.variants` | Catalog | توليد التباينات |
| ✅ | EP-SC-018 | SP-06 | `POST` | `/channel/products/bulk` | `sc.catalog.update` | Catalog | إجراء جماعي على المنتجات |
| ✅ | EP-SC-019 | SP-06 | `POST` | `/channel/catalog/import` | `sc.catalog.import` | Catalog | استيراد الكتالوج |
| ✅ | EP-SC-020 | SP-06 | `GET` | `/channel/catalog/export` | `sc.catalog.view` | Catalog | تصدير الكتالوج |
| ✅ | EP-SC-031 | SP-07 | `PUT` | `/channel/products/{id}/pricing` | `sc.pricing.update` | Pricing | تسعير منتج |

## المندوبون — 12/12

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-075 | SP-06 | `GET` | `/channel/reps` | `sc.reps.view` | Identity | قائمة المندوبين |
| ✅ | EP-SC-076 | SP-06 | `GET` | `/channel/reps/{id}` | `sc.reps.view` | Identity | بطاقة مندوب |
| ✅ | EP-SC-077 | SP-06 | `POST` | `/channel/reps/{id}/approve` | `sc.reps.update` | Identity | اعتماد مندوب |
| ✅ | EP-SC-078 | SP-06 | `POST` | `/channel/reps/{id}/reject` | `sc.reps.update` | Identity | رفض مندوب |
| ✅ | EP-SC-079 | SP-06 | `POST` | `/channel/reps/{id}/disable` | `sc.reps.disable` | Identity | تعطيل مندوب |
| ✅ | EP-SC-088 | SP-06 | `GET` | `/channel/rep-zone-requests` | `sc.reps.view` | Identity | طلبات مناطق المندوبين |
| ✅ | EP-SC-089 | SP-06 | `POST` | `/channel/rep-zone-requests/{id}/decide` | `sc.reps.update` | Identity | قرار طلب منطقة مندوب |
| ✅ | EP-SC-093A | SP-06 | `GET` | `/channel/rep-sourced-shops` | `sc.reps.view` | Identity | محلات سجّلها المندوب |
| ✅ | EP-SC-093B | SP-06 | `POST` | `/channel/rep-sourced-shops/{id}/decide` | `sc.reps.update` | Identity | قرار محل مندوب |
| ✅ | EP-SC-035 | SP-07 | `PUT` | `/channel/reps/{id}/discount-cap` | `sc.reps.update` | Pricing | سقف خصم المندوب |
| ✅ | EP-SC-084 | SP-13 | `POST` | `/channel/reps/{id}/settle` | `sc.reps.settle` | Finance | تسوية عهدة المندوب |
| ✅ | EP-SC-087 | SP-13 | `GET` | `/channel/reps/{id}/wallet` | `sc.reps.wallet` | Finance | محفظة المندوب |

## التسعير — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-030A | SP-07 | `GET` | `/channel/price-lists` | `sc.pricing.view` | Pricing | قوائم الأسعار |
| ✅ | EP-SC-030B | SP-07 | `POST` | `/channel/price-lists` | `sc.pricing.update` | Pricing | إنشاء قائمة أسعار |
| ✅ | EP-SC-032 | SP-07 | `POST` | `/channel/price-lists/{id}/schedule` | `sc.pricing.schedule` | Pricing | جدولة قائمة أسعار |
| ✅ | EP-SC-033 | SP-07 | `POST` | `/channel/pricing/bulk-update` | `sc.pricing.update` | Pricing | تحديث أسعار جماعي |
| ✅ | EP-SC-034 | SP-07 | `GET` | `/channel/pricing/change-log` | `sc.pricing.view` | Pricing | سجل تغيير الأسعار |

## العروض — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-040A | SP-08 | `GET` | `/channel/offers` | `sc.offers.view` | Promotion | العروض |
| ✅ | EP-SC-040B | SP-08 | `POST` | `/channel/offers` | `sc.offers.create` | Promotion | إنشاء عرض |
| ✅ | EP-SC-041 | SP-08 | `PATCH` | `/channel/offers/{id}/stop` | `sc.offers.stop` | Promotion | إيقاف عرض |
| ✅ | EP-SC-042 | SP-08 | `GET` | `/channel/offers/{id}/performance` | `sc.offers.view` | Promotion | أداء العرض |

## المخزون — 5/5

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-050 | SP-09 | `GET` | `/channel/inventory/levels` | `sc.inventory.view` | Inventory | أرصدة المخزون |
| ✅ | EP-SC-051 | SP-09 | `POST` | `/channel/inventory/adjust` | `sc.inventory.adjust` | Inventory | تسوية مخزون |
| ✅ | EP-SC-052 | SP-09 | `POST` | `/channel/inventory/transfers` | `sc.inventory.transfer` | Inventory | تحويل مخزون |
| ✅ | EP-SC-053 | SP-09 | `GET` | `/channel/inventory/movements` | `sc.inventory.view` | Inventory | حركة المخزون |
| ✅ | EP-SC-054 | SP-09 | `PUT` | `/channel/inventory/reorder-points` | `sc.inventory.reorder` | Inventory | نقاط إعادة الطلب |

## الطلبات — 10/10

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-060 | SP-10 | `GET` | `/channel/sub-orders` | `sc.orders.view` | Ordering | الطلبات الفرعية |
| ✅ | EP-SC-061 | SP-10 | `GET` | `/channel/sub-orders/{id}` | `sc.orders.view` | Ordering | تفاصيل طلب فرعي |
| ✅ | EP-SC-062 | SP-10 | `POST` | `/channel/sub-orders/{id}/confirm` | `sc.orders.confirm` | Ordering | تأكيد طلب |
| ✅ | EP-SC-063 | SP-10 | `POST` | `/channel/sub-orders/bulk-confirm` | `sc.orders.confirm` | Ordering | تأكيد جماعي |
| ✅ | EP-SC-064 | SP-10 | `POST` | `/channel/sub-orders/{id}/reject` | `sc.orders.reject` | Ordering | رفض طلب |
| ✅ | EP-SC-065 | SP-10 | `PATCH` | `/channel/sub-orders/{id}/lines` | `sc.orders.edit_lines` | Ordering | تعديل بنود الطلب |
| ✅ | EP-SC-066 | SP-10 | `POST` | `/channel/sub-orders/assign` | `sc.orders.assign` | Ordering | إسناد لمندوب |
| ✅ | EP-SC-067 | SP-10 | `POST` | `/channel/sub-orders/{id}/reassign` | `sc.orders.reassign` | Ordering | إعادة إسناد |
| ✅ | EP-SC-068 | SP-10 | `POST` | `/channel/sub-orders/{id}/schedule` | `sc.orders.schedule` | Ordering | جدولة طلب |
| ✅ | EP-SC-069 | SP-10 | `POST` | `/channel/sub-orders/{id}/cancel` | `sc.orders.cancel` | Ordering | إلغاء طلب |

## المرتجعات — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-070 | SP-12 | `GET` | `/channel/return-requests` | `sc.returns.view` | Returns | طلبات الإرجاع |
| ✅ | EP-SC-071 | SP-12 | `POST` | `/channel/return-requests/{id}/decide` | `sc.returns.decide` | Returns | قرار الإرجاع |

## المالية — 6/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-080 | SP-13 | `GET` | `/channel/invoices` | `sc.finance.view` | Finance | الفواتير |
| ✅ | EP-SC-081 | SP-13 | `POST` | `/channel/invoices/{id}/credit-note` | `sc.finance.credit_note` | Finance | إشعار دائن |
| ✅ | EP-SC-082 | SP-13 | `POST` | `/channel/invoices/{id}/void` | `sc.finance.void_invoice` | Finance | إلغاء فاتورة |
| ✅ | EP-SC-083 | SP-13 | `POST` | `/channel/payments` | `sc.finance.payment` | Finance | تسجيل دفعة مكتبية |
| ✅ | EP-SC-085 | SP-13 | `GET` | `/channel/finance/aging` | `sc.finance.aging` | Finance | أعمار الذمم |
| ✅ | EP-SC-086 | SP-13 | `PUT` | `/channel/retailers/{id}/credit` | `sc.retailers.credit` | Finance | سقف ائتمان التاجر |

## الإشعارات — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-090 | SP-14 | `POST` | `/channel/notifications` | `sc.notify.send` | Notification | إرسال إشعار |
| ✅ | EP-SC-091A | SP-14 | `GET` | `/channel/notifications/templates` | `sc.notify.templates` | Notification | قوالب الإشعارات |
| ✅ | EP-SC-091B | SP-14 | `PUT` | `/channel/notifications/templates` | `sc.notify.templates` | Notification | تحديث قالب إشعار |
| ✅ | EP-SC-092 | SP-14 | `GET` | `/channel/notifications/log` | `sc.notify.view` | Notification | سجل الإشعارات |

## المحتوى والولاء — 11/11

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-100A | SP-15 | `GET` | `/channel/content/intro` | `sc.content.intro` | Content | شاشة الانترو |
| ✅ | EP-SC-100B | SP-15 | `PUT` | `/channel/content/intro` | `sc.content.intro` | Content | تحديث الانترو |
| ✅ | EP-SC-101A | SP-15 | `GET` | `/channel/content/banners` | `sc.content.banners` | Content | البنرات |
| ✅ | EP-SC-101B | SP-15 | `POST` | `/channel/content/banners` | `sc.content.banners` | Content | إنشاء بنر |
| ✅ | EP-SC-102 | SP-15 | `GET` | `/channel/content/banners/{id}/stats` | `sc.content.banners` | Content | إحصاء البنر |
| ✅ | EP-SC-103A | SP-15 | `GET` | `/channel/content/sliders` | `sc.content.sliders` | Content | الساليدرات |
| ✅ | EP-SC-103B | SP-15 | `POST` | `/channel/content/sliders` | `sc.content.sliders` | Content | إنشاء ساليدر |
| ✅ | EP-SC-110A | SP-15 | `GET` | `/channel/loyalty/rules` | `sc.loyalty.manage` | Loyalty | قواعد النقاط |
| ✅ | EP-SC-110B | SP-15 | `PUT` | `/channel/loyalty/rules` | `sc.loyalty.manage` | Loyalty | تحديث قواعد النقاط |
| ✅ | EP-SC-111A | SP-15 | `GET` | `/channel/loyalty/rewards` | `sc.loyalty.manage` | Loyalty | المكافآت |
| ✅ | EP-SC-111B | SP-15 | `POST` | `/channel/loyalty/rewards` | `sc.loyalty.manage` | Loyalty | إنشاء مكافأة |

## لوحة القيادة والتقارير — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-120 | SP-16 | `GET` | `/channel/dashboard` | `sc.dashboard.view` | Reporting | لوحة القناة |
| ✅ | EP-SC-121 | SP-16 | `GET` | `/channel/reports/{type}` | `sc.reports.view` | Reporting | تقرير القناة |
| ✅ | EP-SC-122 | SP-16 | `POST` | `/channel/reports/{type}/export` | `sc.reports.export` | Reporting | تصدير تقرير |
| ✅ | EP-SC-123 | SP-16 | `GET` | `/channel/reports/margins` | `sc.reports.margins` | Reporting | تقرير الهوامش |

## حيّ خارج الكتالوج

مسارات تخدمها الشيفرة ولا يذكرها الكتالوج. إما تُضاف إلى `docs/api/catalog/` أو تُزال — لا ثالث.

| الطريقة | المسار | الصلاحية | الوحدة |
|---|---|---|---|
| `GET` | `/channel` | `sc.settings.view` | Tenancy |
| `PUT` | `/channel` | `sc.settings.update` | Tenancy |
| `GET` | `/channel/zones` | `sc.zones.view` | Reference |
| `POST` | `/channel/zones` | `sc.zones.manage` | Reference |
| `DELETE` | `/channel/zones/{channelZone}` | `sc.zones.manage` | Reference |

