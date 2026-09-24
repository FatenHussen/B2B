<?php

declare(strict_types=1);

return [
    ep('EP-AD-001', 'SP-01', 'POST', '/platform/auth/login', 'platform', null, [
        'name' => 'Platform login',
        'name_ar' => 'دخول المنصة',
        'auth' => false,
        'b' => ['email' => 'admin@platform.sy', 'password' => '********'],
        'r' => ['requires_2fa' => true, 'challenge_token' => 'cht_8f3a'],
        'e' => [401 => 'unauthenticated', 403 => 'requires_2fa'],
    ]),

    ep('EP-AD-002', 'SP-01', 'POST', '/platform/auth/2fa/verify', 'platform', null, [
        'name' => 'Verify 2FA',
        'name_ar' => 'تحقق المصادقة الثنائية',
        'auth' => false,
        'b' => ['challenge_token' => 'cht_8f3a', 'code' => '123456'],
        'r' => [
            'token' => '1|platform_xxxxx',
            'user' => [
                'id' => 1,
                'name' => 'منصّة',
                'email' => 'admin@platform.sy',
                'roles' => ['platform_admin'],
                'permissions' => ['ad.iam.view_catalog'],
            ],
            'expires_at' => '2026-03-01T21:12:44+03:00',
        ],
        'e' => [401 => 'otp_invalid'],
    ]),

    ep('EP-AD-003', 'SP-01', 'POST', '/platform/auth/logout', 'platform', null, [
        'name' => 'Logout',
        'name_ar' => 'تسجيل الخروج',
        'b' => new stdClass(),
        'r' => ['success' => true],
    ]),

    ep('EP-AD-004', 'SP-01', 'GET', '/platform/auth/me', 'platform', null, [
        'name' => 'Current platform user',
        'name_ar' => 'المستخدم الحالي',
        'r' => [
            'id' => 1,
            'name' => 'منصّة',
            'email' => 'admin@platform.sy',
            'roles' => ['platform_admin'],
            'permissions' => ['ad.dashboard.view'],
            'two_factor_enabled' => true,
            'last_login_at' => '2026-03-01T09:12:44+03:00',
        ],
    ]),

    ep('EP-AD-005', 'SP-01', 'POST', '/platform/auth/confirm-password', 'platform', null, [
        'name' => 'Confirm password',
        'name_ar' => 'تأكيد كلمة المرور',
        'b' => ['password' => '********'],
        'r' => ['confirmed_until' => '2026-03-01T09:27:44+03:00'],
        'd' => 'Valid 15 minutes (BR-AD-04). Required before critical actions.',
        'e' => [401 => 'unauthenticated', 403 => 'requires_password_confirm'],
    ]),

    ep('EP-AD-005A', 'SP-01', 'POST', '/platform/auth/request-otp', 'platform', null, [
        'name' => 'Request platform step-up OTP',
        'name_ar' => 'طلب رمز تحقق لإجراء حساس',
        'b' => ['purpose' => 'platform_channel_delete'],
        'r' => [
            'mode' => 'whatsapp',
            'otp_id' => 'otp_ab12cd',
            'expires_in' => 300,
            'resend_after' => 60,
            'channel_used' => 'whatsapp',
        ],
        'd' => 'BF-05. Before EP-AD-058: if the actor has 2FA, mode=totp and no SMS is sent; otherwise a phone OTP is issued. Local/testing may return mode=bypass.',
        'e' => [401 => 'unauthenticated', 422 => 'validation_failed'],
    ]),

    ep('EP-AD-006', 'SP-01', 'GET', '/platform/auth/sessions', 'platform', null, [
        'name' => 'List sessions',
        'name_ar' => 'جلسات الدخول',
        'r' => [
            ['id' => 'ses_1', 'ip' => '185.1.2.3', 'agent' => 'Chrome 131', 'last_active_at' => '2026-03-01T09:12:44+03:00', 'current' => true],
        ],
    ]),

    ep('EP-AD-007', 'SP-01', 'DELETE', '/platform/auth/sessions/{id}', 'platform', null, [
        'name' => 'Revoke session',
        'name_ar' => 'إنهاء جلسة',
        'r' => ['success' => true],
        'e' => [401 => 'token_revoked'],
    ]),

    ep('EP-AD-010', 'SP-02', 'GET', '/platform/iam/permissions', 'platform', 'ad.iam.view_catalog', [
        'name' => 'Permission catalog',
        'name_ar' => 'كتالوج الصلاحيات',
        'q' => listQuery(['filter[system]' => 'platform', 'filter[severity]' => 'critical']),
        'r' => [[
            'code' => 'ad.channels.delete',
            'name_ar' => 'حذف قناة',
            'system' => 'platform',
            'module' => 'channels',
            'severity' => 'critical',
            'dual_approval' => true,
            'delegatable' => false,
            'roles_count' => 1,
            'users_count' => 2,
        ]],
    ]),

    ep('EP-AD-011', 'SP-02', 'GET', '/platform/iam/permissions/{code}/holders', 'platform', 'ad.iam.view_catalog', [
        'name' => 'Permission holders',
        'name_ar' => 'حاملو الصلاحية',
        'r' => [
            'roles' => [['id' => 1, 'key' => 'platform_admin']],
            'users' => [['id' => 1, 'name' => 'منصّة']],
            'recent_usage' => [['at' => '2026-03-01T09:00:00+03:00', 'actor' => 1, 'action' => 'simulate']],
        ],
    ]),

    ep('EP-AD-012', 'SP-02', 'GET', '/platform/iam/roles', 'platform', 'ad.iam.view_catalog', [
        'name' => 'List roles',
        'name_ar' => 'الأدوار',
        'q' => listQuery(['filter[system]' => 'channel']),
        'r' => [[
            'id' => 4,
            'key' => 'channel_manager',
            'name' => 'مدير القناة',
            'system' => 'channel',
            'status' => 'active',
            'is_builtin' => true,
            'permissions_count' => 80,
            'users_count' => 12,
        ]],
    ]),

    ep('EP-AD-013', 'SP-02', 'POST', '/platform/iam/roles', 'platform', 'ad.iam.role_create', [
        'name' => 'Create role',
        'name_ar' => 'إنشاء دور',
        'b' => [
            'name' => 'مشرف كتالوج',
            'key' => 'catalog_supervisor',
            'system' => 'channel',
            'description' => 'صلاحيات الكتالوج دون المالية',
            'permissions' => ['sc.catalog.view', 'sc.catalog.create', 'sc.catalog.update'],
            'copy_from_role_id' => null,
        ],
        'r' => ['id' => 22, 'status' => 'draft', 'sod_conflicts' => []],
        'dual' => true,
    ]),

    ep('EP-AD-014', 'SP-02', 'POST', '/platform/iam/roles/{id}/approve', 'platform', 'ad.iam.role_approve', [
        'name' => 'Approve role',
        'name_ar' => 'اعتماد الدور',
        'b' => ['reason' => 'مراجعة SOD مكتملة'],
        'r' => ['status' => 'active'],
        'e' => [403 => 'sod_violation'],
        'd' => 'Rejected if approver is the creator (SOD-05).',
        'dual' => true,
        'crit' => true,
    ]),

    ep('EP-AD-015', 'SP-02', 'PUT', '/platform/iam/roles/{id}/permissions', 'platform', 'ad.iam.role_create', [
        'name' => 'Replace role permissions',
        'name_ar' => 'تحديث صلاحيات الدور',
        'b' => [
            'permissions' => ['sc.catalog.view', 'sc.catalog.update'],
            'reason' => 'إزالة صلاحية الإنشاء',
        ],
        'r' => ['changed' => ['sc.catalog.create'], 'sod_conflicts' => []],
        'dual' => true,
    ]),

    ep('EP-AD-016', 'SP-02', 'POST', '/platform/iam/assignments', 'platform', 'ad.iam.role_assign', [
        'name' => 'Assign roles',
        'name_ar' => 'إسناد أدوار',
        'b' => [
            'user_ids' => [10, 11],
            'role_id' => 4,
            'expires_at' => null,
            'reason' => 'تعيين مديري قناة جدد',
        ],
        'r' => [
            'assigned' => [10, 11],
            'rejected' => [],
        ],
        'd' => 'Max 50 user_ids per call.',
    ]),

    ep('EP-AD-017', 'SP-02', 'DELETE', '/platform/iam/assignments', 'platform', 'ad.iam.role_assign', [
        'name' => 'Revoke role assignment',
        'name_ar' => 'سحب دور',
        'b' => ['user_id' => 10, 'role_id' => 4, 'reason' => 'انتقال لقناة أخرى'],
        'r' => ['success' => true],
    ]),

    ep('EP-AD-018', 'SP-02', 'POST', '/platform/iam/simulate', 'platform', 'ad.iam.simulate', [
        'name' => 'Simulate authorization',
        'name_ar' => 'محاكاة صلاحية',
        'b' => [
            'user_type' => 'channel',
            'user_id' => 10,
            'permission' => 'sc.orders.confirm',
            'resource_type' => 'sub_order',
            'resource_id' => 9001,
        ],
        'r' => [
            'allowed' => true,
            'decision_path' => [
                ['layer' => 'guard', 'result' => 'pass', 'reason' => 'channel'],
                ['layer' => 'permission', 'result' => 'pass', 'reason' => 'role:channel_manager'],
                ['layer' => 'tenant', 'result' => 'pass', 'reason' => 'same channel'],
            ],
        ],
    ]),

    ep('EP-AD-019', 'SP-02', 'POST', '/platform/iam/temp-grants', 'platform', 'ad.iam.grant_temp', [
        'name' => 'Request temp grant',
        'name_ar' => 'منح مؤقت',
        'b' => [
            'user_id' => 10,
            'permission' => 'sc.finance.void_invoice',
            'duration_minutes' => 60,
            'reason' => 'تصحيح فاتورة مكررة بعد حادث مزامنة — تذكرة SUP-441',
        ],
        'r' => [
            'id' => 55,
            'status' => 'pending_approval',
            'expires_request_at' => '2026-03-01T13:12:44+03:00',
        ],
        'd' => 'duration_minutes ≤ 240. reason ≥ 20 chars.',
        'dual' => true,
        'crit' => true,
    ]),

    ep('EP-AD-020', 'SP-02', 'POST', '/platform/iam/temp-grants/{id}/approve', 'platform', 'ad.iam.grant_temp', [
        'name' => 'Approve temp grant',
        'name_ar' => 'اعتماد المنح المؤقت',
        'b' => ['decision' => 'approve', 'reason' => 'تم التحقق من التذكرة'],
        'r' => ['granted_until' => '2026-03-01T10:12:44+03:00'],
        'dual' => true,
    ]),

    ep('EP-AD-021', 'SP-02', 'GET', '/platform/iam/sod-rules', 'platform', 'ad.iam.sod_rules', [
        'name' => 'SoD rules',
        'name_ar' => 'قواعد فصل المهام',
        'r' => [[
            'code' => 'SOD-01',
            'permission_a' => 'sc.orders.confirm',
            'permission_b' => 'sc.finance.payment',
            'reason' => 'لا يجتمع تأكيد الطلب مع تسجيل الدفعة',
            'exceptions' => [],
        ]],
    ]),

    ep('EP-AD-022', 'SP-02', 'GET', '/platform/audit', 'platform', 'ad.audit.view', [
        'name' => 'Audit log',
        'name_ar' => 'سجل التدقيق',
        'q' => listQuery([
            'filter[actor]' => '1',
            'filter[action]' => 'role.assign',
            'filter[channel_id]' => '1',
            'filter[date_from]' => '2026-02-01',
            'filter[date_to]' => '2026-03-01',
        ]),
        'r' => [[
            'at' => '2026-03-01T09:12:44+03:00',
            'actor' => 1,
            'action' => 'role.assign',
            'entity_type' => 'user',
            'entity_id' => 10,
            'before' => null,
            'after' => ['role_id' => 4],
            'ip' => '185.1.2.3',
            'impersonated' => false,
        ]],
    ]),

    ep('EP-AD-023', 'SP-02', 'POST', '/platform/audit/export', 'platform', 'ad.audit.export', [
        'name' => 'Export audit log',
        'name_ar' => 'تصدير التدقيق',
        'b' => [
            'filters' => ['date_from' => '2026-02-01', 'date_to' => '2026-03-01'],
            'format' => 'xlsx',
        ],
        'r' => ['job_id' => 'job_audit_1'],
        'd' => 'The export itself is audited (REQ-AD-062).',
        'crit' => true,
    ]),

    ep('EP-AD-024', 'SP-02', 'POST', '/platform/iam/reviews', 'platform', 'ad.audit.review', [
        'name' => 'Start access review',
        'name_ar' => 'حملة مراجعة صلاحيات',
        'b' => ['quarter' => '2026-Q1', 'scope' => 'platform'],
        'r' => ['campaign_id' => 3, 'items_count' => 42],
    ]),
];
