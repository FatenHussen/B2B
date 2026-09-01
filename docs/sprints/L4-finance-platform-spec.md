# طبقة 4 — توصيف بناء الباك (SP-13 … SP-18)

عقد تنفيذ. يُقرأ بعد L1–L3. قواعد الغلاف، الحراس، المال الصحيح، Idempotency، Action/Query، Deptrac، و**نمط Form Request / lang في L1 §0.1** سارية. المصدر الملزم: كل ملف تحت `docs/api/catalog/*.php` (`generate.php` يجمّع المجلد كاملاً). كل كتابة جديدة = `ApiFormRequest`؛ الرسائل من `lang/{ar,en}` لا من الكونترولر.

**النطاق:** مال تشغيلي، مزامنة أوفلاين، إشعارات، محتوى/ولاء، لوحات من لقطات يومية، فوترة المنصة والدعم وصحة النظام، ثم تصليب إطلاق بلا API منتج.

**شرط الدخول:** حلقة L3 مكتملة (فاتورة أدنى عند التسليم، `CreditGuard` كان يسمح الكل، `IssuesInvoice` كان رأساً). هذه الطبقة تملأ الدفاتر الحقيقية.

**تنبيه كتالوج:** الجدول أدناه هو الشريحة الأصلية (18+13+14+7+27+0). الملفات `10-platform-12e.php` و`11-platform-doc07.php` أضافت endpoints لنفس السبرنتات — **ملزمة** وتُنفَّذ في نفس السبرنت (ملحق §9). لا تؤجَّل إلى SP-18.

---

## 0. خريطة الموديولات

| موديول | Deptrac | سبرنت | ماذا يملك |
|---|---|---|---|
| `finance` | Coordination | 13 | فواتير قناة، دفعات، محافظ مناديب، كشف، سقف ائتمان، حجز وصل |
| `sync` | Coordination | 14 | pull/push/conflict بالـ cursor |
| `notification` | Domain | 14 | قوالب، طابور، صندوق تطبيق، بث، FCM |
| `content` | Domain | 15 (+17 قانوني/نسخ) | انترو، بنرات، سلايدر، home-blocks |
| `loyalty` | Domain | 15 | قواعد، نقاط، استبدال |
| `reporting` | Coordination | 16 | لقطات يومية، لوحات، تقارير، تصدير |
| `platform-billing` | Domain | 17 | خطط، اشتراكات، فواتير منصة، dunning — يستبدل `channel_plans` الخفيفة من SP-04 |
| `support` | Domain | 17 | بحث، بطاقة 360، انتحال، تذاكر |
| `core` + `integration` + `identity` | Foundation | 17–18 | flags، صيانة، OTP runtime، طوابير، فريق المنصة |

عقود تُكمَّل في `core`:

| عقد | كان في L3 | يصبح |
|---|---|---|
| `IssuesInvoice` | رأس أدنى | دفتر فواتير كامل + بنود |
| `CreditGuard` | سماح افتراضي | سقف + `on_exceed` |
| `ReceiptNumberReserver` | عداد | حجز قبل الدفع REQ-IN-01 |
| `FeatureFlags` | جديد | يقرأه `/app/session` و`/public/app-config` |
| `SyncCursorStore` | جديد | ADR-06 |

أحداث مالية تسمعها loyalty/notification/reporting: `PaymentPosted` · `InvoiceVoided` · `RepWalletSettled` · `CreditLimitChanged`.

---

## 1. SP-13 — مال تشغيلي (18 API)

موديول `finance`. SOD-01: حامل `sc.orders.confirm` لا يحمل `sc.finance.payment` (مرفوض 403 `sod_violation` عند التسجيل المكتبي). إشعار دائن وإلغاء فاتورة: dual + crit.

### 1.1 ثوابت لا تُكسر

```
AC-07  retailer.receivable == SUM(invoices.open+partial) − SUM(payments) − SUM(credit_notes)
       (void لا يدخل المجموع)
AC-06  rep.wallet.net_balance == SUM(collected) − SUM(settled)
REQ-IN-01  رقم الوصل يُحجز على الخادم قبل بدء التحصيل. تكرار receipt_no → 409 دائماً
```

