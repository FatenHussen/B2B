# لوحة قناة التوريد — حالة الواجهات

guard `channel` · prefix `/api/v1/channel/*` · مولَّد آلياً في 2026-09-24 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 123 | 123 | 0 | 0 | 0 |

## الدخول — 2/2

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-CH-001 | SP-01 | `POST` | `/channel/auth/request-otp` | — | Identity | طلب رمز دخول القناة |
| ✅ | EP-CH-002 | SP-01 | `POST` | `/channel/auth/verify-otp` | — | Identity | تحقق دخول القناة |

## الكتالوج — 22/22

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-010A | SP-06 | `GET` | `/channel/brands` | `sc.catalog.view` | Catalog | العلامات |
| ✅ | EP-SC-010B | SP-06 | `POST` | `/channel/brands` | `sc.catalog.create` | Catalog | إنشاء علامة |
| ✅ | EP-SC-010C | SP-06 | `GET` | `/channel/brands/{id}` | `sc.catalog.view` | Catalog | تفاصيل علامة |
| ✅ | EP-SC-010D | SP-06 | `PUT` | `/channel/brands/{id}` | `sc.catalog.update` | Catalog | تحديث علامة |
| ✅ | EP-SC-011 | SP-06 | `GET` | `/channel/categories/tree` | `sc.catalog.view` | Catalog | شجرة الفئات |
| ✅ | EP-SC-012 | SP-06 | `POST` | `/channel/categories` | `sc.catalog.create` | Catalog | إنشاء فئة |
| ✅ | EP-SC-012A | SP-06 | `PUT` | `/channel/categories/{id}` | `sc.catalog.update` | Catalog | تعديل فئة |
| ✅ | EP-SC-012B | SP-06 | `PATCH` | `/channel/categories/{id}/status` | `sc.catalog.update` | Catalog | تعطيل/تفعيل فئة |
| ✅ | EP-SC-013 | SP-06 | `POST` | `/channel/categories/reorder` | `sc.catalog.update` | Catalog | إعادة ترتيب الفئات |
| ✅ | EP-SC-014 | SP-06 | `GET` | `/channel/products` | `sc.catalog.view` | Catalog | المنتجات |
| ✅ | EP-SC-014A | SP-06 | `GET` | `/channel/products/{id}` | `sc.catalog.view` | Catalog | تفاصيل منتج |
| ✅ | EP-SC-015 | SP-06 | `POST` | `/channel/products` | `sc.catalog.create` | Catalog | إنشاء منتج |
| ✅ | EP-SC-016 | SP-06 | `PUT` | `/channel/products/{id}` | `sc.catalog.update` | Catalog | تحديث منتج |
| ✅ | EP-SC-016A | SP-06 | `POST` | `/channel/products/{id}/duplicate` | `sc.catalog.create` | Catalog | نسخ منتج |
| ✅ | EP-SC-017 | SP-06 | `POST` | `/channel/products/{id}/variants/generate` | `sc.catalog.variants` | Catalog | توليد التباينات |
| ✅ | EP-SC-017A | SP-06 | `PUT` | `/channel/products/{id}/variants/{variantId}` | `sc.catalog.variants` | Catalog | تحديث توليفة |
| ✅ | EP-SC-017B | SP-06 | `DELETE` | `/channel/products/{id}/variants/{variantId}` | `sc.catalog.variants` | Catalog | حذف توليفة |
| ✅ | EP-SC-018 | SP-06 | `POST` | `/channel/products/bulk` | `sc.catalog.update` | Catalog | إجراء جماعي على المنتجات |
| ✅ | EP-SC-019 | SP-06 | `POST` | `/channel/catalog/import` | `sc.catalog.import` | Catalog | استيراد الكتالوج |
| ✅ | EP-SC-019A | SP-06 | `GET` | `/channel/catalog/import/template` | `sc.catalog.import` | Catalog | قالب استيراد الكتالوج |
| ✅ | EP-SC-020 | SP-06 | `GET` | `/channel/catalog/export` | `sc.catalog.view` | Catalog | تصدير الكتالوج |
| ✅ | EP-SC-031 | SP-07 | `PUT` | `/channel/products/{id}/pricing` | `sc.pricing.update` | Pricing | تسعير منتج |

