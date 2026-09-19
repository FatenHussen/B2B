# لوحة إدارة المنصة (السنترال) — حالة الواجهات

guard `platform` · prefix `/api/v1/platform/*` · مولَّد آلياً في 2026-09-19 من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).

| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |
|---|---|---|---|---|
| 166 | 76 | 0 | 90 | 7 |

## الدخول والملف الشخصي — 16/16

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-AD-001 | SP-01 | `POST` | `/platform/auth/login` | — | Identity | دخول المنصة |
| ✅ | EP-AD-002 | SP-01 | `POST` | `/platform/auth/2fa/verify` | — | Identity | تحقق المصادقة الثنائية |
| ✅ | EP-AD-003 | SP-01 | `POST` | `/platform/auth/logout` | — | Identity | تسجيل الخروج |
| ✅ | EP-AD-004 | SP-01 | `GET` | `/platform/auth/me` | — | Identity | المستخدم الحالي |
| ✅ | EP-AD-005 | SP-01 | `POST` | `/platform/auth/confirm-password` | — | Identity | تأكيد كلمة المرور |
| ✅ | EP-AD-006 | SP-01 | `GET` | `/platform/auth/sessions` | — | Identity | جلسات الدخول |
| ✅ | EP-AD-007 | SP-01 | `DELETE` | `/platform/auth/sessions/{id}` | — | Identity | إنهاء جلسة |
| ✅ | EP-AD-159A | SP-01 | `GET` | `/platform/me` | — | Identity | ملفي الشخصي |
| ✅ | EP-AD-159B | SP-01 | `PUT` | `/platform/me` | — | Identity | تحديث ملفي |
| ✅ | EP-AD-159C | SP-01 | `PUT` | `/platform/me/password` | — | Identity | تغيير كلمة المرور |
| ✅ | EP-AD-159D | SP-01 | `POST` | `/platform/me/2fa/enable` | — | Identity | بدء تفعيل التحقق بخطوتين |
| ✅ | EP-AD-159E | SP-01 | `POST` | `/platform/me/2fa/confirm` | — | Identity | تأكيد التحقق بخطوتين |
| ✅ | EP-AD-159F | SP-01 | `GET` | `/platform/me/2fa/recovery-codes` | — | Identity | رموز الاسترداد |
| ✅ | EP-AD-159G | SP-01 | `GET` | `/platform/me/api-tokens` | — | Identity | رموز API |
| ✅ | EP-AD-159H | SP-01 | `POST` | `/platform/me/api-tokens` | — | Identity | إنشاء رمز API |
| ✅ | EP-AD-159I | SP-01 | `DELETE` | `/platform/me/api-tokens/{id}` | — | Identity | إبطال رمز API |