كل المبالغ `BIGINT`. دفتر `ledger_entries` append-only (trigger منع UPDATE/DELETE كـ audit).

### 1.2 جداول

```
invoices              channel_id, retailer_id, sub_order_id unique nullable, no UNIQUE(channel,no),
                      status ∈ open|partial|paid|void, total, remaining, issued_at
invoice_lines         product_id, qty, unit_price, amount
credit_notes          invoice_id, no, amount, reason, status, dual request id
payments              channel_id, retailer_id, invoice_id nullable, amount, method ∈ cash|…,
                      receipt_no UNIQUE globally or per channel, source ∈ rep_app|retailer_app|office,
                      rep_id nullable, client_op_id, posted_at
receipt_reservations  receipt_no UNIQUE, reserved_by morph (rep), expires_at, consumed_at, payment_id
retailer_credit       channel_id, retailer_id UNIQUE, credit_limit, grace_days,
                      on_exceed ∈ warn|block|manual_approval
rep_wallets           channel_id, rep_id UNIQUE,  -- الرصيد يُحسب من الحركات لا عمود مخزّن للكتابة المباشرة
rep_wallet_entries    type ∈ collect|settle, amount, payment_id nullable, operation_no unique,
                      at, actor
ledger_entries        account (retailer_receivable|rep_wallet|…), debit, credit, ref_type, ref_id, at
```

`IssuesInvoice` عند اكتمال التسليم (L3) يدرج `invoices` + بنود + `remaining=total`.

حجز الوصل EP-CM-050: يولّد `RCPT-*` فريداً، يربطه بالمندوب/الجهاز، TTL معقول (مثلاً 24س). الدفع بدونه → 422. الدفع بنفس الرقم مرتين (مندوب ثم تاجر أو إعادة مزامنة) → **صف دفعة واحد**؛ الثاني `duplicate` / 409 إن المبلغ مختلف.

تحصيل المندوب يزيد محفظته (`collect`) وينقص `invoice.remaining`. تسجيل التاجر بنفس `receipt_no` يثبت الجانب الآخر ولا يضاعف. دفعة مكتبية لا تزيد محفظة المندوب.

تسوية EP-SC-084 / سحب المندوب EP-RP-062: نفس `operation_no` فريد عبر إعادة المزامنة. `settle` ينقص المحفظة. PDF إيصال Job على `exports`.

سقف الائتمان: `CreditGuard` عند تأكيد الطلب (وsubmit إن `block`): إن `receivable + new_total > limit` و`on_exceed=block` → 423 `credit_limit_exceeded`. `warn` يسمح. `manual_approval` → حالة انتظار (لا تُفرَّغ في SP-13 إن لم يُحدد مسار؛ وثّق: عومل كـ block إلى أن يُضاف مسار اعتماد).

كشف الحساب: صفوف من الدفتر لا JOIN حي للطلبات. ملخص الشهر (EP-RT-051) من **لقطات** إن وُجدت وإلا تجميع يومي مخزّن؛ لا JOIN ثقيل على كل طلب. بعد SP-16 يُلزم ADR-07.

الذمم حسب القناة: اسم القناة ظاهر لأن فاتورة مؤكدة وُجدت (REQ-IN-06 اكتمل).

### 1.3 مسارات — 18