## المندوبون — 14/14

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-075 | SP-06 | `GET` | `/channel/reps` | `sc.reps.view` | Identity | قائمة المندوبين |
| ✅ | EP-SC-075A | SP-06 | `GET` | `/channel/reps/{id}/live` | `sc.reps.view` | Identity | موقع المندوب الحي |
| ✅ | EP-SC-076 | SP-06 | `GET` | `/channel/reps/{id}` | `sc.reps.view` | Identity | بطاقة مندوب |
| ✅ | EP-SC-077 | SP-06 | `POST` | `/channel/reps/{id}/approve` | `sc.reps.update` | Identity | اعتماد مندوب |
| ✅ | EP-SC-078 | SP-06 | `POST` | `/channel/reps/{id}/reject` | `sc.reps.update` | Identity | رفض مندوب |
| ✅ | EP-SC-079 | SP-06 | `POST` | `/channel/reps/{id}/disable` | `sc.reps.disable` | Identity | تعطيل مندوب |
| ✅ | EP-SC-088 | SP-06 | `GET` | `/channel/rep-zone-requests` | `sc.reps.view` | Identity | طلبات مناطق المندوبين |
| ✅ | EP-SC-089 | SP-06 | `POST` | `/channel/rep-zone-requests/{id}/decide` | `sc.reps.update` | Identity | قرار طلب منطقة مندوب |
| ✅ | EP-SC-093A | SP-06 | `GET` | `/channel/rep-sourced-shops` | `sc.reps.view` | Identity | محلات سجّلها المندوب |
| ✅ | EP-SC-093B | SP-06 | `POST` | `/channel/rep-sourced-shops/{id}/decide` | `sc.reps.update` | Identity | قرار محل مندوب |
| ✅ | EP-SC-160 | SP-06 | `GET` | `/channel/reps/live` | `sc.reps.track` | Identity | خريطة المندوبين المباشرين |
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

## العروض — 7/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-040A | SP-08 | `GET` | `/channel/offers` | `sc.offers.view` | Promotion | العروض |
| ✅ | EP-SC-040B | SP-08 | `POST` | `/channel/offers` | `sc.offers.create` | Promotion | إنشاء عرض |
| ✅ | EP-SC-040C | SP-08 | `GET` | `/channel/offers/{id}` | `sc.offers.view` | Promotion | تفاصيل عرض |
| ✅ | EP-SC-040D | SP-08 | `PUT` | `/channel/offers/{id}` | `sc.offers.create` | Promotion | تعديل عرض |
| ✅ | EP-SC-041 | SP-08 | `PATCH` | `/channel/offers/{id}/stop` | `sc.offers.stop` | Promotion | إيقاف عرض |
| ✅ | EP-SC-041A | SP-08 | `PATCH` | `/channel/offers/{id}/activate` | `sc.offers.create` | Promotion | تفعيل عرض |
| ✅ | EP-SC-042 | SP-08 | `GET` | `/channel/offers/{id}/performance` | `sc.offers.view` | Promotion | أداء العرض |

## المخزون — 6/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-050 | SP-09 | `GET` | `/channel/inventory/levels` | `sc.inventory.view` | Inventory | أرصدة المخزون |
| ✅ | EP-SC-051 | SP-09 | `POST` | `/channel/inventory/adjust` | `sc.inventory.adjust` | Inventory | تسوية مخزون |
| ✅ | EP-SC-052 | SP-09 | `POST` | `/channel/inventory/transfers` | `sc.inventory.transfer` | Inventory | تحويل مخزون |
| ✅ | EP-SC-052A | SP-09 | `GET` | `/channel/inventory/transfers` | `sc.inventory.transfer` | Inventory | تحويلات المخزون |
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

## المرتجعات — 3/3

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-070 | SP-12 | `GET` | `/channel/return-requests` | `sc.returns.view` | Returns | طلبات الإرجاع |
| ✅ | EP-SC-071 | SP-12 | `POST` | `/channel/return-requests/{id}/decide` | `sc.returns.decide` | Returns | قرار الإرجاع |
| ✅ | EP-SC-162 | SP-12 | `POST` | `/channel/return-requests/{id}/escalate` | `sc.returns.decide` | Returns | تصعيد مرتجع متأخر |

