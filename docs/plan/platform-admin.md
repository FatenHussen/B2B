# خطة إكمال لوحة إدارة المنصة (السنترال)

الحالة الحيّة في [`../status/01-platform-admin.md`](../status/01-platform-admin.md) (مولَّدة). هذا الملف كان ترتيب العمل المتبقي؛ **كل تذاكر PA-01…PA-18 منجزة** (2026-09-23). العقد لكل مسار (`b` / `r` / `e`) في `docs/api/catalog/`.

قاعدة الترتيب: ما يكمل شاشة قائمة قبل ما يفتح شاشة جديدة؛ ما لا يعتمد على وحدة فارغة قبل ما يعتمد.

| # | التذكرة | مسارات | الوحدة | يعتمد على | الحالة |
|---|---|---|---|---|---|
| PA-01 | مطابقة العقد: نقل القنوات من `/admin/` إلى `/platform/` | 7 | Tenancy | — | ✅ منجز 2026-09-19 |
| PA-02 | الباقات: CRUD كامل | 4 | Tenancy | — | ✅ منجز 2026-09-23 |
| PA-03 | بطاقة القناة: المستخدمون، المستودعات، التغطية | 4 | Tenancy + Identity + Reference | PA-01 | ✅ منجز 2026-09-23 |
| PA-04 | طلبات انضمام القنوات | 2 | Tenancy | PA-01 | ✅ منجز 2026-09-23 |
| PA-05 | إعادة تعيين مدير القناة | 1 | Identity | PA-01 | ✅ منجز 2026-09-23 |
| PA-06 | الاشتراكات وتعيين الباقة والتغيير الجماعي | 4 | PlatformBilling + Tenancy | PA-02 | ✅ منجز 2026-09-23 |
| PA-07 | فواتير المنصة والإعفاء والمذكرة الدائنة والتحصيل المتعثر والإيراد | 5 | PlatformBilling | PA-06 | ✅ منجز 2026-09-23 |
| PA-08 | مفاتيح الميزات وتجاوزها وميزات القناة | 5 | Tenancy | — | ✅ منجز 2026-09-23 |
| PA-09 | نسخ التطبيقات وإعداد التطبيق العام | 4 | Content | PA-08 | ✅ منجز 2026-09-23 |
| PA-10 | الفريق والدعوات | 6 | Identity | — | ✅ منجز 2026-09-23 |
| PA-11 | الإعدادات: الملف، الأمان، النسخ، افتراضات القنوات، التكاملات | 11 | Core + Integration + Tenancy | — | ✅ منجز 2026-09-23 |
| PA-12 | المحتوى العام: الشروط، الانترو، الأدلة | 6 | Content | — | ✅ منجز 2026-09-23 |
| PA-13 | إشعارات المنصة والحملات والبث | 9 | Notification | — | ✅ منجز 2026-09-23 |
| PA-14 | الدعم: البحث، بطاقة 360، الانتحال، الجلسات، التذاكر | 10 | Support | PA-10 | ✅ منجز 2026-09-23 |
| PA-15 | صحة النظام والصيانة والمهام | 11 | Integration | — | ✅ منجز 2026-09-23 |
| PA-16 | لوحة القيادة والتقارير والتصدير | 8 | Reporting | PA-06 | ✅ منجز 2026-09-23 |
| PA-17 | تصدير القنوات (فردي وجماعي) | 2 | Reporting | PA-16 | ✅ منجز 2026-09-23 |
| PA-18 | حذف قناة بموافقة مزدوجة | 1 | Tenancy + Access | PA-01 | ✅ منجز 2026-09-23 |

---

## PA-01 — مطابقة العقد: `/admin/channels/*` → `/platform/channels/*` ✅

الكتالوج يسمّي `/platform/channels`؛ سبعة مسارات كانت تُخدَم على `/admin/channels` (EP-AD-050, 051, 052, 053, 054, 058, 062) بينما EP-AD-055/056 على المسار الصحيح. الحارس واحد للبادئتين (GuardTest)، فالنقل تغيير مسار لا تغيير سلوك.

- **وحدة:** Tenancy — `routes/api.php` فقط، والاختبارات التي تذكر المسار القديم.
- **قبول:** الأسطر السبعة تظهر ✅ في `status/01`؛ لا مسار حيّ تحت `/admin/`؛ الحزمة كاملة خضراء.
- **عقد:** تغيير مسار — يُذكر في وصف PR، والواجهة تستبدل ثابت `/admin/channels` بـ `/platform/channels`.

## PA-02 — الباقات: EP-AD-100A/B/C/D

`channel_plans` موجود (key, name, limits, is_active) ويغذّي `ChannelLimitResolver`. الكتالوج يطلب: `price_monthly`, `price_yearly`, `currency_id`, `limits.otp_monthly`, `features[]`, `on_exceed` (warn|block|manual_approval), `trial_days`, `is_public`, `status`.