| كود | مسار | إذن |
|---|---|---|
| EP-SC-080 | `GET /channel/invoices` | `sc.finance.view` |
| EP-SC-081 | `POST .../credit-note` | `sc.finance.credit_note` dual |
| EP-SC-082 | `POST .../void` | `sc.finance.void_invoice` dual |
| EP-SC-083 | `POST /channel/payments` | `sc.finance.payment` + SOD-01 |
| EP-SC-084 | `POST /channel/reps/{id}/settle` | `sc.reps.settle` |
| EP-SC-085 | `GET /channel/finance/aging` | `sc.finance.aging` — `group_by=zone\|rep` دلاء 0–30/31–60/61–90/>90 |
| EP-SC-086 | `PUT /channel/retailers/{id}/credit` | `sc.retailers.credit` |
| EP-CM-050 | `POST /app/receipts/reserve` | `rp.payment.collect` (مندوب) |
| EP-RT-050 | `POST /app/retailer/payments` | `rt.payment.record` |
| EP-RT-051 | `GET /app/retailer/account/summary` | — |
| EP-RT-052 | `GET .../statement` | `rt.account.statement` |
| EP-RT-053 | `POST .../statement/export` | statement — Job |
| EP-RT-054 | `GET /app/retailer/debts` | — |
| EP-RP-060 | `POST /app/rep/payments` | `rp.payment.collect` |
| EP-RP-061 | `GET /app/rep/wallet` | `rp.wallet.view` |
| EP-RP-062 | `POST .../withdrawals` | `rp.payment.withdraw` |
| EP-RP-063 | `GET .../withdrawals` | view |
| EP-RP-064 | `GET /app/rep/receivables` | view |

### 1.4 اختبارات SP-13

- حجز ثم تحصيل ثم تسجيل تاجر بنفس الرقم: صف `payments` واحد؛ `receivable` و`wallet` يطابقان الثوابت.
- تكرار `receipt_no` بمبلغ مختلف → 409.
- تأكيد طلب فوق السقف `block` → 423.
- مستخدم بصلاحيتَي confirm+payment → 403 عند الدفعة المكتبية.
- void يُخرج الفاتورة من AC-07.
- `operation_no` مكرر لا يسوّي مرتين.

---

## 2. SP-14 — مزامنة وإشعارات (13 API الأصلية)

### 2.1 مزامنة — `sync` (ADR-06, ADR-08)

الخادم مصدر الحقيقة. الكاش المحلي عرض. العميل **لا يدفع** كتالوج/أسعار/عروض/مخزون (REQ-CM-017).

```
sync_cursors          device_id, user morph, scope, cursor, last_pull_at
sync_operations       client_op_id UNIQUE(device, id), type, payload, status ∈ applied|duplicate|conflict|failed,
                      server_id, error
sync_conflicts        public_id, op_id, server_snapshot, client_snapshot, resolution nullable
```

`GET /app/sync/pull`:

- `cursor` فارغ أو قديم جداً → `full_resync_required=true` وحد أدنى من الحذف.
- `scopes[]`: تاجر `catalog,pricing,offers,zones,orders,refs` — مندوب يضيف `rep_catalog,customers,zones,assignments,deliveries,wallet`.
- كل نطاق: `{upserts, deletes}` بحد `limit` (افتراضي 200).
- `next_cursor` غير زمني خام (ULID / id مركب موقَّع).
- يولَّد من جداول المصدر عبر `updated_at` داخلي **لا يُرجع للعميل كمرشح**.

`POST /app/sync/push`:

- حد 500 عملية/دفعة، 20 دفعة/دقيقة/جهاز → 429.
- أنواع مسموحة: `cart.submit`, `payment`, `delivery.*`, `receipt`, `shortage`, `favorite`, `rating`, `add-shop`.
- `client_op_id` موجود → `duplicate` مع `server_id` السابق (لا إعادة تطبيق).
- تعارض (مثلاً تسليم عُدِّل على الخادم) → `conflict` + صف في `sync_conflicts`. العميل لا يحذف من outbox حتى `applied|duplicate` (REQ-CM-019).
- `cart.submit` أوفلاين يمر عبر مسار الطلب مع `offline_created=true` (L3).

`resolve-conflict`: `server_wins` | `client_wins` (إن سُمح للنوع). الافتراضي الآمن: `server_wins` للمال والمخزون.

### 2.2 إشعارات — `notification`

تسمع أحداث L3/L13. الطابور `notifications`.