## المالية — 10/10

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-080 | SP-13 | `GET` | `/channel/invoices` | `sc.finance.view` | Finance | الفواتير |
| ✅ | EP-SC-080A | SP-13 | `GET` | `/channel/invoices/{id}` | `sc.finance.view` | Finance | تفاصيل فاتورة |
| ✅ | EP-SC-081 | SP-13 | `POST` | `/channel/invoices/{id}/credit-note` | `sc.finance.credit_note` | Finance | إشعار دائن |
| ✅ | EP-SC-082 | SP-13 | `POST` | `/channel/invoices/{id}/void` | `sc.finance.void_invoice` | Finance | إلغاء فاتورة |
| ✅ | EP-SC-083 | SP-13 | `POST` | `/channel/payments` | `sc.finance.payment` | Finance | تسجيل دفعة مكتبية |
| ✅ | EP-SC-085 | SP-13 | `GET` | `/channel/finance/aging` | `sc.finance.aging` | Finance | أعمار الذمم |
| ✅ | EP-SC-086 | SP-13 | `PUT` | `/channel/retailers/{id}/credit` | `sc.retailers.credit` | Finance | سقف ائتمان التاجر |
| ✅ | EP-SC-140 | SP-13 | `GET` | `/channel/retailers` | `sc.retailers.view` | Identity | تجّار التغطية |
| ✅ | EP-SC-140A | SP-13 | `GET` | `/channel/retailers/{id}` | `sc.retailers.view` | Identity | ملف التاجر |
| ✅ | EP-SC-164 | SP-13 | `GET` | `/channel/retailers/{id}` | `sc.retailers.view` | Identity | بطاقة التاجر الموسّعة |

## الإشعارات — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-090 | SP-14 | `POST` | `/channel/notifications` | `sc.notify.send` | Notification | إرسال إشعار |
| ✅ | EP-SC-091A | SP-14 | `GET` | `/channel/notifications/templates` | `sc.notify.templates` | Notification | قوالب الإشعارات |
| ✅ | EP-SC-091B | SP-14 | `PUT` | `/channel/notifications/templates` | `sc.notify.templates` | Notification | تحديث قالب إشعار |
| ✅ | EP-SC-092 | SP-14 | `GET` | `/channel/notifications/log` | `sc.notify.view` | Notification | سجل الإشعارات |

## المحتوى والولاء — 17/17

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-100A | SP-15 | `GET` | `/channel/content/intro` | `sc.content.intro` | Content | شاشة الانترو |
| ✅ | EP-SC-100B | SP-15 | `PUT` | `/channel/content/intro` | `sc.content.intro` | Content | تحديث الانترو |
| ✅ | EP-SC-101A | SP-15 | `GET` | `/channel/content/banners` | `sc.content.banners` | Content | البنرات |
| ✅ | EP-SC-101B | SP-15 | `POST` | `/channel/content/banners` | `sc.content.banners` | Content | إنشاء بنر |
| ✅ | EP-SC-101C | SP-15 | `PUT` | `/channel/content/banners/{id}` | `sc.content.banners` | Content | تعديل بنر |
| ✅ | EP-SC-101D | SP-15 | `DELETE` | `/channel/content/banners/{id}` | `sc.content.banners` | Content | حذف بنر |
| ✅ | EP-SC-102 | SP-15 | `GET` | `/channel/content/banners/{id}/stats` | `sc.content.banners` | Content | إحصاء البنر |
| ✅ | EP-SC-103A | SP-15 | `GET` | `/channel/content/sliders` | `sc.content.sliders` | Content | الساليدرات |
| ✅ | EP-SC-103B | SP-15 | `POST` | `/channel/content/sliders` | `sc.content.sliders` | Content | إنشاء ساليدر |
| ✅ | EP-SC-103C | SP-15 | `PUT` | `/channel/content/sliders/{id}` | `sc.content.sliders` | Content | تعديل ساليدر |
| ✅ | EP-SC-103D | SP-15 | `DELETE` | `/channel/content/sliders/{id}` | `sc.content.sliders` | Content | حذف ساليدر |
| ✅ | EP-SC-110A | SP-15 | `GET` | `/channel/loyalty/rules` | `sc.loyalty.manage` | Loyalty | قواعد النقاط |
| ✅ | EP-SC-110B | SP-15 | `PUT` | `/channel/loyalty/rules` | `sc.loyalty.manage` | Loyalty | تحديث قواعد النقاط |
| ✅ | EP-SC-111A | SP-15 | `GET` | `/channel/loyalty/rewards` | `sc.loyalty.manage` | Loyalty | المكافآت |
| ✅ | EP-SC-111B | SP-15 | `POST` | `/channel/loyalty/rewards` | `sc.loyalty.manage` | Loyalty | إنشاء مكافأة |
| ✅ | EP-SC-111C | SP-15 | `PUT` | `/channel/loyalty/rewards/{id}` | `sc.loyalty.manage` | Loyalty | تعديل مكافأة |
| ✅ | EP-SC-111D | SP-15 | `PATCH` | `/channel/loyalty/rewards/{id}/stop` | `sc.loyalty.manage` | Loyalty | إيقاف مكافأة |