## الصلاحيات والتدقيق (IAM) — 20/20

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-AD-010 | SP-02 | `GET` | `/platform/iam/permissions` | `ad.iam.view_catalog` | Access | كتالوج الصلاحيات |
| ✅ | EP-AD-011 | SP-02 | `GET` | `/platform/iam/permissions/{code}/holders` | `ad.iam.view_catalog` | Access | حاملو الصلاحية |
| ✅ | EP-AD-012 | SP-02 | `GET` | `/platform/iam/roles` | `ad.iam.view_catalog` | Access | الأدوار |
| ✅ | EP-AD-013 | SP-02 | `POST` | `/platform/iam/roles` | `ad.iam.role_create` | Access | إنشاء دور |
| ✅ | EP-AD-014 | SP-02 | `POST` | `/platform/iam/roles/{id}/approve` | `ad.iam.role_approve` | Access | اعتماد الدور |
| ✅ | EP-AD-015 | SP-02 | `PUT` | `/platform/iam/roles/{id}/permissions` | `ad.iam.role_create` | Access | تحديث صلاحيات الدور |
| ✅ | EP-AD-016 | SP-02 | `POST` | `/platform/iam/assignments` | `ad.iam.role_assign` | Access | إسناد أدوار |
| ✅ | EP-AD-017 | SP-02 | `DELETE` | `/platform/iam/assignments` | `ad.iam.role_assign` | Access | سحب دور |
| ✅ | EP-AD-018 | SP-02 | `POST` | `/platform/iam/simulate` | `ad.iam.simulate` | Access | محاكاة صلاحية |
| ✅ | EP-AD-019 | SP-02 | `POST` | `/platform/iam/temp-grants` | `ad.iam.grant_temp` | Access | منح مؤقت |
| ✅ | EP-AD-020 | SP-02 | `POST` | `/platform/iam/temp-grants/{id}/approve` | `ad.iam.grant_temp` | Access | اعتماد المنح المؤقت |
| ✅ | EP-AD-021 | SP-02 | `GET` | `/platform/iam/sod-rules` | `ad.iam.sod_rules` | Access | قواعد فصل المهام |
| ✅ | EP-AD-022 | SP-02 | `GET` | `/platform/audit` | `ad.audit.view` | Access | سجل التدقيق |
| ✅ | EP-AD-023 | SP-02 | `POST` | `/platform/audit/export` | `ad.audit.export` | Access | تصدير التدقيق |
| ✅ | EP-AD-024 | SP-02 | `POST` | `/platform/iam/reviews` | `ad.audit.review` | Access | حملة مراجعة صلاحيات |
| ✅ | EP-AD-025 | SP-02 | `GET` | `/platform/iam/approval-requests` | `ad.iam.view_catalog` | Access | طلبات الاعتماد المزدوج |
| ✅ | EP-AD-026 | SP-02 | `POST` | `/platform/iam/approval-requests/{id}/decide` | `ad.iam.role_approve` | Access | بتّ طلب اعتماد |
| ✅ | EP-AD-027 | SP-02 | `POST` | `/platform/iam/roles/preview` | `ad.iam.view_catalog` | Access | معاينة أثر الدور |
| ✅ | EP-AD-028 | SP-02 | `GET` | `/platform/iam/reviews/{id}` | `ad.audit.review` | Access | حملة مراجعة الصلاحيات |
| ✅ | EP-AD-029 | SP-02 | `POST` | `/platform/iam/reviews/{id}/items/{itemId}/decide` | `ad.audit.review` | Access | تأكيد أو سحب بند المراجعة |

