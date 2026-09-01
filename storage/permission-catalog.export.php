<?php
return [
  0 => 
  [
    'code' => 'ad.audit.export',
    'name_ar' => 'تصدير التدقيق',
    'system' => 'platform',
    'module' => 'audit',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  1 => 
  [
    'code' => 'ad.audit.review',
    'name_ar' => 'حملة مراجعة صلاحيات',
    'system' => 'platform',
    'module' => 'audit',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  2 => 
  [
    'code' => 'ad.audit.view',
    'name_ar' => 'سجل التدقيق',
    'system' => 'platform',
    'module' => 'audit',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  3 => 
  [
    'code' => 'ad.billing.assign_plan',
    'name_ar' => 'حدود القناة',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  4 => 
  [
    'code' => 'ad.billing.dunning',
    'name_ar' => 'تحصيل المتأخرات',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  5 => 
  [
    'code' => 'ad.billing.invoice',
    'name_ar' => 'مذكرة دائنة لفاتورة منصة',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  6 => 
  [
    'code' => 'ad.billing.plans',
    'name_ar' => 'الخطط',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  7 => 
  [
    'code' => 'ad.billing.view',
    'name_ar' => 'الاشتراكات',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  8 => 
  [
    'code' => 'ad.billing.waive',
    'name_ar' => 'إعفاء فاتورة منصة',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  9 => 
  [
    'code' => 'ad.channels.create',
    'name_ar' => 'إنشاء قناة',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  10 => 
  [
    'code' => 'ad.channels.delete',
    'name_ar' => 'ad.channels.delete',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  11 => 
  [
    'code' => 'ad.channels.export',
    'name_ar' => 'تصدير بيانات القناة',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  12 => 
  [
    'code' => 'ad.channels.suspend',
    'name_ar' => 'تغيير حالة القناة',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  13 => 
  [
    'code' => 'ad.channels.update',
    'name_ar' => 'إعادة التجهيز',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  14 => 
  [
    'code' => 'ad.channels.view',
    'name_ar' => 'قنوات التوريد',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  15 => 
  [
    'code' => 'ad.content.force_update',
    'name_ar' => 'فرض التحديث',
    'system' => 'platform',
    'module' => 'content',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  16 => 
  [
    'code' => 'ad.content.manage',
    'name_ar' => 'نشر نسخة شروط/خصوصية',
    'system' => 'platform',
    'module' => 'content',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  17 => 
  [
    'code' => 'ad.content.publish_version',
    'name_ar' => 'نشر نسخة تطبيق',
    'system' => 'platform',
    'module' => 'content',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  18 => 
  [
    'code' => 'ad.content.view',
    'name_ar' => 'سجل إصدارات التطبيقات',
    'system' => 'platform',
    'module' => 'content',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  19 => 
  [
    'code' => 'ad.dashboard.view',
    'name_ar' => 'لوحة المنصة',
    'system' => 'platform',
    'module' => 'dashboard',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  20 => 
  [
    'code' => 'ad.features.manage',
    'name_ar' => 'إنشاء مفتاح ميزة',
    'system' => 'platform',
    'module' => 'features',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  21 => 
  [
    'code' => 'ad.features.override',
    'name_ar' => 'تجاوز ميزة لقناة',
    'system' => 'platform',
    'module' => 'features',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  22 => 
  [
    'code' => 'ad.features.view',
    'name_ar' => 'ميزات هذه القناة',
    'system' => 'platform',
    'module' => 'features',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  23 => 
  [
    'code' => 'ad.iam.grant_temp',
    'name_ar' => 'منح مؤقت',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  24 => 
  [
    'code' => 'ad.iam.role_approve',
    'name_ar' => 'اعتماد الدور',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  25 => 
  [
    'code' => 'ad.iam.role_assign',
    'name_ar' => 'إسناد أدوار',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  26 => 
  [
    'code' => 'ad.iam.role_create',
    'name_ar' => 'إنشاء دور',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'standard',
    'dual_approval' => true,
    'delegatable' => true,
  ),
  27 => 
  [
    'code' => 'ad.iam.simulate',
    'name_ar' => 'محاكاة صلاحية',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  28 => 
  [
    'code' => 'ad.iam.sod_rules',
    'name_ar' => 'قواعد فصل المهام',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  29 => 
  [
    'code' => 'ad.iam.view_catalog',
    'name_ar' => 'كتالوج الصلاحيات',
    'system' => 'platform',
    'module' => 'iam',
    'severity' => 'standard',
    'dual_approval' => true,
    'delegatable' => true,
  ),
  30 => 
  [
    'code' => 'ad.notify.broadcast',
    'name_ar' => 'بث إشعار المنصة',
    'system' => 'platform',
    'module' => 'notify',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  31 => 
  [
    'code' => 'ad.notify.send',
    'name_ar' => 'إشعار مديري القنوات',
    'system' => 'platform',
    'module' => 'notify',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  32 => 
  [
    'code' => 'ad.notify.view',
    'name_ar' => 'قوالب إشعارات المنصة',
    'system' => 'platform',
    'module' => 'notify',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  33 => 
  [
    'code' => 'ad.refs.create',
    'name_ar' => 'إنشاء محافظة',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  34 => 
  [
    'code' => 'ad.refs.currency',
    'name_ar' => 'العملات',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  35 => 
  [
    'code' => 'ad.refs.disable',
    'name_ar' => 'تعطيل/تفعيل منطقة',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  36 => 
  [
    'code' => 'ad.refs.import',
    'name_ar' => 'استيراد مرجعيات',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  37 => 
  [
    'code' => 'ad.refs.update',
    'name_ar' => 'تعديل محافظة',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  38 => 
  [
    'code' => 'ad.refs.view',
    'name_ar' => 'المحافظات',
    'system' => 'platform',
    'module' => 'refs',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  39 => 
  [
    'code' => 'ad.reports.export',
    'name_ar' => 'تصدير تقرير المنصة',
    'system' => 'platform',
    'module' => 'reports',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  40 => 
  [
    'code' => 'ad.reports.view',
    'name_ar' => 'تقرير المنصة',
    'system' => 'platform',
    'module' => 'reports',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  41 => 
  [
    'code' => 'ad.settings.security',
    'name_ar' => 'سياسة أمان المنصة',
    'system' => 'platform',
    'module' => 'settings',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  42 => 
  [
    'code' => 'ad.settings.update',
    'name_ar' => 'تحديث سياسة النسخ',
    'system' => 'platform',
    'module' => 'settings',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  43 => 
  [
    'code' => 'ad.settings.view',
    'name_ar' => 'سياسة النسخ الاحتياطي',
    'system' => 'platform',
    'module' => 'settings',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  44 => 
  [
    'code' => 'ad.support.disable_user',
    'name_ar' => 'تعطيل مستخدم مؤقت',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  45 => 
  [
    'code' => 'ad.support.impersonate',
    'name_ar' => 'انتحال جلسة دعم',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  46 => 
  [
    'code' => 'ad.support.resend_otp',
    'name_ar' => 'إعادة OTP لمستخدم',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  47 => 
  [
    'code' => 'ad.support.revoke_sessions',
    'name_ar' => 'إنهاء جلسات المستخدم',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  48 => 
  [
    'code' => 'ad.support.search',
    'name_ar' => 'بحث الدعم',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  49 => 
  [
    'code' => 'ad.support.tickets',
    'name_ar' => 'التذاكر',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  50 => 
  [
    'code' => 'ad.support.view_profile',
    'name_ar' => 'بطاقة المستخدم 360',
    'system' => 'platform',
    'module' => 'support',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  51 => 
  [
    'code' => 'ad.system.backup',
    'name_ar' => 'تشغيل نسخة احتياطية',
    'system' => 'platform',
    'module' => 'system',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  52 => 
  [
    'code' => 'ad.system.maintenance',
    'name_ar' => 'وضع الصيانة',
    'system' => 'platform',
    'module' => 'system',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  53 => 
  [
    'code' => 'ad.system.retry_jobs',
    'name_ar' => 'إعادة مهام فاشلة',
    'system' => 'platform',
    'module' => 'system',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  54 => 
  [
    'code' => 'ad.system.switch_otp',
    'name_ar' => 'تبديل قناة OTP',
    'system' => 'platform',
    'module' => 'system',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  55 => 
  [
    'code' => 'ad.system.view',
    'name_ar' => 'طوابير النظام',
    'system' => 'platform',
    'module' => 'system',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  56 => 
  [
    'code' => 'ad.team.delete',
    'name_ar' => 'تعطيل عضو منصة',
    'system' => 'platform',
    'module' => 'team',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  57 => 
  [
    'code' => 'ad.team.invite',
    'name_ar' => 'دعوة عضو للمنصة',
    'system' => 'platform',
    'module' => 'team',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  58 => 
  [
    'code' => 'ad.team.update',
    'name_ar' => 'تعديل عضو المنصة',
    'system' => 'platform',
    'module' => 'team',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  59 => 
  [
    'code' => 'ad.team.view',
    'name_ar' => 'فريق المنصة',
    'system' => 'platform',
    'module' => 'team',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  60 => 
  [
    'code' => 'rp.delivery.accept',
    'name_ar' => 'إسنادات بانتظار القبول',
    'system' => 'app',
    'module' => 'delivery',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  61 => 
  [
    'code' => 'rp.delivery.deliver',
    'name_ar' => 'قائمة التسليم',
    'system' => 'app',
    'module' => 'delivery',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  62 => 
  [
    'code' => 'rp.delivery.postpone',
    'name_ar' => 'تأجيل التسليم',
    'system' => 'app',
    'module' => 'delivery',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  63 => 
  [
    'code' => 'rp.delivery.return_request',
    'name_ar' => 'طلب إرجاع ميداني',
    'system' => 'app',
    'module' => 'delivery',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  64 => 
  [
    'code' => 'rp.payment.collect',
    'name_ar' => 'حجز رقم وصل',
    'system' => 'app',
    'module' => 'payment',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  65 => 
  [
    'code' => 'rp.payment.withdraw',
    'name_ar' => 'تسليم نقدية',
    'system' => 'app',
    'module' => 'payment',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  66 => 
  [
    'code' => 'rp.wallet.view',
    'name_ar' => 'المحفظة',
    'system' => 'app',
    'module' => 'wallet',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  67 => 
  [
    'code' => 'rp.warehouse.receive',
    'name_ar' => 'استلام العهدة',
    'system' => 'app',
    'module' => 'warehouse',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  68 => 
  [
    'code' => 'rt.account.statement',
    'name_ar' => 'كشف الحساب',
    'system' => 'app',
    'module' => 'account',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  69 => 
  [
    'code' => 'rt.payment.record',
    'name_ar' => 'تسجيل دفعة',
    'system' => 'app',
    'module' => 'payment',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  70 => 
  [
    'code' => 'rt.receive.confirm',
    'name_ar' => 'قائمة الاستلام',
    'system' => 'app',
    'module' => 'receive',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  71 => 
  [
    'code' => 'rt.receive.return_request',
    'name_ar' => 'طلب إرجاع/استبدال',
    'system' => 'app',
    'module' => 'receive',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  72 => 
  [
    'code' => 'sc.catalog.create',
    'name_ar' => 'إنشاء علامة',
    'system' => 'channel',
    'module' => 'catalog',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  73 => 
  [
    'code' => 'sc.catalog.import',
    'name_ar' => 'استيراد الكتالوج',
    'system' => 'channel',
    'module' => 'catalog',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  74 => 
  [
    'code' => 'sc.catalog.update',
    'name_ar' => 'إعادة ترتيب الفئات',
    'system' => 'channel',
    'module' => 'catalog',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  75 => 
  [
    'code' => 'sc.catalog.variants',
    'name_ar' => 'توليد التباينات',
    'system' => 'channel',
    'module' => 'catalog',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  76 => 
  [
    'code' => 'sc.catalog.view',
    'name_ar' => 'العلامات',
    'system' => 'channel',
    'module' => 'catalog',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  77 => 
  [
    'code' => 'sc.content.banners',
    'name_ar' => 'البنرات',
    'system' => 'channel',
    'module' => 'content',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  78 => 
  [
    'code' => 'sc.content.intro',
    'name_ar' => 'شاشة الانترو',
    'system' => 'channel',
    'module' => 'content',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  79 => 
  [
    'code' => 'sc.content.sliders',
    'name_ar' => 'الساليدرات',
    'system' => 'channel',
    'module' => 'content',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  80 => 
  [
    'code' => 'sc.dashboard.view',
    'name_ar' => 'لوحة القناة',
    'system' => 'channel',
    'module' => 'dashboard',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  81 => 
  [
    'code' => 'sc.finance.aging',
    'name_ar' => 'أعمار الذمم',
    'system' => 'channel',
    'module' => 'finance',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  82 => 
  [
    'code' => 'sc.finance.credit_note',
    'name_ar' => 'إشعار دائن',
    'system' => 'channel',
    'module' => 'finance',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  83 => 
  [
    'code' => 'sc.finance.payment',
    'name_ar' => 'تسجيل دفعة مكتبية',
    'system' => 'channel',
    'module' => 'finance',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  84 => 
  [
    'code' => 'sc.finance.view',
    'name_ar' => 'الفواتير',
    'system' => 'channel',
    'module' => 'finance',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  85 => 
  [
    'code' => 'sc.finance.void_invoice',
    'name_ar' => 'إلغاء فاتورة',
    'system' => 'channel',
    'module' => 'finance',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  86 => 
  [
    'code' => 'sc.inventory.adjust',
    'name_ar' => 'تسوية مخزون',
    'system' => 'channel',
    'module' => 'inventory',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  87 => 
  [
    'code' => 'sc.inventory.reorder',
    'name_ar' => 'نقاط إعادة الطلب',
    'system' => 'channel',
    'module' => 'inventory',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  88 => 
  [
    'code' => 'sc.inventory.transfer',
    'name_ar' => 'تحويل مخزون',
    'system' => 'channel',
    'module' => 'inventory',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  89 => 
  [
    'code' => 'sc.inventory.view',
    'name_ar' => 'أرصدة المخزون',
    'system' => 'channel',
    'module' => 'inventory',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  90 => 
  [
    'code' => 'sc.loyalty.manage',
    'name_ar' => 'قواعد النقاط',
    'system' => 'channel',
    'module' => 'loyalty',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  91 => 
  [
    'code' => 'sc.notify.send',
    'name_ar' => 'إرسال إشعار',
    'system' => 'channel',
    'module' => 'notify',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  92 => 
  [
    'code' => 'sc.notify.templates',
    'name_ar' => 'قوالب الإشعارات',
    'system' => 'channel',
    'module' => 'notify',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  93 => 
  [
    'code' => 'sc.notify.view',
    'name_ar' => 'سجل الإشعارات',
    'system' => 'channel',
    'module' => 'notify',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  94 => 
  [
    'code' => 'sc.offers.create',
    'name_ar' => 'إنشاء عرض',
    'system' => 'channel',
    'module' => 'offers',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  95 => 
  [
    'code' => 'sc.offers.stop',
    'name_ar' => 'إيقاف عرض',
    'system' => 'channel',
    'module' => 'offers',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  96 => 
  [
    'code' => 'sc.offers.view',
    'name_ar' => 'العروض',
    'system' => 'channel',
    'module' => 'offers',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  97 => 
  [
    'code' => 'sc.orders.assign',
    'name_ar' => 'إسناد لمندوب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  98 => 
  [
    'code' => 'sc.orders.cancel',
    'name_ar' => 'إلغاء طلب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  99 => 
  [
    'code' => 'sc.orders.confirm',
    'name_ar' => 'تأكيد طلب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  100 => 
  [
    'code' => 'sc.orders.edit_lines',
    'name_ar' => 'تعديل بنود الطلب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  101 => 
  [
    'code' => 'sc.orders.reassign',
    'name_ar' => 'إعادة إسناد',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  102 => 
  [
    'code' => 'sc.orders.reject',
    'name_ar' => 'رفض طلب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  103 => 
  [
    'code' => 'sc.orders.schedule',
    'name_ar' => 'جدولة طلب',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  104 => 
  [
    'code' => 'sc.orders.view',
    'name_ar' => 'الطلبات الفرعية',
    'system' => 'channel',
    'module' => 'orders',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  105 => 
  [
    'code' => 'sc.pricing.schedule',
    'name_ar' => 'جدولة قائمة أسعار',
    'system' => 'channel',
    'module' => 'pricing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  106 => 
  [
    'code' => 'sc.pricing.update',
    'name_ar' => 'تسعير منتج',
    'system' => 'channel',
    'module' => 'pricing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  107 => 
  [
    'code' => 'sc.pricing.view',
    'name_ar' => 'قوائم الأسعار',
    'system' => 'channel',
    'module' => 'pricing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  108 => 
  [
    'code' => 'sc.reports.export',
    'name_ar' => 'تصدير تقرير',
    'system' => 'channel',
    'module' => 'reports',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  109 => 
  [
    'code' => 'sc.reports.margins',
    'name_ar' => 'تقرير الهوامش',
    'system' => 'channel',
    'module' => 'reports',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
  ),
  110 => 
  [
    'code' => 'sc.reports.view',
    'name_ar' => 'تقرير القناة',
    'system' => 'channel',
    'module' => 'reports',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  111 => 
  [
    'code' => 'sc.reps.settle',
    'name_ar' => 'تسوية عهدة المندوب',
    'system' => 'channel',
    'module' => 'reps',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  112 => 
  [
    'code' => 'sc.reps.update',
    'name_ar' => 'سقف خصم المندوب',
    'system' => 'channel',
    'module' => 'reps',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  113 => 
  [
    'code' => 'sc.retailers.credit',
    'name_ar' => 'سقف ائتمان التاجر',
    'system' => 'channel',
    'module' => 'retailers',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  114 => 
  [
    'code' => 'sc.returns.decide',
    'name_ar' => 'قرار الإرجاع',
    'system' => 'channel',
    'module' => 'returns',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  115 => 
  [
    'code' => 'sc.returns.view',
    'name_ar' => 'طلبات الإرجاع',
    'system' => 'channel',
    'module' => 'returns',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  116 => 
  [
    'code' => 'wh.handover.execute',
    'name_ar' => 'عهد بانتظار المندوب',
    'system' => 'warehouse',
    'module' => 'handover',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  117 => 
  [
    'code' => 'wh.handover.return_trip',
    'name_ar' => 'عودة المندوب',
    'system' => 'warehouse',
    'module' => 'handover',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  118 => 
  [
    'code' => 'wh.packing.execute',
    'name_ar' => 'تحقق التغليف',
    'system' => 'warehouse',
    'module' => 'packing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  119 => 
  [
    'code' => 'wh.picking.execute',
    'name_ar' => 'قائمة الالتقاط',
    'system' => 'warehouse',
    'module' => 'picking',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  120 => 
  [
    'code' => 'wh.picking.shortage',
    'name_ar' => 'نقص أثناء الالتقاط',
    'system' => 'warehouse',
    'module' => 'picking',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  121 => 
  [
    'code' => 'wh.queue.view',
    'name_ar' => 'طوابير المستودع',
    'system' => 'warehouse',
    'module' => 'queue',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  122 => 
  [
    'code' => 'wh.receiving.execute',
    'name_ar' => 'استلام وارد',
    'system' => 'warehouse',
    'module' => 'receiving',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  123 => 
  [
    'code' => 'wh.receiving.qc',
    'name_ar' => 'فحص الوارد',
    'system' => 'warehouse',
    'module' => 'receiving',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  124 => 
  [
    'code' => 'wh.returns.sort',
    'name_ar' => 'فرز المرتجع',
    'system' => 'warehouse',
    'module' => 'returns',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
  125 => 
  [
    'code' => 'wh.stocktake.approve',
    'name_ar' => 'اعتماد الجرد',
    'system' => 'warehouse',
    'module' => 'stocktake',
    'severity' => 'critical',
    'dual_approval' => true,
    'delegatable' => false,
  ),
  126 => 
  [
    'code' => 'wh.stocktake.execute',
    'name_ar' => 'بدء جرد',
    'system' => 'warehouse',
    'module' => 'stocktake',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
  ),
];