## لوحة القيادة والتقارير — 4/4

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-120 | SP-16 | `GET` | `/channel/dashboard` | `sc.dashboard.view` | Reporting | لوحة القناة |
| ✅ | EP-SC-121 | SP-16 | `GET` | `/channel/reports/{type}` | `sc.reports.view` | Reporting | تقرير القناة |
| ✅ | EP-SC-122 | SP-16 | `POST` | `/channel/reports/{type}/export` | `sc.reports.export` | Reporting | تصدير تقرير |
| ✅ | EP-SC-123 | SP-16 | `GET` | `/channel/reports/margins` | `sc.reports.margins` | Reporting | تقرير الهوامش |

## إعدادات القناة والمناطق — 7/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-131A | SP-03 | `GET` | `/channel/zones` | `sc.zones.view` | Reference | مناطق تغطية القناة |
| ✅ | EP-SC-131B | SP-03 | `POST` | `/channel/zones` | `sc.zones.manage` | Reference | إضافة/تحديث تغطية منطقة |
| ✅ | EP-SC-131C | SP-03 | `DELETE` | `/channel/zones/{id}` | `sc.zones.manage` | Reference | حذف تغطية منطقة |
| ✅ | EP-SC-131D | SP-03 | `GET` | `/channel/zones/coverage` | `sc.zones.view` | Identity | فجوات تغطية المناطق |
| ✅ | EP-SC-163 | SP-03 | `GET` | `/channel/zones/map` | `sc.zones.view` | Reference | خريطة المناطق بالمضلعات |
| ✅ | EP-SC-130A | SP-15 | `GET` | `/channel` | `sc.settings.view` | Tenancy | إعدادات القناة |
| ✅ | EP-SC-130B | SP-15 | `PUT` | `/channel` | `sc.settings.update` | Tenancy | تحديث إعدادات القناة |

## أخرى — 12/12

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-SC-161 | SP-03 | `GET` | `/channel/delivery-calendar` | `sc.zones.view` | Reference | تقويم التوصيل الأسبوعي |
| ✅ | EP-SC-021 | SP-06 | `POST` | `/channel/media/upload` | `sc.catalog.create` | Catalog | رفع وسائط |
| ✅ | EP-SC-141 | SP-09 | `GET` | `/channel/warehouses` | `sc.inventory.view` | Tenancy | مستودعات القناة |
| ✅ | EP-SC-142A | SP-13 | `GET` | `/channel/retailer-groups` | `sc.retailers.groups` | Identity | مجموعات التجار |
| ✅ | EP-SC-142B | SP-13 | `POST` | `/channel/retailer-groups` | `sc.retailers.groups` | Identity | إنشاء مجموعة تجار |
| ✅ | EP-SC-142C | SP-13 | `PUT` | `/channel/retailer-groups/{id}` | `sc.retailers.groups` | Identity | تعديل مجموعة تجار |
| ✅ | EP-SC-142D | SP-13 | `DELETE` | `/channel/retailer-groups/{id}` | `sc.retailers.groups` | Identity | حذف مجموعة تجار |
| ✅ | EP-SC-165 | SP-13 | `GET` | `/channel/credit-approvals` | `sc.retailers.credit` | Finance | طابور موافقة الائتمان |
| ✅ | EP-SC-165A | SP-13 | `POST` | `/channel/credit-approvals/{id}/decide` | `sc.retailers.credit` | Finance | قرار موافقة ائتمان |
| ✅ | EP-SC-150A | SP-15 | `GET` | `/channel/users` | `sc.iam.users_view` | Identity | مستخدمو القناة |
| ✅ | EP-SC-150B | SP-15 | `POST` | `/channel/users/invite` | `sc.iam.users_manage` | Identity | دعوة مستخدم قناة |
| ✅ | EP-SC-124 | SP-16 | `GET` | `/channel/jobs/{id}` | `sc.reports.view` | Reporting | حالة مهمة |