## المرجعيات — 31/31

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-AD-030 | SP-03 | `GET` | `/platform/refs/governorates` | `ad.refs.view` | Reference | المحافظات |
| ✅ | EP-AD-031 | SP-03 | `POST` | `/platform/refs/governorates` | `ad.refs.create` | Reference | إنشاء محافظة |
| ✅ | EP-AD-032 | SP-03 | `GET` | `/platform/refs/zones` | `ad.refs.view` | Reference | المناطق |
| ✅ | EP-AD-033 | SP-03 | `POST` | `/platform/refs/zones` | `ad.refs.create` | Reference | إنشاء منطقة |
| ✅ | EP-AD-034 | SP-03 | `PATCH` | `/platform/refs/zones/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل منطقة |
| ✅ | EP-AD-035A | SP-03 | `GET` | `/platform/refs/activity-types` | `ad.refs.view` | Reference | أنواع النشاط |
| ✅ | EP-AD-035B | SP-03 | `POST` | `/platform/refs/activity-types` | `ad.refs.create` | Reference | إنشاء نوع نشاط |
| ✅ | EP-AD-036A | SP-03 | `GET` | `/platform/refs/root-categories` | `ad.refs.view` | Reference | الفئات الجذر |
| ✅ | EP-AD-036B | SP-03 | `POST` | `/platform/refs/root-categories` | `ad.refs.create` | Reference | إنشاء فئة جذر |
| ✅ | EP-AD-037A | SP-03 | `GET` | `/platform/refs/sale-units` | `ad.refs.view` | Reference | وحدات البيع |
| ✅ | EP-AD-037B | SP-03 | `POST` | `/platform/refs/sale-units` | `ad.refs.create` | Reference | إنشاء وحدة بيع |
| ✅ | EP-AD-038A | SP-03 | `GET` | `/platform/refs/equipments` | `ad.refs.view` | Reference | التجهيزات |
| ✅ | EP-AD-038B | SP-03 | `POST` | `/platform/refs/equipments` | `ad.refs.create` | Reference | إنشاء تجهيز |
| ✅ | EP-AD-039A | SP-03 | `GET` | `/platform/refs/currencies` | `ad.refs.currency` | Reference | العملات |
| ✅ | EP-AD-039B | SP-03 | `POST` | `/platform/refs/currencies` | `ad.refs.currency` | Reference | إنشاء عملة |
| ✅ | EP-AD-040 | SP-03 | `POST` | `/platform/refs/fx-rates` | `ad.refs.currency` | Reference | سعر صرف |
| ✅ | EP-AD-041 | SP-03 | `POST` | `/platform/refs/import` | `ad.refs.import` | Reference | استيراد مرجعيات |
| ✅ | EP-AD-042A | SP-03 | `PUT` | `/platform/refs/governorates/{id}` | `ad.refs.update` | Reference | تعديل محافظة |
| ✅ | EP-AD-042B | SP-03 | `PUT` | `/platform/refs/zones/{id}` | `ad.refs.update` | Reference | تعديل منطقة |
| ✅ | EP-AD-042C | SP-03 | `PUT` | `/platform/refs/activity-types/{id}` | `ad.refs.update` | Reference | تعديل نوع نشاط |
| ✅ | EP-AD-042D | SP-03 | `PUT` | `/platform/refs/root-categories/{id}` | `ad.refs.update` | Reference | تعديل فئة جذر |
| ✅ | EP-AD-042E | SP-03 | `PUT` | `/platform/refs/sale-units/{id}` | `ad.refs.update` | Reference | تعديل وحدة بيع |
| ✅ | EP-AD-042F | SP-03 | `PUT` | `/platform/refs/equipments/{id}` | `ad.refs.update` | Reference | تعديل تجهيز |
| ✅ | EP-AD-042G | SP-03 | `PUT` | `/platform/refs/currencies/{id}` | `ad.refs.currency` | Reference | تعديل عملة |
| ✅ | EP-AD-043A | SP-03 | `PATCH` | `/platform/refs/governorates/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل محافظة |
| ✅ | EP-AD-043B | SP-03 | `PATCH` | `/platform/refs/activity-types/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل نوع نشاط |
| ✅ | EP-AD-043C | SP-03 | `PATCH` | `/platform/refs/root-categories/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل فئة جذر |
| ✅ | EP-AD-043D | SP-03 | `PATCH` | `/platform/refs/sale-units/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل وحدة بيع |
| ✅ | EP-AD-043E | SP-03 | `PATCH` | `/platform/refs/equipments/{id}/status` | `ad.refs.disable` | Reference | تعطيل/تفعيل تجهيز |
| ✅ | EP-AD-043F | SP-03 | `PATCH` | `/platform/refs/currencies/{id}/status` | `ad.refs.currency` | Reference | تعطيل/تفعيل عملة |
| ✅ | EP-AD-043G | SP-03 | `GET` | `/platform/refs/fx-rates` | `ad.refs.currency` | Reference | أسعار الصرف |

## القنوات — 9/23

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ✅ | EP-AD-050 | SP-04 | `GET` | `/platform/channels` | `ad.channels.view` | Tenancy | قنوات التوريد |
| ✅ | EP-AD-051 | SP-04 | `POST` | `/platform/channels` | `ad.channels.create` | Tenancy | إنشاء قناة |
| ✅ | EP-AD-052 | SP-04 | `GET` | `/platform/channels/{id}` | `ad.channels.view` | Tenancy | تفاصيل القناة |
| ✅ | EP-AD-053 | SP-04 | `POST` | `/platform/channels/{id}/retry-provisioning` | `ad.channels.update` | Tenancy | إعادة التجهيز |
| ✅ | EP-AD-054 | SP-04 | `POST` | `/platform/channels/{id}/transition` | `ad.channels.suspend` | Tenancy | تغيير حالة القناة |
| ✅ | EP-AD-055 | SP-04 | `PUT` | `/platform/channels/{id}/limits` | `ad.billing.assign_plan` | Tenancy | حدود القناة |
| ✅ | EP-AD-056 | SP-04 | `GET` | `/platform/channels/{id}/usage` | `ad.channels.view` | Tenancy | استخدام القناة |
| ❌ | EP-AD-057 | SP-04 | `POST` | `/platform/channels/{id}/export` | `ad.channels.export` | — | تصدير بيانات القناة |
| ✅ | EP-AD-058 | SP-04 | `DELETE` | `/platform/channels/{id}` | `ad.channels.delete` | Tenancy | حذف قناة |
| ❌ | EP-AD-059A | SP-04 | `POST` | `/platform/channels/bulk-plan/preview` | `ad.billing.assign_plan` | — | معاينة تغيير الباقة الجماعي |
| ❌ | EP-AD-059B | SP-04 | `POST` | `/platform/channels/bulk-plan` | `ad.billing.assign_plan` | — | تطبيق تغيير الباقة الجماعي |
| ❌ | EP-AD-059C | SP-04 | `POST` | `/platform/channels/export` | `ad.channels.export` | — | تصدير قائمة القنوات |
| ❌ | EP-AD-060 | SP-04 | `GET` | `/platform/channel-applications` | `ad.channels.view` | — | طلبات انضمام القنوات |
| ❌ | EP-AD-061 | SP-04 | `POST` | `/platform/channel-applications/{id}/decide` | `ad.channels.create` | — | بتّ طلب انضمام قناة |
| ✅ | EP-AD-062 | SP-04 | `PUT` | `/platform/channels/{id}` | `ad.channels.update` | Tenancy | تعديل بيانات القناة |
| ❌ | EP-AD-063 | SP-04 | `GET` | `/platform/channels/{id}/users` | `ad.channels.view` | — | مستخدمو القناة وأدوارهم |
| ❌ | EP-AD-064 | SP-04 | `POST` | `/platform/channels/{id}/manager/reset` | `ad.channels.update` | — | إعادة تعيين حساب مدير القناة |
| ❌ | EP-AD-065A | SP-04 | `GET` | `/platform/channels/{id}/coverage` | `ad.channels.view` | — | المناطق والتغطية |
| ❌ | EP-AD-065B | SP-04 | `PUT` | `/platform/channels/{id}/coverage` | `ad.channels.update` | — | تحديث تغطية القناة |
| ❌ | EP-AD-066 | SP-04 | `GET` | `/platform/channels/{id}/warehouses` | `ad.channels.view` | — | مستودعات القناة |
| ❌ | EP-AD-067 | SP-04 | `GET` | `/platform/channels/{id}/features` | `ad.features.view` | — | ميزات هذه القناة |
| ❌ | EP-AD-068 | SP-04 | `POST` | `/platform/channels/notify-managers` | `ad.notify.send` | — | إشعار مديري القنوات |
| ❌ | EP-AD-102 | SP-17 | `POST` | `/platform/channels/{id}/plan` | `ad.billing.assign_plan` | — | تعيين خطة |

## الفوترة والباقات — 0/10

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-100A | SP-17 | `GET` | `/platform/plans` | `ad.billing.plans` | — | الخطط |
| ❌ | EP-AD-100B | SP-17 | `POST` | `/platform/plans` | `ad.billing.plans` | — | إنشاء خطة |
| ❌ | EP-AD-100C | SP-17 | `GET` | `/platform/plans/{id}` | `ad.billing.plans` | — | تفاصيل الباقة |
| ❌ | EP-AD-100D | SP-17 | `PUT` | `/platform/plans/{id}` | `ad.billing.plans` | — | تعديل باقة |
| ❌ | EP-AD-101 | SP-17 | `GET` | `/platform/subscriptions` | `ad.billing.view` | — | الاشتراكات |
| ❌ | EP-AD-103 | SP-17 | `GET` | `/platform/platform-invoices` | `ad.billing.view` | — | فواتير المنصة |
| ❌ | EP-AD-104 | SP-17 | `POST` | `/platform/platform-invoices/{id}/waive` | `ad.billing.waive` | — | إعفاء فاتورة منصة |
| ❌ | EP-AD-105 | SP-17 | `GET` | `/platform/dunning` | `ad.billing.dunning` | — | تحصيل المتأخرات |
| ❌ | EP-AD-106 | SP-17 | `POST` | `/platform/platform-invoices/{id}/credit-note` | `ad.billing.invoice` | — | مذكرة دائنة لفاتورة منصة |
| ❌ | EP-AD-107 | SP-17 | `GET` | `/platform/billing/revenue` | `ad.billing.view` | — | تقرير إيراد المنصة |

## الميزات ونسخ التطبيقات — 0/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-110A | SP-17 | `GET` | `/platform/features` | `ad.features.view` | — | مفاتيح الميزات |
| ❌ | EP-AD-110B | SP-17 | `POST` | `/platform/features` | `ad.features.manage` | — | إنشاء مفتاح ميزة |
| ❌ | EP-AD-111 | SP-17 | `POST` | `/platform/features/{key}/override` | `ad.features.override` | — | تجاوز ميزة لقناة |
| ❌ | EP-AD-112 | SP-17 | `POST` | `/platform/app-versions` | `ad.content.publish_version` | — | نشر نسخة تطبيق |
| ❌ | EP-AD-113 | SP-17 | `POST` | `/platform/app-versions/{id}/force-update` | `ad.content.force_update` | — | فرض التحديث |
| ❌ | EP-AD-114 | SP-17 | `DELETE` | `/platform/features/{key}/override` | `ad.features.override` | — | إعادة الميزة لقيمة الباقة |
| ❌ | EP-AD-115 | SP-17 | `GET` | `/platform/app-versions` | `ad.content.view` | — | سجل إصدارات التطبيقات |

## الإشعارات — 0/8

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-080 | SP-14 | `POST` | `/platform/notifications/broadcast` | `ad.notify.broadcast` | — | بث إشعار المنصة |
| ❌ | EP-AD-081 | SP-14 | `POST` | `/platform/notifications` | `ad.notify.send` | — | إنشاء إشعار منصة |
| ❌ | EP-AD-082 | SP-14 | `POST` | `/platform/notifications/preview` | `ad.notify.send` | — | تقييم استهداف الإشعار |
| ❌ | EP-AD-083A | SP-14 | `GET` | `/platform/notifications/templates` | `ad.notify.view` | — | قوالب إشعارات المنصة |
| ❌ | EP-AD-083B | SP-14 | `PUT` | `/platform/notifications/templates` | `ad.notify.send` | — | تحديث قالب منصة |
| ❌ | EP-AD-084 | SP-14 | `GET` | `/platform/notifications/log` | `ad.notify.view` | — | سجل إرسال المنصة |
| ❌ | EP-AD-085A | SP-14 | `GET` | `/platform/notifications/campaigns` | `ad.notify.view` | — | حملات الإشعارات |
| ❌ | EP-AD-085B | SP-14 | `GET` | `/platform/notifications/campaigns/{id}` | `ad.notify.view` | — | تفاصيل حملة |

## لوحة القيادة والتقارير — 0/7

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-090 | SP-16 | `GET` | `/platform/dashboard` | `ad.dashboard.view` | — | لوحة المنصة |
| ❌ | EP-AD-091 | SP-16 | `GET` | `/platform/reports/{type}` | `ad.reports.view` | — | تقرير المنصة |
| ❌ | EP-AD-092 | SP-16 | `GET` | `/platform/exports/{jobId}` | `ad.reports.view` | — | حالة التصدير |
| ❌ | EP-AD-093 | SP-16 | `POST` | `/platform/reports/{type}/export` | `ad.reports.export` | — | تصدير تقرير المنصة |
| ❌ | EP-AD-094 | SP-16 | `GET` | `/platform/dashboard/alerts` | `ad.dashboard.view` | — | تنبيهات المنصة اللحظية |
| ❌ | EP-AD-095 | SP-16 | `GET` | `/platform/dashboard/cards/{key}` | `ad.dashboard.view` | — | بطاقة لوحة مستقلة |
| ❌ | EP-AD-096 | SP-16 | `GET` | `/platform/dashboard/charts/{key}` | `ad.dashboard.view` | — | رسم لوحة القيادة |

## الدعم — 0/10

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-120 | SP-17 | `GET` | `/platform/support/search` | `ad.support.search` | — | بحث الدعم |
| ❌ | EP-AD-121 | SP-17 | `GET` | `/platform/support/users/{type}/{id}` | `ad.support.view_profile` | — | بطاقة المستخدم 360 |
| ❌ | EP-AD-122 | SP-17 | `POST` | `/platform/support/impersonate` | `ad.support.impersonate` | — | انتحال جلسة دعم |
| ❌ | EP-AD-123 | SP-17 | `POST` | `/platform/support/users/{id}/resend-otp` | `ad.support.resend_otp` | — | إعادة OTP لمستخدم |
| ❌ | EP-AD-124 | SP-17 | `POST` | `/platform/support/users/{id}/revoke-sessions` | `ad.support.revoke_sessions` | — | إنهاء جلسات المستخدم |
| ❌ | EP-AD-125A | SP-17 | `GET` | `/platform/support/tickets` | `ad.support.tickets` | — | التذاكر |
| ❌ | EP-AD-125B | SP-17 | `POST` | `/platform/support/tickets` | `ad.support.tickets` | — | إنشاء تذكرة |
| ❌ | EP-AD-126 | SP-17 | `POST` | `/platform/support/users/{id}/disable` | `ad.support.disable_user` | — | تعطيل مستخدم مؤقت |
| ❌ | EP-AD-127 | SP-17 | `POST` | `/platform/support/users/{id}/reset-device` | `ad.support.revoke_sessions` | — | إعادة تعيين جهاز المستخدم |
| ❌ | EP-AD-128 | SP-17 | `PATCH` | `/platform/support/tickets/{id}` | `ad.support.tickets` | — | تحديث تذكرة |

## صحة النظام — 0/11

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-130 | SP-17 | `GET` | `/platform/system/queues` | `ad.system.view` | — | طوابير النظام |
| ❌ | EP-AD-131 | SP-17 | `GET` | `/platform/system/errors` | `ad.system.view` | — | آخر الاستثناءات |
| ❌ | EP-AD-132 | SP-17 | `GET` | `/platform/system/sync` | `ad.system.view` | — | صحة المزامنة |
| ❌ | EP-AD-133 | SP-17 | `GET` | `/platform/system/integrations` | `ad.system.view` | — | صحة التكاملات |
| ❌ | EP-AD-134 | SP-17 | `POST` | `/platform/system/switch-otp-channel` | `ad.system.switch_otp` | — | تبديل قناة OTP |
| ❌ | EP-AD-135 | SP-17 | `POST` | `/platform/system/retry-jobs` | `ad.system.retry_jobs` | — | إعادة مهام فاشلة |
| ❌ | EP-AD-136 | SP-17 | `POST` | `/platform/system/maintenance` | `ad.system.maintenance` | — | وضع الصيانة |
| ❌ | EP-AD-137 | SP-17 | `GET` | `/platform/system/scheduled-jobs` | `ad.system.view` | — | المهام المجدولة |
| ❌ | EP-AD-138 | SP-17 | `GET` | `/platform/system/storage` | `ad.system.view` | — | التخزين والنسخ |
| ❌ | EP-AD-139C | SP-17 | `POST` | `/platform/system/backups` | `ad.system.backup` | — | تشغيل نسخة احتياطية |
| ❌ | EP-AD-139D | SP-17 | `POST` | `/platform/system/backups/restore-test` | `ad.system.backup` | — | اختبار الاستعادة |

## المحتوى — 0/6

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-140A | SP-17 | `GET` | `/platform/content/legal` | `ad.content.view` | — | الشروط والخصوصية |
| ❌ | EP-AD-140B | SP-17 | `POST` | `/platform/content/legal` | `ad.content.manage` | — | نشر نسخة شروط/خصوصية |
| ❌ | EP-AD-141A | SP-17 | `GET` | `/platform/content/intro` | `ad.content.view` | — | الانترو الافتراضي للمنصة |
| ❌ | EP-AD-141B | SP-17 | `PUT` | `/platform/content/intro` | `ad.content.manage` | — | تحديث الانترو الافتراضي |
| ❌ | EP-AD-142A | SP-17 | `GET` | `/platform/content/help` | `ad.content.view` | — | الأدلة والمساعدة |
| ❌ | EP-AD-142B | SP-17 | `POST` | `/platform/content/help` | `ad.content.manage` | — | إنشاء مقال مساعدة |

## الإعدادات والفريق — 0/17

| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |
|---|---|---|---|---|---|---|---|
| ❌ | EP-AD-139A | SP-17 | `GET` | `/platform/settings/backup` | `ad.settings.view` | — | سياسة النسخ الاحتياطي |
| ❌ | EP-AD-139B | SP-17 | `PUT` | `/platform/settings/backup` | `ad.settings.update` | — | تحديث سياسة النسخ |
| ❌ | EP-AD-150A | SP-17 | `GET` | `/platform/settings/profile` | `ad.settings.view` | — | الملف العام للمنصة |
| ❌ | EP-AD-150B | SP-17 | `PUT` | `/platform/settings/profile` | `ad.settings.update` | — | تحديث الملف العام |
| ❌ | EP-AD-151A | SP-17 | `GET` | `/platform/settings/integrations` | `ad.settings.view` | — | مفاتيح التكاملات |
| ❌ | EP-AD-151B | SP-17 | `PUT` | `/platform/settings/integrations/{provider}` | `ad.settings.update` | — | حفظ مفتاح تكامل |
| ❌ | EP-AD-152 | SP-17 | `POST` | `/platform/settings/integrations/{provider}/test` | `ad.settings.update` | — | اختبار اتصال المزوّد |
| ❌ | EP-AD-153A | SP-17 | `GET` | `/platform/settings/channel-defaults` | `ad.settings.view` | — | التهيئة الافتراضية للقنوات |
| ❌ | EP-AD-153B | SP-17 | `PUT` | `/platform/settings/channel-defaults` | `ad.settings.update` | — | تحديث تهيئة القنوات |
| ❌ | EP-AD-154 | SP-17 | `GET` | `/platform/team` | `ad.team.view` | — | فريق المنصة |
| ❌ | EP-AD-155 | SP-17 | `POST` | `/platform/team/invites` | `ad.team.invite` | — | دعوة عضو للمنصة |
| ❌ | EP-AD-156 | SP-17 | `GET` | `/platform/team/invites` | `ad.team.view` | — | الدعوات المعلّقة |
| ❌ | EP-AD-157 | SP-17 | `POST` | `/platform/team/{id}/disable` | `ad.team.delete` | — | تعطيل عضو منصة |
| ❌ | EP-AD-158A | SP-17 | `GET` | `/platform/settings/security` | `ad.settings.security` | — | سياسة أمان المنصة |
| ❌ | EP-AD-158B | SP-17 | `PUT` | `/platform/settings/security` | `ad.settings.security` | — | تحديث سياسة الأمان |
| ❌ | EP-AD-160A | SP-17 | `PUT` | `/platform/team/{id}` | `ad.team.update` | — | تعديل عضو المنصة |
| ❌ | EP-AD-160B | SP-17 | `DELETE` | `/platform/team/{id}` | `ad.team.delete` | — | حذف عضو المنصة |

## حيّ خارج الكتالوج

مسارات تخدمها الشيفرة ولا يذكرها الكتالوج. إما تُضاف إلى `docs/api/catalog/` أو تُزال — لا ثالث.

| الطريقة | المسار | الصلاحية | الوحدة |
|---|---|---|---|
| `GET` | `/platform/refs/activity-types/{activityType}` | `ad.refs.view` | Reference |
| `GET` | `/platform/refs/currencies/{currency}` | `ad.refs.currency` | Reference |
| `GET` | `/platform/refs/equipments/{equipment}` | `ad.refs.view` | Reference |
| `GET` | `/platform/refs/governorates/{governorate}` | `ad.refs.view` | Reference |
| `GET` | `/platform/refs/root-categories/{rootCategory}` | `ad.refs.view` | Reference |
| `GET` | `/platform/refs/sale-units/{saleUnit}` | `ad.refs.view` | Reference |
| `GET` | `/platform/refs/zones/{zone}` | `ad.refs.view` | Reference |