```
notification_templates   owner (channel|platform), event_key, title, body (placeholders), enabled, channels JSON
notification_campaigns   targeting JSON, scheduled_at, status ∈ queued|pending_approval|sent
notification_deliveries  recipient morph, template/campaign, channel ∈ push|in_app, status, failure_reason, at
inbox_items              user morph, title, body, icon, action JSON, read_at, hidden_at
device_push_tokens       device, token, platform
```

بث المنصة EP-AD-080: dual+crit، يبدأ `pending_approval`. إرسال القناة: `queued` فوراً إن صلاحية `sc.notify.send`.

`DELETE /app/notifications`: يخفي العرض (`hidden_at`) **لا** يمسح `notification_deliveries`.

الغلاف: `meta.unread_count`.

FCM/APNs عبر `integration`. في الاختبار Fake.

### 2.3 مسارات — 13

| كود | مسار |
|---|---|
| EP-SY-001…004 | `/app/sync/pull|push|status|resolve-conflict` |
| EP-CM-060…063 | صندوق، قراءة الكل، مسح عرض، push-token |
| EP-SC-090 | إرسال قناة |
| EP-SC-091A/B | قوالب قناة |
| EP-SC-092 | سجل قناة |
| EP-AD-080 | بث منصة dual |

### 2.4 اختبارات SP-14

- دفع سعر منتج من العميل → `failed` / رفض النوع.
- نفس `client_op_id` مرتين → `duplicate` بلا دفعة ثانية.
- cursor منتهي → `full_resync_required`.
- مسح الصندوق يبقي السجل في `/channel/notifications/log`.
- بث بلا اعتماد ثانٍ لا يُرسل.

---

## 3. SP-15 — محتوى وولاء (14 API)

### 3.1 محتوى — `content`

```
channel_intros        enabled, text, media_type, media_id, duration, targeting JSON
banners               media, link JSON, placements[], targeting, starts_at, ends_at, order, weight, status
banner_stats          impressions, clicks  -- CTR يُحسب
sliders               source ∈ manual|brand|category|offers|algorithm, algorithm, placements, items_count, targeting
slider_items          للـ manual: product/offer ids مرتّبة
```

`GET /app/content/home-blocks`: فلتر نشاط+منطقة التاجر/المندوب + انترو مطابق (TB-RT-010). يُستدعى من تجميع الرئيسية (L2 كانت أصفاراً — هنا تُملأ).

خوارزميات السلايدر (`best_selling` إلخ): من **لقطات/تجميعات** لا من مسح `sub_order_lines` الحي في الطلب. حتى تُوجد لقطات SP-16: تجميع ليلي مبكر داخل content أو جدول `product_ranks` يحدّثه أمر.

إحصاء البنر: endpoints تطبيق تزيد العداد (أو بكسل لاحق)؛ لا أرقام وهمية في الاختبار بلا أحداث.

### 3.2 ولاء — `loyalty`

```
loyalty_rules         channel_id, audience ∈ retailer|rep, event, points_per_1000 أو points int
loyalty_tiers         channel_id, name, threshold, benefits JSON
loyalty_accounts      owner morph, points, tier
loyalty_ledger        delta, reason/event, ref, at append-only
loyalty_rewards       name, points_cost, stock, expires_at
loyalty_redemptions   redemption_no, reward_id, account_id, status
```

مستمعون: `invoice_paid` → نقاط تاجر؛ `delivery_completed` → نقاط مندوب. النقاط أعداد صحيحة. `points_per_1000` = 1 تعني نقطة لكل 1000 وحدة نقد.

استبدال: ينقص النقاط والمخزون. خطة القناة بلا ميزة `loyalty` → 423 `plan_limit_exceeded`.

`GET /app/session` وheader الرئيسية يقرآن `loyalty_accounts` لا أصفار.

### 3.3 مسارات — 14

| كود | مسار |
|---|---|
| EP-SC-100A/B | انترو |
| EP-SC-101A/B, 102 | بنرات + إحصاء |
| EP-SC-103A/B | سلايدر |
| EP-SC-110A/B, 111A/B | قواعد ومكافآت |
| EP-APP-100 | home-blocks |
| EP-APP-110/111 | محفظة نقاط / استبدال |