- **وحدة:** Tenancy (الباقة تغذّي حدود القناة؛ Tenancy أساس ولا يجوز أن تعتمد على PlatformBilling).
- **جداول:** `channel_plans` — إضافة الأعمدة أعلاه؛ `status` محروس (القاعدة 8) ويتغيّر عبر `PlanLifecycle`.
- **صلاحية:** `ad.billing.plans` (مبذورة).
- **قبول:** إنشاء باقة ثم قراءتها تُعيد الشكل في `r`؛ تعديل حدود الباقة **لا** يغيّر حدود قناة قائمة عليها (`channel_limits` يُكتب من الباقة عند التجهيز — `d` في EP-AD-100D) لكن قناة تُنشأ بعده تأخذ الحدود الجديدة؛ `key` فريد → 422؛ الأسعار أعداد صحيحة (القاعدة 7)؛ `reason` إلزامي في التعديل ومسجَّل في `AuditLog`.

## PA-03 — بطاقة القناة: EP-AD-063, 065A, 065B, 066

- **063 المستخدمون:** Identity يملك `channel_users`؛ Tenancy لا يستورد نموذجه (القاعدة 1). عقد جديد في Core: `ChannelUserDirectory::listForChannel(int $channelId): array` تنفّذه Identity، وتقرأه Tenancy في `ShowChannelUsers`.
- **066 المستودعات:** `Warehouse` في Tenancy — قراءة مباشرة، `served_zone_ids` من `warehouse_zones` إن وُجد وإلا `[]` مع ذكر ذلك في الملاحظة.
- **065A/B التغطية:** `ChannelGovernorate` في Tenancy و`channel_zone` في Reference (`ChannelZoneLookup` قراءة فقط). الكتابة عبر عقد Core `ChannelCoverageWriter::replace(int $channelId, array $zoneIds)` تنفّذه Reference. `overlaps` من `ChannelZoneLookup` عبر القنوات (اسم السبب بجانب `acrossChannels()` — القاعدة 10)؛ `has_rep` من عقد `RepDirectory` الموجود.
- **قبول:** أربعة أسطر ✅؛ تحديث التغطية بلا `reason` → 422؛ التغطية لقناة غير موجودة → 404.

## PA-04 — طلبات الانضمام: EP-AD-060, 061

- **جدول جديد:** `channel_applications` (name, legal_form, cr_number, documents json, contact, status: under_review|provisioning|rejected, decided_by, decided_at, reason).
- **061 approve** ينادي `CreateChannel` الموجود بحالة `provisioning` ويعيد `channel_id`؛ **reject** يسجّل السبب. قرار على طلب مبتوت → 409 `illegal_transition`.
- **مصدر الطلب:** لا يوجد مسار عام لتقديم طلب انضمام في الكتالوج؛ يُدخل من لوحة المنصة نفسها (EP-AD-051) أو يُبذر. لا يُخترع مسار.

## PA-05 — إعادة تعيين مدير القناة: EP-AD-064

- Identity يملك دعوة المدير (`ChannelManagerInvite` من BE-T07). الإجراء: إبطال دعوات المدير المفتوحة وجلساته، إنشاء دعوة جديدة صالحة 72 ساعة تُستهلك مرة، والرد `{invite_id, expires_at}`. `reason` إلزامي ومدقَّق.
- **مسار:** تحت `/platform/channels/{id}/manager/reset` لكن المتحكم في Identity (الحارس من البادئة، الوحدة من الملكية).

## PA-06 — الاشتراكات: EP-AD-101, 102, 059A, 059B

- **وحدة:** PlatformBilling (Domain) ← Tenancy عبر عقد `ChannelDirectory` الموجود + عقد جديد `PlanDirectory` تنفّذه Tenancy.
- **جدول:** `channel_subscriptions` (channel_id, plan_id, cycle, status: trial|active|past_due|cancelled|scheduled, starts_at, next_renewal_at, amount, scheduled_plan_id, effective_from). المبالغ `bigInteger`.
- **102 assign plan** بتاريخ سريان مستقبلي → `status: scheduled`؛ فوري → يبدّل `plan_id` على القناة عبر عقد Tenancy (`ChannelPlanAssigner`) ليتحرّك `ChannelLimitResolver`.
- **059A preview** يحسب MRR الحالي/الجديد من أسعار الباقات؛ **059B** يطبّق على ≤ 50 قناة ويعيد `updated[]`.
- **حالة الاشتراك** تظهر أيضاً في EP-AD-050 (`subscription_status`) وEP-AD-052 (`subscription`) — تُقرأ عبر عقد Core `SubscriptionDirectory` تنفّذه PlatformBilling وتستهلكه Tenancy… **ممنوع** (أساس يعتمد على مجال). البديل الصحيح: PlatformBilling يبثّ `SubscriptionChanged` وTenancy يحتفظ بنسخة مسطّحة `subscription_status` على `supply_channels` (القاعدة 5: الحدث يُعلم ولا يتحكم).

