<?php

declare(strict_types=1);

namespace Modules\Access\Domain;

/**
 * Binding catalog of permission codes through SP-17 (docs/api/catalog).
 *
 * @phpstan-type PermissionRow array{
 *     name_ar: string,
 *     system: 'platform'|'channel'|'warehouse'|'app',
 *     module: string,
 *     severity: 'standard'|'critical',
 *     dual_approval: bool,
 *     delegatable: bool
 * }
 */
final class PermissionCatalog
{
    /**
     * @var array<string, PermissionRow>
     */
    private const ITEMS = [
        'ad.audit.export' => ['name_ar' => 'تصدير التدقيق', 'system' => 'platform', 'module' => 'audit', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.audit.review' => ['name_ar' => 'حملة مراجعة صلاحيات', 'system' => 'platform', 'module' => 'audit', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.audit.view' => ['name_ar' => 'سجل التدقيق', 'system' => 'platform', 'module' => 'audit', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.billing.assign_plan' => ['name_ar' => 'حدود القناة', 'system' => 'platform', 'module' => 'billing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.billing.dunning' => ['name_ar' => 'تحصيل المتأخرات', 'system' => 'platform', 'module' => 'billing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.billing.invoice' => ['name_ar' => 'مذكرة دائنة لفاتورة منصة', 'system' => 'platform', 'module' => 'billing', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.billing.plans' => ['name_ar' => 'الخطط', 'system' => 'platform', 'module' => 'billing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.billing.view' => ['name_ar' => 'الاشتراكات', 'system' => 'platform', 'module' => 'billing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.billing.waive' => ['name_ar' => 'إعفاء فاتورة منصة', 'system' => 'platform', 'module' => 'billing', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.channels.create' => ['name_ar' => 'إنشاء قناة', 'system' => 'platform', 'module' => 'channels', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.channels.delete' => ['name_ar' => 'حذف قناة', 'system' => 'platform', 'module' => 'channels', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.channels.export' => ['name_ar' => 'تصدير بيانات القناة', 'system' => 'platform', 'module' => 'channels', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.channels.suspend' => ['name_ar' => 'تغيير حالة القناة', 'system' => 'platform', 'module' => 'channels', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.channels.update' => ['name_ar' => 'إعادة التجهيز', 'system' => 'platform', 'module' => 'channels', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.channels.view' => ['name_ar' => 'قنوات التوريد', 'system' => 'platform', 'module' => 'channels', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.content.force_update' => ['name_ar' => 'فرض التحديث', 'system' => 'platform', 'module' => 'content', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.content.manage' => ['name_ar' => 'نشر نسخة شروط/خصوصية', 'system' => 'platform', 'module' => 'content', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.content.publish_version' => ['name_ar' => 'نشر نسخة تطبيق', 'system' => 'platform', 'module' => 'content', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.content.view' => ['name_ar' => 'سجل إصدارات التطبيقات', 'system' => 'platform', 'module' => 'content', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.dashboard.view' => ['name_ar' => 'لوحة المنصة', 'system' => 'platform', 'module' => 'dashboard', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.features.manage' => ['name_ar' => 'إنشاء مفتاح ميزة', 'system' => 'platform', 'module' => 'features', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.features.override' => ['name_ar' => 'تجاوز ميزة لقناة', 'system' => 'platform', 'module' => 'features', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.features.view' => ['name_ar' => 'ميزات هذه القناة', 'system' => 'platform', 'module' => 'features', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.iam.grant_temp' => ['name_ar' => 'منح مؤقت', 'system' => 'platform', 'module' => 'iam', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.iam.role_approve' => ['name_ar' => 'اعتماد الدور', 'system' => 'platform', 'module' => 'iam', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.iam.role_assign' => ['name_ar' => 'إسناد أدوار', 'system' => 'platform', 'module' => 'iam', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.iam.role_create' => ['name_ar' => 'إنشاء دور', 'system' => 'platform', 'module' => 'iam', 'severity' => 'standard', 'dual_approval' => true, 'delegatable' => true],
        'ad.iam.simulate' => ['name_ar' => 'محاكاة صلاحية', 'system' => 'platform', 'module' => 'iam', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.iam.sod_rules' => ['name_ar' => 'قواعد فصل المهام', 'system' => 'platform', 'module' => 'iam', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.iam.view_catalog' => ['name_ar' => 'كتالوج الصلاحيات', 'system' => 'platform', 'module' => 'iam', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.notify.broadcast' => ['name_ar' => 'بث إشعار المنصة', 'system' => 'platform', 'module' => 'notify', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.notify.send' => ['name_ar' => 'إشعار مديري القنوات', 'system' => 'platform', 'module' => 'notify', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.notify.view' => ['name_ar' => 'قوالب إشعارات المنصة', 'system' => 'platform', 'module' => 'notify', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.refs.create' => ['name_ar' => 'إنشاء محافظة', 'system' => 'platform', 'module' => 'refs', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.refs.currency' => ['name_ar' => 'العملات', 'system' => 'platform', 'module' => 'refs', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.refs.disable' => ['name_ar' => 'تعطيل/تفعيل منطقة', 'system' => 'platform', 'module' => 'refs', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.refs.import' => ['name_ar' => 'استيراد مرجعيات', 'system' => 'platform', 'module' => 'refs', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.refs.update' => ['name_ar' => 'تعديل محافظة', 'system' => 'platform', 'module' => 'refs', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.refs.view' => ['name_ar' => 'المحافظات', 'system' => 'platform', 'module' => 'refs', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.reports.export' => ['name_ar' => 'تصدير تقرير المنصة', 'system' => 'platform', 'module' => 'reports', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.reports.view' => ['name_ar' => 'تقرير المنصة', 'system' => 'platform', 'module' => 'reports', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.settings.security' => ['name_ar' => 'سياسة أمان المنصة', 'system' => 'platform', 'module' => 'settings', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.settings.update' => ['name_ar' => 'تحديث سياسة النسخ', 'system' => 'platform', 'module' => 'settings', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.settings.view' => ['name_ar' => 'سياسة النسخ الاحتياطي', 'system' => 'platform', 'module' => 'settings', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.support.disable_user' => ['name_ar' => 'تعطيل مستخدم مؤقت', 'system' => 'platform', 'module' => 'support', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.support.impersonate' => ['name_ar' => 'انتحال جلسة دعم', 'system' => 'platform', 'module' => 'support', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.support.resend_otp' => ['name_ar' => 'إعادة OTP لمستخدم', 'system' => 'platform', 'module' => 'support', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.support.revoke_sessions' => ['name_ar' => 'إنهاء جلسات المستخدم', 'system' => 'platform', 'module' => 'support', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.support.search' => ['name_ar' => 'بحث الدعم', 'system' => 'platform', 'module' => 'support', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.support.tickets' => ['name_ar' => 'التذاكر', 'system' => 'platform', 'module' => 'support', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.support.view_profile' => ['name_ar' => 'بطاقة المستخدم 360', 'system' => 'platform', 'module' => 'support', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.system.backup' => ['name_ar' => 'تشغيل نسخة احتياطية', 'system' => 'platform', 'module' => 'system', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.system.maintenance' => ['name_ar' => 'وضع الصيانة', 'system' => 'platform', 'module' => 'system', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.system.retry_jobs' => ['name_ar' => 'إعادة مهام فاشلة', 'system' => 'platform', 'module' => 'system', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.system.switch_otp' => ['name_ar' => 'تبديل قناة OTP', 'system' => 'platform', 'module' => 'system', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.system.view' => ['name_ar' => 'طوابير النظام', 'system' => 'platform', 'module' => 'system', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.team.delete' => ['name_ar' => 'تعطيل عضو منصة', 'system' => 'platform', 'module' => 'team', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'ad.team.invite' => ['name_ar' => 'دعوة عضو للمنصة', 'system' => 'platform', 'module' => 'team', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'ad.team.update' => ['name_ar' => 'تعديل عضو المنصة', 'system' => 'platform', 'module' => 'team', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'ad.team.view' => ['name_ar' => 'فريق المنصة', 'system' => 'platform', 'module' => 'team', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.delivery.accept' => ['name_ar' => 'إسنادات بانتظار القبول', 'system' => 'app', 'module' => 'delivery', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.delivery.deliver' => ['name_ar' => 'قائمة التسليم', 'system' => 'app', 'module' => 'delivery', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.delivery.postpone' => ['name_ar' => 'تأجيل التسليم', 'system' => 'app', 'module' => 'delivery', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.delivery.return_request' => ['name_ar' => 'طلب إرجاع ميداني', 'system' => 'app', 'module' => 'delivery', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.payment.collect' => ['name_ar' => 'حجز رقم وصل', 'system' => 'app', 'module' => 'payment', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.payment.withdraw' => ['name_ar' => 'تسليم نقدية', 'system' => 'app', 'module' => 'payment', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.wallet.view' => ['name_ar' => 'المحفظة', 'system' => 'app', 'module' => 'wallet', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rp.warehouse.receive' => ['name_ar' => 'استلام العهدة', 'system' => 'app', 'module' => 'warehouse', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rt.account.statement' => ['name_ar' => 'كشف الحساب', 'system' => 'app', 'module' => 'account', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rt.payment.record' => ['name_ar' => 'تسجيل دفعة', 'system' => 'app', 'module' => 'payment', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rt.receive.confirm' => ['name_ar' => 'قائمة الاستلام', 'system' => 'app', 'module' => 'receive', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'rt.receive.return_request' => ['name_ar' => 'طلب إرجاع/استبدال', 'system' => 'app', 'module' => 'receive', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.catalog.create' => ['name_ar' => 'إنشاء علامة', 'system' => 'channel', 'module' => 'catalog', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.catalog.import' => ['name_ar' => 'استيراد الكتالوج', 'system' => 'channel', 'module' => 'catalog', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.catalog.update' => ['name_ar' => 'إعادة ترتيب الفئات', 'system' => 'channel', 'module' => 'catalog', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.catalog.variants' => ['name_ar' => 'توليد التباينات', 'system' => 'channel', 'module' => 'catalog', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.catalog.view' => ['name_ar' => 'العلامات', 'system' => 'channel', 'module' => 'catalog', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.content.banners' => ['name_ar' => 'البنرات', 'system' => 'channel', 'module' => 'content', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.content.intro' => ['name_ar' => 'شاشة الانترو', 'system' => 'channel', 'module' => 'content', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.content.sliders' => ['name_ar' => 'الساليدرات', 'system' => 'channel', 'module' => 'content', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.dashboard.view' => ['name_ar' => 'لوحة القناة', 'system' => 'channel', 'module' => 'dashboard', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.finance.aging' => ['name_ar' => 'أعمار الذمم', 'system' => 'channel', 'module' => 'finance', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.finance.credit_note' => ['name_ar' => 'إشعار دائن', 'system' => 'channel', 'module' => 'finance', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'sc.finance.payment' => ['name_ar' => 'تسجيل دفعة مكتبية', 'system' => 'channel', 'module' => 'finance', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.finance.view' => ['name_ar' => 'الفواتير', 'system' => 'channel', 'module' => 'finance', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.finance.void_invoice' => ['name_ar' => 'إلغاء فاتورة', 'system' => 'channel', 'module' => 'finance', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'sc.inventory.adjust' => ['name_ar' => 'تسوية مخزون', 'system' => 'channel', 'module' => 'inventory', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'sc.inventory.reorder' => ['name_ar' => 'نقاط إعادة الطلب', 'system' => 'channel', 'module' => 'inventory', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.inventory.transfer' => ['name_ar' => 'تحويل مخزون', 'system' => 'channel', 'module' => 'inventory', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.inventory.view' => ['name_ar' => 'أرصدة المخزون', 'system' => 'channel', 'module' => 'inventory', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.loyalty.manage' => ['name_ar' => 'قواعد النقاط', 'system' => 'channel', 'module' => 'loyalty', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.notify.send' => ['name_ar' => 'إرسال إشعار', 'system' => 'channel', 'module' => 'notify', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.notify.templates' => ['name_ar' => 'قوالب الإشعارات', 'system' => 'channel', 'module' => 'notify', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        // EP-SC-092 GET /channel/notifications/log — catalogued, not in DOC-08.
        'sc.notify.view' => ['name_ar' => 'سجل الإشعارات', 'system' => 'channel', 'module' => 'notify', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.offers.create' => ['name_ar' => 'إنشاء عرض', 'system' => 'channel', 'module' => 'offers', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.offers.stop' => ['name_ar' => 'إيقاف عرض', 'system' => 'channel', 'module' => 'offers', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.offers.view' => ['name_ar' => 'العروض', 'system' => 'channel', 'module' => 'offers', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.assign' => ['name_ar' => 'إسناد لمندوب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.cancel' => ['name_ar' => 'إلغاء طلب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'sc.orders.confirm' => ['name_ar' => 'تأكيد طلب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.edit_lines' => ['name_ar' => 'تعديل بنود الطلب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.reassign' => ['name_ar' => 'إعادة إسناد', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.reject' => ['name_ar' => 'رفض طلب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.schedule' => ['name_ar' => 'جدولة طلب', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.orders.view' => ['name_ar' => 'الطلبات الفرعية', 'system' => 'channel', 'module' => 'orders', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.pricing.schedule' => ['name_ar' => 'جدولة قائمة أسعار', 'system' => 'channel', 'module' => 'pricing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.pricing.update' => ['name_ar' => 'تسعير منتج', 'system' => 'channel', 'module' => 'pricing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.pricing.view' => ['name_ar' => 'قوائم الأسعار', 'system' => 'channel', 'module' => 'pricing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.reports.export' => ['name_ar' => 'تصدير تقرير', 'system' => 'channel', 'module' => 'reports', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.reports.margins' => ['name_ar' => 'تقرير الهوامش', 'system' => 'channel', 'module' => 'reports', 'severity' => 'critical', 'dual_approval' => false, 'delegatable' => false],
        'sc.reports.view' => ['name_ar' => 'تقرير القناة', 'system' => 'channel', 'module' => 'reports', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.reps.settle' => ['name_ar' => 'تسوية عهدة المندوب', 'system' => 'channel', 'module' => 'reps', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.reps.update' => ['name_ar' => 'سقف خصم المندوب', 'system' => 'channel', 'module' => 'reps', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.retailers.credit' => ['name_ar' => 'سقف ائتمان التاجر', 'system' => 'channel', 'module' => 'retailers', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.returns.decide' => ['name_ar' => 'قرار الإرجاع', 'system' => 'channel', 'module' => 'returns', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.returns.view' => ['name_ar' => 'طلبات الإرجاع', 'system' => 'channel', 'module' => 'returns', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        // Added when a route needed them, per the standing rule for the DOC-08 codes this
        // catalog does not yet carry. These four gate the channel-owned routes that used
        // to run on `can:settings.*`: coverage rows in Modules\Reference and the channel's
        // own settings in Modules\Tenancy. DOC-08 rates the two writes حساسة, a tier this
        // catalog does not model; `ad.refs.create` and `ad.refs.update` carry the same
        // rating and are recorded here as standard, so these follow them.
        'sc.settings.update' => ['name_ar' => 'تعديل سياسات الطلب والتشغيل', 'system' => 'channel', 'module' => 'settings', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.settings.view' => ['name_ar' => 'عرض الإعدادات', 'system' => 'channel', 'module' => 'settings', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.zones.manage' => ['name_ar' => 'ضبط التغطية وأوقات التوصيل', 'system' => 'channel', 'module' => 'zones', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'sc.zones.view' => ['name_ar' => 'عرض المناطق', 'system' => 'channel', 'module' => 'zones', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.handover.execute' => ['name_ar' => 'عهد بانتظار المندوب', 'system' => 'warehouse', 'module' => 'handover', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.handover.return_trip' => ['name_ar' => 'عودة المندوب', 'system' => 'warehouse', 'module' => 'handover', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.packing.execute' => ['name_ar' => 'تحقق التغليف', 'system' => 'warehouse', 'module' => 'packing', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.picking.execute' => ['name_ar' => 'قائمة الالتقاط', 'system' => 'warehouse', 'module' => 'picking', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.picking.shortage' => ['name_ar' => 'نقص أثناء الالتقاط', 'system' => 'warehouse', 'module' => 'picking', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.queue.view' => ['name_ar' => 'طوابير المستودع', 'system' => 'warehouse', 'module' => 'queue', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.receiving.execute' => ['name_ar' => 'استلام وارد', 'system' => 'warehouse', 'module' => 'receiving', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.receiving.qc' => ['name_ar' => 'فحص الوارد', 'system' => 'warehouse', 'module' => 'receiving', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.returns.sort' => ['name_ar' => 'فرز المرتجع', 'system' => 'warehouse', 'module' => 'returns', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
        'wh.stocktake.approve' => ['name_ar' => 'اعتماد الجرد', 'system' => 'warehouse', 'module' => 'stocktake', 'severity' => 'critical', 'dual_approval' => true, 'delegatable' => false],
        'wh.stocktake.execute' => ['name_ar' => 'بدء جرد', 'system' => 'warehouse', 'module' => 'stocktake', 'severity' => 'standard', 'dual_approval' => false, 'delegatable' => true],
    ];

    /**
     * @return array<string, PermissionRow>
     */
    public static function all(): array
    {
        return self::ITEMS;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::ITEMS);
    }

    /**
     * @return PermissionRow|null
     */
    public static function get(string $code): ?array
    {
        return self::ITEMS[$code] ?? null;
    }

    public static function exists(string $code): bool
    {
        return isset(self::ITEMS[$code]);
    }

    /**
     * @return list<string>
     */
    public static function codesForSystem(string $system): array
    {
        $out = [];
        foreach (self::ITEMS as $code => $row) {
            if ($row['system'] === $system) {
                $out[] = $code;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function codesForModules(string $system, array $modules): array
    {
        $out = [];
        foreach (self::ITEMS as $code => $row) {
            if ($row['system'] === $system && in_array($row['module'], $modules, true)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    /**
     * Builtin role → permission codes. platform_admin receives every platform code.
     *
     * @return array<string, list<string>>
     */
    public static function builtinGrants(): array
    {
        return [
            'platform_admin' => self::codesForSystem('platform'),
            'channel_manager' => self::codesForSystem('channel'),
            'sales_manager' => self::codesForModules('channel', ['orders', 'reps', 'merchants', 'promotions', 'dashboard', 'pricing']),
            'catalog_manager' => self::codesForModules('channel', ['catalog', 'pricing', 'offers', 'promotions', 'content']),
            'accountant' => self::codesForModules('channel', ['finance', 'returns']),
            'warehouse_keeper' => self::codesForSystem('warehouse'),
            'retailer' => self::codesStartingWith('rt.'),
            'rep' => self::codesStartingWith('rp.'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function codesStartingWith(string $prefix): array
    {
        $out = [];
        foreach (self::ITEMS as $code => $_) {
            if (str_starts_with($code, $prefix)) {
                $out[] = $code;
            }
        }

        return $out;
    }

    public static function guardForSystem(string $system): string
    {
        return match ($system) {
            'platform' => 'platform',
            'channel' => 'channel',
            'warehouse' => 'warehouse',
            'app' => 'app',
            default => 'platform',
        };
    }
}