اختبارات: بنر منطقة 12 لا يظهر لتاجر 13. استبدال بلا رصيد → 422. خطة بلا ولاء → 423. النقاط لا تُكتب float.

---

## 4. SP-16 — لوحات وتقارير (7 API)

موديول `reporting`. **ADR-07 / BR-AD-23:** بلاط KPI **لا** يقرأ جداول الطلبات الحية.

```
daily_channel_snapshots     channel_id, date, JSON kpis (مبيعات، طلبات حسب حالة، تحصيل، ذمم، fill_rate, …)
daily_platform_snapshots    date, JSON (قنوات، تجّار، GMV، إيراد منصة، صحة تكامل)
export_jobs                 type, status, download_path, expires_at (24س), requester
```

أمر `snapshots:generate` (جدولة يومية + يمكن إعادة يوم). المصدر: دفاتر finance + حالات sub_orders + مخزون — يكتب صف اللقطة فقط.

`GET` لوحة/تقرير يقرأ اللقطات (+ تقرير تفصيلي قد يقرأ جداول حقائق تجميعية لا OLTP الخام للبلاط).

هوامش EP-SC-123: crit؛ من تكلفة إن وُجدت وإلا وثّق `null` لا رقم مخترع — إن لا تكلفة في الكتالوج بعد، أرجع صفوفاً فارغة صادقة حتى يوجد حقل تكلفة منتج (لا تخترع هامشاً).

تصدير: طابور `exports`، حد 5/ساعة للمنصة. استطلاع EP-AD-092.

أنواع تقارير المنصة: `gmv|adoption|operations|growth|quality`.  
القناة: `sales|products|retailers|reps|zones|inventory|finance|offers|operations`.

### 4.1 مسارات — 7

| كود | مسار |
|---|---|
| EP-AD-090 | لوحة منصة |
| EP-AD-091 | تقرير منصة |
| EP-AD-092 | حالة تصدير |
| EP-SC-120 | لوحة قناة |
| EP-SC-121 | تقرير قناة |
| EP-SC-122 | تصدير قناة |
| EP-SC-123 | هوامش crit |

اختبارات: إيقاف قراءة OLTP في Query البلاط (architecture test أو أن Query يلمس جدول `*_snapshots` فقط). تصدير منتهٍ يعطي URL ينتهي بعد 24س.

---

## 5. SP-17 — منصة (27 API الأصلية + `/public/app-config`)

تتوزع على `platform-billing` · `support` · `content` · `core`/`integration` · `identity` (فريق).

### 5.1 فوترة المنصة — `platform-billing`

يستبدل حدود SP-04 الخفيفة: `plans` المصدر. اشتراك القناة يشير لـ `plan_id`.

```
plans                 key unique, prices monthly/yearly BIGINT, limits JSON, features[], on_exceed, trial_days, is_public
subscriptions         channel_id, plan_id, cycle, status, next_renewal, amount
platform_invoices     no PF-*, amount, due_at, status ∈ open|paid|waived|void, pdf
dunning_items         derived أو جدول طابور: days_late, reminders_sent, next_action
```

إعفاء فاتورة: dual + crit + SOD-07 (طالب الإعفاء ≠ المعتمد). تعيين خطة قد يكون `scheduled`.

حدود `skus/otp_monthly/...`: عند التجاوز `on_exceed=block` → 423 `plan_limit_exceeded` (ولاء SP-15 يستخدمها).

### 5.2 ميزات ونسخ — `core` (+ content للنسخ)

```
feature_flags         key, enabled_globally, rollout_percent, scopes[], planned_removal_at
feature_overrides     channel_id, key, enabled, reason
app_versions          app, platform, version, build, min_supported, rollout, store_url, force_update
maintenance_state     singleton: enabled, message_ar, until
otp_channel_setting   whatsapp|sms  — تبديل بلا نشر (REQ-AD-076)
```

`GET /public/app-config`: بدون auth. `X-App-Version` أقدم من `min_supported` → 426 `upgrade_required`. صيانة → 503 `maintenance_mode` (ما عدا health).