## PA-07 — فواتير المنصة: EP-AD-103, 104, 106, 105, 107

- **جداول:** `platform_invoices` (no, channel_id, subscription_id, amount, issued_at, due_at, status: open|paid|overdue|waived|credited, pdf_path), `platform_credit_notes`.
- **104 waive** حرج + موافقة مزدوجة (`meta.requires_dual_approval` عبر آلية Access الموجودة لطلبات الاعتماد) + SOD-07 (من يمنح الإعفاء لا يصدر الفاتورة).
- **105 dunning** مشتق: الفواتير `overdue` مع أيام التأخير والتذكيرات المرسلة و`next_action`.
- **107 revenue** 12 شهراً: جديد/متسرّب/توزيع بالباقة — من `platform_invoices` لا من لقطة.
- **PDF:** قالب تحت `PlatformBilling/Presentation/Pdf/` (الاستثناء الوحيد المسموح لقالب).

## PA-08 — مفاتيح الميزات: EP-AD-110A, 110B, 111, 114, 067

- **وحدة:** Tenancy (الأسبقية: تجاوز يدوي ← الباقة ← عالمي — الثلاثة أصولها في Tenancy).
- **جداول:** `feature_flags` (key, description, enabled_globally, rollout_percent, scopes json, planned_removal_at), `feature_flag_overrides` (feature_key, channel_id, enabled, reason, actor).
- عقد Core `FeatureFlags::forChannel(int $channelId): array` و`FeatureFlags::forApp(string $scope): array` تستهلكهما Content (app-config) وأي وحدة أعلى.
- `rollout_percent` حتمي: `crc32(key.channel_id) % 100 < percent`.

## PA-09 — نسخ التطبيقات: EP-AD-112, 113, 115, EP-PB-010

- **وحدة:** Content. جدول `app_versions` (app, platform, version, build, min_supported, release_notes, rollout, store_url, force_update).
- **113 force-update** حرج + مزدوج؛ يقلب `force_update` ويغيّر ما يعيده `/public/app-config`.
- **PB-010 app-config** عام بلا حارس: `min_supported_version`, `latest_version`, `store_url`, `force_update`, `maintenance{}` (من PA-15), `feature_flags{}` (من PA-08 عبر العقد), `release_notes`. يُستدعى عند كل إقلاع؛ يُخزَّن 60 ثانية.
- الميدلوير الموجود لـ `X-App-Version` (426 `upgrade_required`) يقرأ `min_supported` من هذا الجدول بدل الإعداد.

## PA-10 — الفريق: EP-AD-154, 155, 156, 157, 160A, 160B

- **وحدة:** Identity (`platform_users`) + Access للأدوار.
- **جدول:** `platform_invites` (email, role_ids, token_hash, expires_at 72h, accepted_at, invited_by).
- **BR-AD-05/06:** لا يسحب المستخدم دوره الأخير من نفسه؛ آخر `platform_admin` نشط لا يُعطَّل ولا يُحذف → 409 `illegal_transition`.
- **160B** حرج + مزدوج + تأكيد كلمة المرور (الميدلوير `RequirePasswordConfirmation` موجود).

## PA-11 — الإعدادات: EP-AD-150A/B, 158A/B, 139A/B, 153A/B, 151A/B, 152

- **جدول واحد:** `platform_settings` (group, key, value json, updated_by) في Core — الملف والأمان والنسخ وافتراضات القنوات مفاتيح فيه.
- **151/152 التكاملات** في Integration: الأسرار تُكتب ولا تُقرأ (`***` في الرد)، و`test` ينفّذ فحص اتصال فعلي للمزوّد (واتساب/SMS/تخزين/خرائط/دفع).
- **158B الأمان** حرج: سياسة كلمة المرور، إلزام 2FA للأدوار، مهلة الجلسة.

## PA-12 — المحتوى العام: EP-AD-140A/B, 141A/B, 142A/B

- **وحدة:** Content. `legal_documents` مؤرَّخة بنسخ (type: terms|privacy, version, body_ar, published_at) — النشر يخلق نسخة ولا يعدّل القديمة؛ `platform_intros` (افتراضي تُرجعه القناة إن لم تخصّص) ؛ `help_guides` (audience, title, body, order).
- **141A/B الانترو:** ✅ منجز 2026-09-19 — `GET` يعيد الشكل الكامل (كائن واحد، صف شاغر = `enabled:false`)؛ `PUT` يعيد `{enabled}` فقط. صلاحيتان: `ad.content.view` / `ad.content.manage`. المخزن منفصل عن انترو القناة.

