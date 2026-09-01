<?php

declare(strict_types=1);

return [
    ep('EP-CORE-001', 'SP-00', 'GET', '/health', 'public', null, [
        'name' => 'Health check',
        'name_ar' => 'فحص الصحة',
        'auth' => false,
        'r' => ['status' => 'ok'],
        'd' => 'Public liveness. No tenant, no auth.',
    ]),

    ep('EP-PB-010', 'SP-17', 'GET', '/public/app-config', 'public', null, [
        'name' => 'App config / force-update',
        'name_ar' => 'إعداد التطبيق وفحص النسخة',
        'auth' => false,
        'q' => ['app' => 'retailer', 'platform' => 'android', 'version' => '1.4.2'],
        'r' => [
            'min_supported_version' => '1.3.0',
            'latest_version' => '1.4.2',
            'store_url' => 'https://play.google.com/store/apps/details?id=sy.b2b.retailer',
            'force_update' => false,
            'release_notes' => 'إصلاح المزامنة دون اتصال',
            'feature_flags' => ['offline_orders' => true, 'loyalty' => true],
            'maintenance' => ['enabled' => false, 'message_ar' => null, 'until' => null],
        ],
        'e' => [426 => 'upgrade_required', 503 => 'maintenance_mode'],
        'd' => 'Called on every app launch (REQ-AD-073, 074). Stale X-App-Version → 426.',
    ]),

    ep('EP-PB-001', 'SP-03', 'GET', '/public/refs', 'public', null, [
        'name' => 'Public reference snapshot',
        'name_ar' => 'المرجعيات العامة للتسجيل',
        'auth' => false,
        'q' => ['since' => ''],
        'r' => [
            'governorates' => [['id' => 1, 'name' => 'دمشق', 'order' => 1, 'status' => 'active']],
            'zones' => [['id' => 12, 'name' => 'المزة', 'governorate_id' => 1]],
            'activity_types' => [['id' => 3, 'name' => 'بقالة', 'icon' => 'grocery']],
            'root_categories' => [['id' => 10, 'name' => 'مواد غذائية']],
            'sale_units' => [['id' => 3, 'name' => 'قطعة', 'abbr' => 'pcs', 'default_factor' => 1]],
            'equipments' => [['id' => 1, 'name' => 'ثلاجة عرض']],
            'sync_cursor' => 'c_9f2a71',
        ],
        'd' => 'Registration screens cache this locally and refresh by cursor (REQ-AD-032, REQ-CM-016).',
    ]),

    ep('EP-CM-001', 'SP-01', 'POST', '/public/auth/request-otp', 'public', null, [
        'name' => 'Request OTP',
        'name_ar' => 'طلب رمز واتساب',
        'auth' => false,
        'b' => [
            'phone' => '+963933000000',
            'purpose' => 'login',
            'client' => 'retailer-android',
        ],
        'r' => [
            'otp_id' => 'otp_9f2a71',
            'channel_used' => 'whatsapp',
            'expires_in' => 300,
            'resend_after' => 60,
        ],
        'e' => [422 => 'validation_failed', 429 => 'rate_limited'],
        'd' => 'purpose: register|login. Rate: 3/hour per phone, 10/hour per device, 30/hour per IP (REQ-CM-055). Phone E.164 Syrian mobile.',
    ]),

    ep('EP-CM-002', 'SP-01', 'POST', '/public/auth/verify-otp', 'public', null, [
        'name' => 'Verify OTP',
        'name_ar' => 'تحقق رمز الدخول',
        'auth' => false,
        'b' => [
            'otp_id' => '{{otp_id}}',
            'code' => '482193',
            'device_id' => '{{deviceId}}',
            'device_name' => 'Redmi Note 13',
            'platform' => 'android',
        ],
        'r' => [
            'token' => '12|xxxxx',
            'is_new_user' => false,
            'user_type' => 'retailer',
            'profile_completed' => true,
            'user' => [
                'id' => 481,
                'name' => 'أبو خالد',
                'phone' => '+963933000000',
                'shop_name' => 'بقالية النور',
                'zone' => ['id' => 12, 'name' => 'المزة'],
                'activity_type' => ['id' => 3, 'name' => 'بقالة'],
                'points' => 1240,
                'tier' => 'silver',
            ],
        ],
        'e' => [401 => 'otp_invalid', 429 => 'rate_limited'],
        'd' => '5 attempts per otp_id. New user returns is_new_user=true without a full profile.',
    ]),

    ep('EP-CM-003', 'SP-01', 'POST', '/public/auth/resend-otp', 'public', null, [
        'name' => 'Resend OTP',
        'name_ar' => 'إعادة إرسال الرمز',
        'auth' => false,
        'b' => [
            'otp_id' => '{{otp_id}}',
            'prefer_channel' => 'sms',
        ],
        'r' => [
            'channel_used' => 'sms',
            'resend_after' => 60,
        ],
        'e' => [429 => 'rate_limited', 404 => 'not_found'],
        'd' => 'prefer_channel: whatsapp|sms. Falls back to SMS if WhatsApp fails.',
    ]),
];