فرض التحديث dual+crit وفقط لكسر API حقيقي (BR-AD-17).

### 5.3 دعم — `support`

بحث `q`: هاتف، اسم، محل، رقم طلب/فاتورة/وصل — عبر عقود لا JOIN لكل الموديولات في Query واحد مكدّس؛ خدمة تجمّع نتائج محدودة.

انتحال: توكن ≤ 15 د، استخدام واحد، `write_enabled` افتراضي false → 403 `guest_write_blocked`. كلمة مرور + OTP. كل استدعاءات الجلسة المنتحلة تُعلَّم `impersonated=true` في audit.

إعادة OTP / سحب جلسات: تفوّض `identity`.

تذاكر: CRUD أدنى في هذا السبرنت.

### 5.4 صحة النظام

قراءة Horizon/Redis للطوابير. آخر 100 استثناء من مخزن (جدول `system_exceptions` يملؤه handler أو Sentry API). صحة sync من جداول SP-14. تكاملات: عدّادات نجاح/زمن من `integration`. إعادة Jobs فاشلة. صيانة dual.

### 5.5 مسارات — 27

مجموعات `03-platform-ops.php` + `EP-PB-010`:

- خطط/اشتراكات/فواتير منصة/dunning: EP-AD-100A/B, 101–105  
- flags ونسخ: 110A/B, 111–113  
- دعم: 120–125A/B  
- نظام: 130–136  
- عام: EP-PB-010 `/public/app-config`

اختبارات: app-config نسخة قديمة → 426. انتحال بلا كتابة يفشل POST سلة. تبديل OTP يغيّر القناة في الطلب التالي بلا إعادة نشر. إعفاء فاتورة من المنشئ → 403 SOD.

---

## 6. SP-18 — إطلاق (0 API منتج)

لا صف كتالوج جديد. عمل تشغيلي:

| بند | المطلوب |
|---|---|
| حمل | سيناريوهات k6: OTP، browse، quote، submit، confirm، sync pull (أهداف DOC-11A) |
| نسخ | نسخ احتياطي MySQL + MinIO؛ تجربة استعادة على بيئة غير إنتاج |
| Runbook | صيانة، تبديل OTP، إعادة الطابور، force-update، تعليق قناة |
| أمن | مراجعة حراس، dual، عدم تسريب قناة، معدل OTP |
| مراقبة | Sentry + صحة `/health` + بوابات CI الست خضراء |
| نسخ قديمة | حذف `Modules.old` إن بقي |

Backup APIs في ملف 11 (`/platform/settings/backup`, `system/backups`) تُحسب **SP-17** لا 18.

تعريف الإغلاق: بيئة إنتاج بقائمة تحقق موقعة، بلا endpoint جديد.

---

## 7. توجيه الطبقة (الشريحة الأصلية)

```
/api/v1/channel/invoices|payments|finance|retailers/{id}/credit|reps/{id}/settle   finance
/api/v1/app/receipts/reserve + retailer/rep finance paths                           finance
/api/v1/app/sync/*                                                                  sync
/api/v1/app/notifications*  /devices/push-token  /channel/notifications*  /platform/notifications/broadcast  notification
/api/v1/channel/content/*  /app/content/home-blocks                                 content
/api/v1/channel/loyalty/*  /app/loyalty*                                            loyalty
/api/v1/channel/dashboard|/reports*  /platform/dashboard|/reports*|/exports*        reporting
/api/v1/platform/plans|subscriptions|platform-invoices|dunning                       platform-billing
/api/v1/platform/features|app-versions|system|support                               core/support/identity
/api/v1/public/app-config                                                           core
```

---

## 8. ترتيب البناء

1. دفتر finance + حجز وصل + ثوابت AC-06/07 + ربط `IssuesInvoice`/`CreditGuard`.  
2. دفعات المندوب/التاجر/المكتب (صف واحد للوصل).  
3. Sync pull للكتالوج ثم push للعمليات المسموحة.  
4. مستمعو إشعارات الأحداث الموجودة.  
5. محتوى + ولاء يملآن الرئيسية والجلسة.  
6. `snapshots:generate` ثم اللوحات.  
7. خطط المنصة تستبدل حدود SP-04؛ flags؛ app-config؛ انتحال.  
8. SP-18 تصليب.