## PA-13 — إشعارات المنصة: EP-AD-080, 081, 082, 083A/B, 084, 085A/B, 068

- **وحدة:** Notification. جداول `platform_campaigns` (title, body, targeting json, channels json, scheduled_at, status: draft|pending_approval|queued|sent|failed, stats json), `platform_notification_templates`.
- **080 broadcast** حرج + مزدوج → `pending_approval` ثم تُجدوَل عند الاعتماد. **082 preview** يعيد `estimated_recipients` من عقود Identity (تجار/مندوبون/مستخدمو قناة). **068** يستهدف مديري ≤ 50 قناة.
- الإرسال الفعلي على طابور `default`؛ `in_app` يكتب في جدول إشعارات التطبيق المشترك (انظر `docs/plan/apps.md` — الإشعارات الموحّدة).

## PA-14 — الدعم: EP-AD-120 … 128

- **وحدة:** Support (Domain). البحث يمرّ عبر عقد Core `SupportSearchSource` تسجّل تنفيذه Identity (تجار/مندوبون) وOrdering (طلبات) وFinance (فواتير/وصولات) — الاعتماد من الوحدة الأعلى نحو Core، لا العكس.
- **122 impersonate:** توكن Sanctum على حارس `app` لمدة ≤ 15 دقيقة بقدرة `impersonation` (قراءة فقط ما لم `write_enabled`) — ميدلوير يرفض الكتابة بـ 403 `guest_write_blocked`؛ يُسجَّل في `AuditLog` بـ `impersonated=true`؛ يُشعَر صاحب الحساب.
- **125/128 التذاكر:** `support_tickets` بسبع حالات؛ الإغلاق يوجب `resolution` و`root_cause`.

## PA-15 — صحة النظام: EP-AD-130 … 138, 139C, 139D

- **وحدة:** Integration. 130 من Horizon/`jobs`+`failed_jobs`؛ 131 آخر 100 سطر استثناء من `failed_jobs` + سجل الأخطاء؛ 132 من جدول عمليات المزامنة (يبقى `pending_operations: 0` بصدق حتى تُبنى المزامنة — `docs/plan/apps.md`)؛ 133 نسب النجاح من `notification_delivery_logs` وسجل OTP؛ 134 يبدّل `OTP_CHANNEL` في `platform_settings` بلا نشر (يقرأه `CompositeOtpChannel`)؛ 136 الصيانة حرجة + مزدوجة وتُقرأ في `/public/app-config` وتعيد 503 `maintenance_mode` على المسارات الكاتبة.
- 139C/D النسخ: مهمة على طابور `reports` تسجّل النتيجة في `platform_settings`.

## PA-16 — لوحة القيادة والتقارير: EP-AD-090, 094, 095, 096, 091, 092, 093

- **وحدة:** Reporting. لقطة يومية للمنصة (`platform_daily_snapshots`) تُولَّد بـ `GenerateDailySnapshot` الموجود مع نطاق منصة؛ البطاقات من اللقطة وتحمل `snapshot_date`؛ الطوابير/الأخطاء/المزامنة لحظية وموسومة `live: true`.
- 091 `type ∈ gmv|adoption|operations|growth|quality`؛ 093 يخلق `ReportExport` (موجود) على طابور `reports`؛ 092 حالته و`download_url` صالح 24 ساعة (5 تصديرات/ساعة — 429).

## PA-17 — تصدير القنوات: EP-AD-057, 059C

- Reporting يعيد استخدام `ReportExport` نفسه؛ 057 يوجب تأكيد كلمة المرور (403 `requires_password_confirm`) ويسجّل التصدير نفسه في `AuditLog`.

## PA-18 — حذف قناة: EP-AD-058

- المسار حيّ لكنه `DELETE` مباشر خلف `role:platform_admin`. الكتالوج: مؤرشفة ≥ 30 يوماً، تأكيد كلمة مرور + OTP + كتابة الاسم + معتمِد ثانٍ (`ad.channels.delete` + طلب اعتماد في Access) ويعيد `deletion_request_id`. يُعاد بناؤه بعد PA-01 على الصلاحية لا الدور.

---

## ما ليس في هذه الخطة عمداً

- **`/channel/*` و`/warehouse/*`:** مكتملان بالكامل حسب الكتالوج (انظر `status/02`, `status/03`).
- **التطبيقات والمشترك:** في [`apps.md`](apps.md).
- **المسارات الحيّة خارج الكتالوج** (`/channel`, `/channel/zones`, `/{governorates|zones|currencies}` بلا بادئة، و`GET /platform/refs/*/{id}`): تُضاف إلى `docs/api/catalog/` بـ `b`/`r`/`e` في تذكرة عقد مستقلة، أو تُزال. الثلاثة بلا بادئة تخالف قاعدة "البادئة تسمّي الحارس" وتحتاج قراراً.
