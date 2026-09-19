# خطة إكمال تطبيقات الموبايل والمشترك

الحالة الحيّة في [`../status/04-retailer-app.md`](../status/04-retailer-app.md)، [`05-rep-app.md`](../status/05-rep-app.md)، [`06-shared-app.md`](../status/06-shared-app.md)، [`07-public.md`](../status/07-public.md) (مولَّدة). تطبيق المندوب مكتمل حسب الكتالوج. المتبقي **17 مساراً**: خمسة لمالية التاجر، أحد عشر مشتركة بين التطبيقين، وواحد عام.

| # | التذكرة | مسارات | الوحدة | يعتمد على | الحالة |
|---|---|---|---|---|---|
| AP-01 | مالية التاجر: دفعة، ملخص، كشف، تصدير، ذمم | 5 | Finance | — | ⬜ |
| AP-02 | الإشعارات الموحّدة ورمز الدفع | 4 | Notification | — | ⬜ |
| AP-03 | المزامنة دون اتصال | 4 | Sync | — | ⬜ |
| AP-04 | بلوكات الرئيسية | 1 | Content | — | ⬜ |
| AP-05 | الولاء | 2 | Loyalty | — | ⬜ |
| AP-06 | إعداد التطبيق العام | 1 | Content | PA-08, PA-09, PA-15 | ⬜ (نفسه PA-09 في خطة المنصة) |

---

## AP-01 — مالية التاجر: EP-RT-050 … 054

- **وحدة:** Finance تملك `payments`, `invoices`, `receipts`, `retailer_debts` — كلها موجودة وتخدم لوحة القناة. المسارات الخمسة قراءةٌ لنفس الجداول من جهة التاجر مع فلتر المالك (`retailer_id`) بجانب `acrossChannels()` (القاعدة 10 — نمط SubOrder؛ `/app/*` لا يضبط مستأجراً لأن التاجر يشتري من عدة قنوات).
- **050 تسجيل دفعة:** التاجر يعلن دفعة (نقد للمندوب/تحويل) → `payments` بحالة `pending_confirmation`؛ القناة تؤكدها عبر EP-SC-13x الموجود. `amount` صحيح، `X-Idempotency-Key` إلزامي.
- **051 الملخص:** رصيد لكل قناة + إجمالي، آخر دفعة، أقدم دين.
- **052/053 الكشف:** حركات مرتّبة بالتاريخ بفلتر قناة ومدة؛ التصدير `ReportExport` على طابور `reports` بحدّ 5/ساعة → 429.
- **054 الذمم:** الفواتير المفتوحة مع الأعمار (0–30، 31–60، 61+).
- **قبول:** تاجر لا يرى دفعة تاجر آخر (404)؛ الملخص يطابق مجموع الفواتير – المدفوعات لكل قناة في اختبار واحد.

## AP-02 — الإشعارات الموحّدة: EP-CM-060, 061, 062, 063

- **وحدة:** Notification. لديها اليوم قوالب القناة وسجل التسليم فقط؛ لا صندوق وارد للتطبيق.
- **جداول:** `app_notifications` (recipient_type: retailer|rep, recipient_id, channel_id nullable, title, body, deep_link, read_at, sent_at) و`push_tokens` (recipient_type, recipient_id, device_uuid, platform, token, updated_at — فريد على device_uuid).
- المصادر التي تكتب فيه: أحداث Ordering/Delivery/Finance الموجودة (`SubOrderConfirmed`, `HandoverCompleted`, …) عبر مستمعين في Notification — الحدث يُعلم ولا يتحكم (القاعدة 5)؛ وحملات المنصة (PA-13).
- **063** يرتبط بـ `device_uuid` للتوكن الحالي؛ توكن جهاز آخر لنفس الحساب يُستبدل لا يُضاف.
- `unread_count` يُرجَع في `meta` على EP-CM-060 ليعرضه التطبيق دون مسار إضافي (لا مسار عدّ في الكتالوج).

## AP-03 — المزامنة: EP-SY-001 … 004

- **وحدة:** Sync (Coordination — فارغة اليوم). العميل يعمل دون اتصال (DOC-04 §المزامنة) ويدفع عمليات مؤرَّخة.
- **جداول:** `sync_operations` (device_uuid, op_id فريد, entity, action, payload json, client_ts, status: pending|applied|rejected|conflict, server_result json), `sync_cursors` (device_uuid, cursor, pulled_at).
- **001 pull:** يعيد التغييرات منذ `cursor` عبر عقود الوحدات (`CatalogSyncSource`, `PricingSyncSource`, `OrderingSyncSource` — كلٌّ يعيد الصفوف المعدّلة منذ طابع بصيغة موحّدة)؛ `meta.sync_cursor` هو الطابع الجديد (الغلاف يحمله أصلاً).
- **002 push:** كل عملية تُنفَّذ عبر الإجراء الموجود (`SubmitOrder`, …) بمفتاح تكرار = `op_id`؛ فشل واحدة لا يُسقط الدفعة؛ النتيجة لكل op.
- **003 status:** آخر cursor، عدد المعلّق، عدد التعارضات.
- **004 resolve-conflict:** `keep_server|keep_client` على op بحالة `conflict`.
- **قبول:** دفع نفس `op_id` مرتين يعيد النتيجة المخزّنة لا تنفيذاً ثانياً.

## AP-04 — بلوكات الرئيسية: EP-APP-100

- **وحدة:** Content. الجدول `home_blocks` (channel_id, type: banner|products|categories|offer, title, payload json, order, active_from/to). التاجر يرى بلوكات قنواته النشطة مجمّعة؛ المندوب يرى بلوكات قناته. لوحة القناة تديرها عبر EP-SC-16x الموجودة إن كانت في الكتالوج، وإلا تُبذر.

## AP-05 — الولاء: EP-APP-110, 111

- **وحدة:** Loyalty (Domain). `loyalty_accounts` (retailer_id, channel_id, balance, tier), `loyalty_transactions` (earn|redeem|expire, points, reference_type, reference_id). الكسب من حدث `SubOrderDelivered` بمعدّل القناة؛ **111** يخلق `redeem` ويعيد رمز خصم يستهلكه Pricing عبر عقد Core `RedeemableDiscount` — Pricing (Domain) لا يستورد Loyalty (Domain، نفس الطبقة) إلا عبر العقد.

## AP-06 — `/public/app-config`: EP-PB-010

نفسه PA-09 في [`platform-admin.md`](platform-admin.md): يُبنى مرة واحدة هناك لأنه يجمع نسخ التطبيقات (PA-09)، مفاتيح الميزات (PA-08) والصيانة (PA-15).

---

## ما ليس هنا عمداً

- **تطبيق المندوب** مكتمل بالكامل حسب الكتالوج (29/29 في `status/05`).
- **مسارات التاجر الأخرى** مكتملة (`status/04`: 28 من 33).
- **لوحة القناة والمستودع** مكتملتان.