---

## 9. ملحق ملزم — كل صف في `10-platform-12e.php` و`11-platform-doc07.php`

الكتالوج الحالي **338** نقطة. الشريحة الأصلية في هذا الملف تبقى صحيحة. الإضافات التالية **نفس رقم السبرنت**، ليست SP-18. الشكل في الملفين.

**SP-14 — إشعارات المنصة (7) — `notification`**

| كود | مسار |
|---|---|
| EP-AD-081 | `POST /platform/notifications` |
| EP-AD-082 | `POST /platform/notifications/preview` |
| EP-AD-083A/B | قوالب المنصة GET/PUT |
| EP-AD-084 | `GET /platform/notifications/log` |
| EP-AD-085A/B | حملات GET list/detail |

يُغلق SP-14 على **20** مساراً.

**SP-16 — لوحة منصة تفصيلية (4) — `reporting`**

| كود | مسار |
|---|---|
| EP-AD-093 | `POST /platform/reports/{type}/export` |
| EP-AD-094 | `GET /platform/dashboard/alerts` |
| EP-AD-095 | `GET /platform/dashboard/cards/{key}` |
| EP-AD-096 | `GET /platform/dashboard/charts/{key}` |

يُغلق SP-16 على **11** مساراً.

**SP-17 — بقية سطح المنصة (36) — حسب الموديول**

| مجموعة | أكواد | موديول |
|---|---|---|
| خطة تفصيل/تعديل | EP-AD-100C/D | platform-billing |
| إشعار دائن منصة + إيراد | EP-AD-106, 107 | platform-billing |
| حذف تجاوز ميزة + قائمة نسخ | EP-AD-114, 115 | core / content |
| تعطيل مستخدم، تصفير جهاز، تذكرة PATCH | EP-AD-126…128 | support |
| وظائف مجدولة، تخزين | EP-AD-137, 138 | core |
| نسخ احتياطي وإعداداته + تجربة استعادة | EP-AD-139A…D | core |
| قانوني / انترو منصة / مساعدة | EP-AD-140A/B, 141A/B, 142A/B | content |
| ملف شخصي منصة، تكاملات، افتراضات قناة، أمن | EP-AD-150A/B … 153A/B, 158A/B | identity + integration |
| فريق المنصة: قائمة، دعوة، تعطيل، تعديل، حذف | EP-AD-154…157, 160A/B | identity |

يُغلق SP-17 على **63** مساراً (27 أصلية + 36 ملحق). شكل كل صف في الكتالوج.

---

## 10. ما يُمنع

- كسر AC-06 أو AC-07 ولو في سباق: قفل صف المحفظة/الفاتورة داخل المعاملة.
- دفع كتالوج/سعر من الجهاز.
- لوحة KPI من JOIN على `sub_orders` الحي.
- float للنقاط أو المال.
- انتحال بلا audit `impersonated`.
- مضاعفة دفعة لنفس `receipt_no`.
- اختراع هوامش بلا تكلفة مصدر.
- API منتج في SP-18.

---

## 11. تعريف «منتهٍ»

| سبرنت | يُغلق عند |
|---|---|
| SP-13 | **18** + اختبار الوصل الواحد + الثوابت |
| SP-14 | **20** + منع دفع الكتالوج + cursor |
| SP-15 | **14** + الرئيسية غير فارغة للاستهداف |
| SP-16 | **11** + بلاط من snapshots فقط |
| SP-17 | **63** + app-config 426/503 + انتحال |
| SP-18 | k6 + استعادة نسخة + runbook — صفر مسار جديد |

المرجع الشكلي: `05-channel-ops.php`, `09-shared-app.php`, `07-retailer.php`, `08-rep.php`, `03-platform-ops.php`, `00-public.php`, ثم 10 و11.
