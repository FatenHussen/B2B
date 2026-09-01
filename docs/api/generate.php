<?php

declare(strict_types=1);

/**
 * Builds exportable API artifacts from the DOC-11B contract:
 *   - b2b-api.catalog.json
 *   - b2b-api.postman_collection.json
 *   - b2b-api.openapi.json
 *   - environments/*.postman_environment.json
 *
 * Source: DOC-11B (binding API catalog) + DOC-12A/12B extras + DOC-10 envelope.
 */

function ep(
    string $code,
    string $sprint,
    string $method,
    string $path,
    string $audience,
    ?string $permission = null,
    array $x = [],
): array {
    $write = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

    return [
        'code' => $code,
        'sprint' => $sprint,
        'method' => $method,
        'path' => $path,
        'full_path' => '/api/v1'.$path,
        'audience' => $audience,
        'guard' => match ($audience) {
            'public' => null,
            'platform' => 'platform',
            'channel' => 'channel',
            'warehouse' => 'warehouse',
            'retailer', 'rep', 'app' => 'app',
            default => $audience,
        },
        'permission' => $permission,
        'auth' => array_key_exists('auth', $x) ? (bool) $x['auth'] : $audience !== 'public',
        'name' => $x['name'] ?? $code,
        'name_ar' => $x['name_ar'] ?? '',
        'description' => $x['d'] ?? '',
        'query' => $x['q'] ?? [],
        'body' => $x['b'] ?? null,
        'response' => $x['r'] ?? null,
        'errors' => $x['e'] ?? [],
        'dual_approval' => (bool) ($x['dual'] ?? false),
        'critical' => (bool) ($x['crit'] ?? false),
        'multipart' => (bool) ($x['mp'] ?? false),
        'integration' => $x['in'] ?? null,
        'write' => $write,
        'folder' => $x['folder'] ?? '',
    ];
}

function moduleAr(array $ep): string
{
    $path = $ep['path'];
    $aud = $ep['audience'];

    if (str_contains($path, '/auth') || str_contains($path, '/register') || str_contains($path, '/session') || str_contains($path, '/device-login') || str_contains($path, '/app-config') || ($aud !== 'platform' && str_contains($path, '/refs'))) {
        return '00. الدخول والتسجيل';
    }
    if (str_contains($path, '/health')) {
        return '00. صحة الخدمة';
    }
    if (str_contains($path, '/iam') || str_contains($path, '/audit')) {
        return '01. الصلاحيات والتدقيق';
    }
    if ($aud === 'retailer' && str_contains($path, '/home')) {
        return '01. الرئيسية';
    }
    if (str_contains($path, '/dashboard')) {
        return '01. لوحة القيادة';
    }
    if (str_contains($path, '/refs')) {
        return '02. المرجعيات';
    }
    if (str_contains($path, '/brands') || str_contains($path, '/categories') || str_contains($path, '/products') || str_contains($path, '/catalog') || str_contains($path, '/shortages') || str_contains($path, '/favorite') || str_contains($path, '/customers') || ($aud === 'rep' && str_contains($path, '/zones'))) {
        return '02. الكتالوج';
    }
    if ($aud === 'platform' && (str_contains($path, '/channels') || str_contains($path, '/channel-applications'))) {
        return '03. القنوات';
    }
    if (str_contains($path, '/quote') || str_contains($path, '/price') || str_contains($path, '/pricing') || str_contains($path, 'discount-cap')) {
        return '03. التسعير';
    }
    if (str_contains($path, '/offers')) {
        return '04. العروض';
    }
    if (str_contains($path, '/plans') || str_contains($path, '/subscriptions') || str_contains($path, '/platform-invoices') || str_contains($path, '/dunning')) {
        return '04. الفوترة والخطط';
    }
    if (str_contains($path, '/inventory') || str_contains($path, '/stocktake') || str_contains($path, '/receiving')) {
        return '05. المخزون';
    }
    if (str_contains($path, '/features') || str_contains($path, '/app-versions') || str_contains($path, '/app-config')) {
        return '05. الميزات ونسخ التطبيقات';
    }
    if (str_contains($path, '/cart') || str_contains($path, '/sub-orders') || ($aud === 'retailer' && str_contains($path, '/orders')) || str_contains($path, '/assignments') || str_contains($path, '/scheduled-orders') || ($aud === 'rep' && preg_match('#/status$#', $path))) {
        return '06. السلة والطلبات';
    }
    if (str_contains($path, '/support')) {
        return '06. الدعم';
    }
    if (str_contains($path, '/settings') || str_contains($path, '/team') || preg_match('#^/platform/me(/|$)#', $path)) {
        return '09. الإعدادات والفريق';
    }
    if ($aud === 'platform' && str_contains($path, '/content')) {
        return '05. الميزات ونسخ التطبيقات';
    }
    if (str_contains($path, '/dashboard/alerts') || str_contains($path, '/dashboard/cards')) {
        return '01. لوحة القيادة';
    }
    if (str_contains($path, '/billing/')) {
        return '04. الفوترة والخطط';
    }
    if (str_contains($path, '/system')) {
        return '07. تشغيل النظام';
    }
    if (str_starts_with($path, '/warehouse') || str_contains($path, '/warehouse-receipts')) {
        if (str_contains($path, '/picking') || str_contains($path, '/packing') || str_contains($path, '/handover') || str_contains($path, '/queues') || str_contains($path, '/warehouse-receipts')) {
            return '07. التجهيز والعهدة';
        }
    }
    if (str_contains($path, '/deliver') || str_contains($path, '/locations/ping') || (str_contains($path, '/receipts') && ! str_contains($path, '/receipts/reserve'))) {
        return '08. التسليم والاستلام';
    }
    if (str_contains($path, '/reports') || str_contains($path, '/exports')) {
        return '08. التقارير';
    }
    if (str_contains($path, '/return') || str_contains($path, '/reps/') && str_contains($path, '/rate')) {
        return '09. المرتجعات والتقييم';
    }
    if (str_contains($path, '/invoice') || str_contains($path, '/payment') || str_contains($path, '/finance') || str_contains($path, '/wallet') || str_contains($path, '/account') || str_contains($path, '/debts') || str_contains($path, '/receivables') || str_contains($path, '/credit') || str_contains($path, '/settle') || str_contains($path, '/receipts/reserve')) {
        return '10. المالية';
    }
    if (str_contains($path, '/sync')) {
        return '11. المزامنة';
    }
    if (str_contains($path, '/notif') || str_contains($path, '/devices/push') || str_contains($path, '/broadcast')) {
        return '12. الإشعارات';
    }
    if (str_contains($path, '/content') || str_contains($path, '/banners') || str_contains($path, '/sliders')) {
        return '13. المحتوى';
    }
    if (str_contains($path, '/loyalty')) {
        return '14. النقاط';
    }

    return '99. أخرى';
}

function roleLabel(string $role): string
{
    return match ($role) {
        'retailer' => 'التاجر',
        'rep' => 'المندوب',
        'warehouse_keeper' => 'أمين المستودع',
        'channel_manager' => 'مدير القناة',
        'sales_manager' => 'مدير المبيعات',
        'catalog_manager' => 'مدير الكتالوج',
        'accountant' => 'المحاسب',
        'platform_admin' => 'مدير المنصة',
        'platform_ops' => 'مسؤول التشغيل',
        'platform_support' => 'موظف الدعم',
        'platform_finance' => 'محاسب المنصة',
        'platform_content' => 'مسؤول المحتوى',
        'platform_tech' => 'مسؤول التقنية',
        'platform_auditor' => 'المدقّق',
        default => $role,
    };
}

function appRoot(string $role): string
{
    return match ($role) {
        'retailer' => '01. تطبيق التاجر',
        'rep' => '02. تطبيق المندوب',
        'warehouse_keeper' => '03. واجهة المستودع — أمين المستودع',
        'channel_manager', 'sales_manager', 'catalog_manager', 'accountant', 'channel_auth' => '04. لوحة قناة التوريد',
        'platform_admin', 'platform_ops', 'platform_support', 'platform_finance', 'platform_content', 'platform_tech', 'platform_auditor', 'platform_auth' => '05. لوحة إدارة المنصة (السنترال)',
        default => '99. أخرى',
    };
}

function channelRolesOf(array $ep): array
{
    $path = $ep['path'];
    $isGet = $ep['method'] === 'GET';

    if (! ($ep['auth'] ?? true) || str_contains($path, '/auth')) {
        return ['channel_auth'];
    }

    $roles = ['channel_manager'];

    $add = function (array &$roles, string ...$extra): void {
        foreach ($extra as $role) {
            if (! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }
    };

    if (str_contains($path, '/dashboard')) {
        $add($roles, 'sales_manager', 'catalog_manager', 'accountant');

        return $roles;
    }
    if (str_contains($path, '/reports/margins')) {
        $add($roles, 'accountant');

        return $roles;
    }
    if (str_contains($path, '/reports') || str_contains($path, '/exports')) {
        $add($roles, 'sales_manager', 'accountant');

        return $roles;
    }
    if (str_contains($path, '/brands') || str_contains($path, '/categories') || str_contains($path, '/products') || str_contains($path, '/catalog')) {
        $add($roles, 'catalog_manager');
        if ($isGet) {
            $add($roles, 'sales_manager', 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, 'discount-cap')) {
        $add($roles, 'sales_manager');

        return $roles;
    }
    if (str_contains($path, '/price') || str_contains($path, '/pricing')) {
        $add($roles, 'catalog_manager');
        if ($isGet) {
            $add($roles, 'sales_manager', 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, '/offers')) {
        $add($roles, 'sales_manager', 'catalog_manager');
        if ($isGet) {
            $add($roles, 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, '/inventory')) {
        if ($isGet) {
            $add($roles, 'sales_manager', 'catalog_manager', 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, '/sub-orders')) {
        $add($roles, 'sales_manager');
        if ($isGet) {
            $add($roles, 'catalog_manager', 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, '/return')) {
        $add($roles, 'sales_manager');
        if ($isGet) {
            $add($roles, 'accountant');
        }

        return $roles;
    }
    if (str_contains($path, '/invoice') || str_contains($path, '/payment') || str_contains($path, '/finance') || str_contains($path, '/settle') || str_contains($path, '/credit')) {
        $add($roles, 'accountant');
        if ($isGet || str_contains($path, '/credit') || str_contains($path, '/settle')) {
            $add($roles, 'sales_manager');
        }

        return $roles;
    }
    if (str_contains($path, '/content') || str_contains($path, '/banner') || str_contains($path, '/slider') || str_contains($path, '/intro')) {
        $add($roles, 'catalog_manager');
        if ($isGet) {
            $add($roles, 'sales_manager');
        }

        return $roles;
    }

    return $roles;
}

function platformTeam(): array
{
    return ['platform_admin', 'platform_ops', 'platform_support', 'platform_finance', 'platform_content', 'platform_tech', 'platform_auditor'];
}

function platformRolesOf(array $ep): array
{
    $path = $ep['path'];
    $perm = (string) ($ep['permission'] ?? '');
    $isGet = $ep['method'] === 'GET';

    if (! ($ep['auth'] ?? true) || str_contains($path, '/auth')) {
        return ['platform_auth'];
    }

    if (preg_match('#^/platform/me(/|$)#', $path) || str_contains($path, '/health')) {
        return platformTeam();
    }

    $roles = ['platform_admin'];
    $add = function (array &$roles, string ...$extra): void {
        foreach ($extra as $role) {
            if (! in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }
    };

    if (str_contains($path, '/iam')) {
        if ($isGet || str_contains($path, '/simulate') || str_contains($path, '/reviews')) {
            $add($roles, 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/audit')) {
        $add($roles, 'platform_auditor');

        return $roles;
    }
    if (str_contains($path, '/dashboard') || str_contains($path, '/reports') || str_contains($path, '/exports')) {
        if ($isGet) {
            $add($roles, 'platform_ops', 'platform_support', 'platform_finance', 'platform_content', 'platform_tech', 'platform_auditor');
        } else {
            $add($roles, 'platform_ops', 'platform_finance');
        }

        return $roles;
    }
    if (str_contains($path, '/settings') || str_contains($path, '/team')) {
        return $roles;
    }
    if (str_contains($path, '/support') || str_contains($path, '/impersonate')) {
        $add($roles, 'platform_support');
        if ($isGet) {
            $add($roles, 'platform_ops', 'platform_finance', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/plans') || str_contains($path, '/subscriptions') || str_contains($path, '/platform-invoices') || str_contains($path, '/dunning') || str_contains($path, '/billing')) {
        $add($roles, 'platform_finance');
        if ($isGet) {
            $add($roles, 'platform_ops', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/system') || str_contains($path, '/maintenance')) {
        $add($roles, 'platform_tech');
        if ($isGet) {
            $add($roles, 'platform_ops', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/content') || str_contains($path, '/app-versions') || str_contains($path, '/legal') || str_contains($path, '/help')) {
        if (str_contains($path, 'force-update')) {
            return $roles;
        }
        $add($roles, 'platform_content');
        if (str_contains($path, '/app-versions') && ! $isGet) {
            $add($roles, 'platform_tech');
        }
        if ($isGet) {
            $add($roles, 'platform_ops', 'platform_tech', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/notif')) {
        if (str_contains($path, 'broadcast')) {
            return $roles;
        }
        $add($roles, 'platform_ops', 'platform_support', 'platform_content');
        if ($isGet) {
            $add($roles, 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/features')) {
        $add($roles, 'platform_ops');
        if ($isGet) {
            $add($roles, 'platform_tech', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/refs')) {
        $add($roles, 'platform_ops');
        if ($isGet) {
            $add($roles, 'platform_support', 'platform_auditor');
        }

        return $roles;
    }
    if (str_contains($path, '/channels') || str_contains($path, '/channel-applications')) {
        if ($ep['method'] === 'DELETE' || str_contains($perm, 'delete') || (str_contains($path, '/export') && ! str_contains($path, 'channels/export'))) {
            return $roles;
        }
        if (str_contains($path, '/channels/export') && $ep['method'] === 'POST' && ! str_contains($path, '{id}')) {
            $add($roles, 'platform_ops');

            return $roles;
        }
        if (str_contains($path, '{id}/export')) {
            return $roles;
        }
        $add($roles, 'platform_ops');
        if ($isGet) {
            $add($roles, 'platform_support', 'platform_finance', 'platform_tech', 'platform_auditor');
        }

        return $roles;
    }

    return $roles;
}

function rolesOf(array $ep): array
{
    return match ($ep['audience']) {
        'retailer' => ['retailer'],
        'rep' => ['rep'],
        'app' => ['retailer', 'rep'],
        'warehouse' => ['warehouse_keeper'],
        'channel' => array_values(array_filter(
            channelRolesOf($ep),
            fn (string $r): bool => $r !== 'channel_auth',
        )) ?: ['channel_manager', 'sales_manager', 'catalog_manager', 'accountant'],
        'platform' => array_values(array_filter(
            platformRolesOf($ep),
            fn (string $r): bool => $r !== 'platform_auth',
        )) ?: platformTeam(),
        'public' => str_contains($ep['path'], '/health')
            ? ['platform_admin', 'platform_tech']
            : ['retailer', 'rep'],
        default => [],
    };
}

function appsOf(array $ep): array
{
    return match ($ep['audience']) {
        'retailer', 'rep' => [$ep['audience'].'_app'],
        'app' => ['retailer_app', 'rep_app'],
        'warehouse' => ['warehouse'],
        'channel' => ['channel_dashboard'],
        'platform' => ['platform_admin'],
        'public' => str_contains($ep['path'], '/health')
            ? ['platform_admin']
            : ['retailer_app', 'rep_app'],
        default => [],
    };
}

function annotateEndpoint(array $ep): array
{
    $ep['module'] = moduleAr($ep);
    $ep['roles'] = rolesOf($ep);
    $ep['apps'] = appsOf($ep);
    $ep['folder'] = appRoot($ep['roles'][0] ?? 'platform_admin').' / '.$ep['module'];

    return $ep;
}

function cloneForRole(array $ep, string $role): array
{
    $clone = $ep;
    $clone['placement_role'] = $role;

    if (in_array($role, ['retailer', 'rep'], true)) {
        $clone['audience'] = $role;
        if (is_array($clone['body'] ?? null) && isset($clone['body']['client'])) {
            $clone['body']['client'] = $role.'-android';
        }
        if (isset($clone['query']['app'])) {
            $clone['query']['app'] = $role;
        }
    }

    $root = appRoot($role);
    $module = $ep['module'] ?? moduleAr($ep);

    if ($role === 'channel_auth') {
        $clone['folder'] = $root.' / 00. الدخول (كل الأدوار) / '.$module;
    } elseif ($role === 'platform_auth') {
        $clone['folder'] = $root.' / 00. الدخول (كل أدوار المنصة) / '.$module;
    } elseif (in_array($role, ['channel_manager', 'sales_manager', 'catalog_manager', 'accountant'], true)) {
        $order = [
            'channel_manager' => '01. مدير القناة',
            'sales_manager' => '02. مدير المبيعات',
            'catalog_manager' => '03. مدير الكتالوج',
            'accountant' => '04. المحاسب',
        ];
        $clone['folder'] = $root.' / '.$order[$role].' / '.$module;
    } elseif (str_starts_with($role, 'platform_')) {
        $order = [
            'platform_admin' => '01. مدير المنصة',
            'platform_ops' => '02. مسؤول التشغيل',
            'platform_support' => '03. موظف الدعم',
            'platform_finance' => '04. محاسب المنصة',
            'platform_content' => '05. مسؤول المحتوى',
            'platform_tech' => '06. مسؤول التقنية',
            'platform_auditor' => '07. المدقّق',
        ];
        $clone['folder'] = $root.' / '.($order[$role] ?? $role).' / '.$module;
    } else {
        $clone['folder'] = $root.' / '.$module;
    }

    return $clone;
}

function expandForPostman(array $endpoints): array
{
    $out = [];
    foreach ($endpoints as $ep) {
        if ($ep['audience'] === 'channel' && in_array('channel_auth', channelRolesOf($ep), true)) {
            $out[] = cloneForRole($ep, 'channel_auth');

            continue;
        }
        if ($ep['audience'] === 'platform' && in_array('platform_auth', platformRolesOf($ep), true)) {
            $out[] = cloneForRole($ep, 'platform_auth');

            continue;
        }

        $roles = $ep['roles'] !== [] ? $ep['roles'] : rolesOf($ep);
        foreach ($roles as $role) {
            $out[] = cloneForRole($ep, $role);
        }
    }

    return $out;
}

function folderDescription(string $name): ?string
{
    return match (true) {
        str_contains($name, 'تطبيق التاجر') => "الدور: تاجر\nالحارس: app\nالدخول: هاتف + رمز واتساب مربوط بالجهاز\nX-Client: retailer-android | retailer-ios",
        str_contains($name, 'تطبيق المندوب') => "الدور: مندوب\nالحارس: app\nالدخول: هاتف + رمز واتساب مربوط بالجهاز\nX-Client: rep-android | rep-ios",
        str_contains($name, 'واجهة المستودع') => "الدور: أمين المستودع\nالحارس: warehouse\nالدخول: جهاز مسجّل + رمز قصير\nX-Client: warehouse-desktop | warehouse-web",
        str_contains($name, 'لوحة قناة التوريد') && ! str_contains($name, 'مدير') && ! str_contains($name, 'المحاسب') && ! str_contains($name, 'الدخول') => "لوحة ويب قناة التوريد.\nكل مجلد = دور واحد حسب صلاحياته.\nالحارس: channel · هاتف + واتساب\nX-Client: channel-web",
        str_contains($name, 'دخول (كل أدوار المنصة)') => 'مشترك لكل أدوار المنصة: بريد + كلمة مرور + 2FA. الحارس platform.',
        str_contains($name, 'دخول (كل الأدوار)') => 'مشترك لكل أدوار القناة قبل تحديد الدور من التوكن.',
        str_contains($name, 'مدير المنصة') => 'platform_admin — كل الكتالوج عبر قاعدة تجاوز مركزية. خاضع لفصل المهام والتأكيد المزدوج.',
        str_contains($name, 'مسؤول التشغيل') => 'القنوات والمرجعيات والميزات والقيادة. بلا فوترة ولا صلاحيات ولا أمان ولا حذف نهائي.',
        str_contains($name, 'موظف الدعم') => 'البحث وبطاقة 360 والانتحال والبلاغات. بلا فوترة ولا مرجعيات ولا إعدادات ولا سجل تدقيق (SOD-06).',
        str_contains($name, 'محاسب المنصة') => 'الباقات والفواتير والتحصيل المتعثر وتقرير الإيراد. بلا تشغيل قنوات ولا دعم.',
        str_contains($name, 'مسؤول المحتوى') => 'الشروط والأدلة والانترو والقوالب والإصدارات. بلا فرض تحديث ولا بث عام.',
        str_contains($name, 'مسؤول التقنية') => 'الطوابير والأخطاء والتكاملات والنسخ الاحتياطي. بلا قنوات ولا فوترة ولا دعم.',
        str_contains($name, 'المدقّق') => 'سجل التدقيق والمراجعة الربعية والقراءة العامة. بلا أي صلاحية كتابة.',
        str_contains($name, 'مدير القناة') => 'channel_manager — كل وحدات القناة.',
        str_contains($name, 'مدير المبيعات') => 'sales_manager — الطلبات، المندوبون، التجار، العروض، المرتجعات. بدون تسوية مخزون أو مالية كاملة.',
        str_contains($name, 'مدير الكتالوج') => 'catalog_manager — الكتالوج، التسعير، العروض، المحتوى.',
        str_contains($name, '04. المحاسب') => 'accountant — الفواتير، الدفعات، الذمم، تسوية المندوب، الإشعارات الدائنة.',
        str_contains($name, 'لوحة إدارة المنصة') => "السنترال — DOC-07.\nسبعة أدوار: مدير المنصة / تشغيل / دعم / مالية / محتوى / تقنية / مدقّق.\nالحارس: platform · بريد + كلمة مرور + 2FA\nX-Client: platform-web",
        default => null,
    };
}

function defaultHeaders(array $ep): array
{
    $aud = $ep['audience'];
    $headers = [
        'Accept' => 'application/json',
        'Accept-Language' => '{{acceptLanguage}}',
        'X-Client' => match ($aud) {
            'platform' => 'platform-web',
            'channel' => 'channel-web',
            'warehouse' => 'warehouse-desktop',
            'retailer' => 'retailer-android',
            'rep' => 'rep-android',
            default => '{{client}}',
        },
    ];

    if ($ep['auth']) {
        $headers['Authorization'] = 'Bearer {{token}}';
    }

    if (in_array($aud, ['warehouse', 'retailer', 'rep', 'app'], true)
        || ($aud === 'public' && str_contains($ep['path'], '/auth'))) {
        $headers['X-Device-Id'] = '{{deviceId}}';
    }

    if (in_array($aud, ['retailer', 'rep', 'app'], true)
        || ($aud === 'public' && (str_contains($ep['path'], '/auth') || str_contains($ep['path'], '/app-config')))) {
        $headers['X-App-Version'] = '{{appVersion}}';
    }

    if ($aud === 'channel' || ($aud === 'platform' && str_contains($ep['path'], '/channels/'))) {
        $headers['X-Channel-Id'] = '{{channelId}}';
    }

    if ($ep['write']) {
        $headers['X-Idempotency-Key'] = '{{$guid}}';
        if (! $ep['multipart']) {
            $headers['Content-Type'] = 'application/json';
        }
    }

    return $headers;
}

function envelope(mixed $data, array $meta = []): array
{
    return [
        'data' => $data,
        'meta' => array_merge(['server_time' => '2026-03-01T09:12:44+03:00'], $meta),
    ];
}

function errorEnvelope(string $code, string $message, array $details = []): array
{
    $error = ['code' => $code, 'message' => $message];
    if ($details !== []) {
        $error['details'] = $details;
    }

    return ['error' => $error];
}

function listQuery(array $extra = []): array
{
    return array_merge([
        'page' => '1',
        'per_page' => '25',
        'sort' => '-created_at',
        'search' => '',
    ], $extra);
}

function jsonBody(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function pathToPostmanUrl(string $fullPath, array $query): array
{
    $trimmed = trim($fullPath, '/');
    $segments = $trimmed === '' ? [] : explode('/', $trimmed);
    $path = array_map(function (string $seg): string {
        if (preg_match('/^\{(.+)\}$/', $seg, $m)) {
            return ':'.$m[1];
        }

        return $seg;
    }, $segments);

    $rawPath = implode('/', array_map(function (string $seg): string {
        return str_starts_with($seg, ':') ? '{{'.substr($seg, 1).'}}' : $seg;
    }, $path));

    $queryPairs = [];
    $rawQuery = '';
    if ($query !== []) {
        foreach ($query as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $queryPairs[] = ['key' => (string) $k, 'value' => (string) $v];
        }
        if ($queryPairs !== []) {
            $rawQuery = '?'.http_build_query(array_column($queryPairs, 'value', 'key'));
        }
    }

    return [
        'raw' => '{{baseUrl}}/'.$rawPath.$rawQuery,
        'host' => ['{{baseUrl}}'],
        'path' => $path,
        'query' => $queryPairs,
        'variable' => array_values(array_filter(array_map(function (string $seg): ?array {
            if (! str_starts_with($seg, ':')) {
                return null;
            }
            $name = substr($seg, 1);

            return ['key' => $name, 'value' => '{{'.$name.'}}'];
        }, $path))),
    ];
}

function postmanHeaders(array $ep): array
{
    $out = [];
    foreach (defaultHeaders($ep) as $key => $value) {
        $disabled = $key === 'X-Channel-Id' && $ep['audience'] === 'platform';
        $item = ['key' => $key, 'value' => $value, 'type' => 'text'];
        if ($disabled) {
            $item['disabled'] = true;
        }
        $out[] = $item;
    }

    return $out;
}

function postmanBody(array $ep): ?array
{
    if (! $ep['write'] || $ep['body'] === null) {
        return null;
    }

    if ($ep['multipart']) {
        $form = [];
        foreach ($ep['body'] as $k => $v) {
            $form[] = [
                'key' => (string) $k,
                'type' => $k === 'file' ? 'file' : 'text',
                'src' => $k === 'file' ? [] : null,
                'value' => $k === 'file' ? null : (is_scalar($v) ? (string) $v : jsonBody($v)),
            ];
        }

        return ['mode' => 'formdata', 'formdata' => $form];
    }

    return [
        'mode' => 'raw',
        'raw' => jsonBody($ep['body']),
        'options' => ['raw' => ['language' => 'json']],
    ];
}

function postmanRequest(array $ep): array
{
    $desc = [];
    $desc[] = '**'.$ep['code'].'** · '.$ep['sprint'].($ep['name_ar'] !== '' ? ' · '.$ep['name_ar'] : '');
    if (! empty($ep['placement_role']) && ! in_array($ep['placement_role'], ['channel_auth', 'platform_auth'], true)) {
        $desc[] = 'الدور: **'.roleLabel($ep['placement_role']).'** (`'.$ep['placement_role'].'`)';
    } elseif (! empty($ep['roles'])) {
        $desc[] = 'الأدوار: '.implode(', ', array_map('roleLabel', $ep['roles']));
    }
    if ($ep['permission']) {
        $desc[] = 'الصلاحية: `'.$ep['permission'].'`';
    }
    if ($ep['guard']) {
        $desc[] = 'الحارس: `'.$ep['guard'].'`';
    }
    if ($ep['critical']) {
        $desc[] = 'Critical operation — dual confirmation required in UI.';
    }
    if ($ep['dual_approval']) {
        $desc[] = 'May return `meta.requires_dual_approval` and create an approval request instead of executing.';
    }
    if ($ep['integration']) {
        $desc[] = 'Integration: '.$ep['integration'];
    }
    if ($ep['description'] !== '') {
        $desc[] = $ep['description'];
    }
    if ($ep['errors'] !== []) {
        $lines = [];
        foreach ($ep['errors'] as $status => $code) {
            $lines[] = "- HTTP {$status}: `{$code}`";
        }
        $desc[] = "Errors:\n".implode("\n", $lines);
    }

    $item = [
        'name' => $ep['code'].' '.$ep['name'],
        'request' => [
            'method' => $ep['method'],
            'header' => postmanHeaders($ep),
            'url' => pathToPostmanUrl($ep['full_path'], $ep['query']),
            'description' => implode("\n\n", $desc),
        ],
        'response' => [],
    ];

    $body = postmanBody($ep);
    if ($body !== null) {
        $item['request']['body'] = $body;
    }

    if (! $ep['auth']) {
        $item['request']['auth'] = ['type' => 'noauth'];
    }

    if ($ep['response'] !== null) {
        $item['response'][] = [
            'name' => '200 '.$ep['code'],
            'originalRequest' => $item['request'],
            'status' => 'OK',
            'code' => 200,
            '_postman_previewlanguage' => 'json',
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
            ],
            'body' => jsonBody(envelope($ep['response'])),
        ];
    }

    $tests = tokenTestScript($ep);
    if ($tests !== null) {
        $item['event'] = [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => explode("\n", $tests)],
        ]];
    }

    return $item;
}

function tokenTestScript(array $ep): ?string
{
    $codes = [
        'EP-AD-002', 'EP-CM-001', 'EP-CM-002', 'EP-CH-001', 'EP-CH-002',
        'EP-RT-001', 'EP-RP-001', 'EP-WH-001', 'EP-CM-050',
    ];

    if (! in_array($ep['code'], $codes, true)) {
        return null;
    }

    return <<<'JS'
const json = pm.response.json();
const data = json?.data || {};
if (data.token) pm.collectionVariables.set('token', data.token);
if (data.otp_id) pm.collectionVariables.set('otp_id', data.otp_id);
if (data.receipt_no) pm.collectionVariables.set('receipt_no', data.receipt_no);
if (data.challenge_token) pm.collectionVariables.set('challenge_token', data.challenge_token);
JS;
}

function folderItem(string $name, array $children): array
{
    $item = ['name' => $name, 'item' => $children];
    $desc = folderDescription($name);
    if ($desc !== null) {
        $item['description'] = $desc;
    }

    return $item;
}

function nestFolders(array $endpoints): array
{
    $tree = [];
    foreach ($endpoints as $ep) {
        $parts = explode(' / ', $ep['folder']);
        if (count($parts) >= 3) {
            $tree[$parts[0]][$parts[1]][$parts[2]][] = postmanRequest($ep);
        } else {
            $root = $parts[0] ?? '99. أخرى';
            $mod = $parts[1] ?? '99. أخرى';
            $tree[$root]['__flat'][$mod][] = postmanRequest($ep);
        }
    }

    $rootOrder = [
        '01. تطبيق التاجر',
        '02. تطبيق المندوب',
        '03. واجهة المستودع — أمين المستودع',
        '04. لوحة قناة التوريد',
        '05. لوحة إدارة المنصة (السنترال)',
    ];

    $items = [];
    $roots = array_unique([...$rootOrder, ...array_keys($tree)]);
    foreach ($roots as $root) {
        if (! isset($tree[$root])) {
            continue;
        }
        $children = [];
        if (isset($tree[$root]['__flat'])) {
            ksort($tree[$root]['__flat']);
            foreach ($tree[$root]['__flat'] as $mod => $reqs) {
                $children[] = folderItem($mod, $reqs);
            }
        }
        $roleKeys = array_filter(array_keys($tree[$root]), fn ($k) => $k !== '__flat');
        sort($roleKeys);
        foreach ($roleKeys as $role) {
            $mods = $tree[$root][$role];
            ksort($mods);
            $modItems = [];
            foreach ($mods as $mod => $reqs) {
                $modItems[] = folderItem($mod, $reqs);
            }
            $children[] = folderItem($role, $modItems);
        }
        $items[] = folderItem($root, $children);
    }

    return $items;
}

function openapiPath(string $path): string
{
    return '/api/v1'.$path;
}

function openapiOperation(array $ep): array
{
    $op = [
        'operationId' => $ep['code'],
        'summary' => $ep['name'],
        'description' => trim(($ep['name_ar'] ? $ep['name_ar']."\n\n" : '').$ep['description']),
        'tags' => array_values(array_unique(array_merge(
            $ep['apps'] ?? [],
            array_map('roleLabel', $ep['roles'] ?? []),
        ))) ?: ['api'],
        'parameters' => [],
        'responses' => [
            '200' => [
                'description' => 'Success envelope `{ data, meta }`',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                        'example' => envelope($ep['response']),
                    ],
                ],
            ],
        ],
    ];

    if ($ep['permission']) {
        $op['x-permission'] = $ep['permission'];
    }
    $op['x-sprint'] = $ep['sprint'];
    $op['x-code'] = $ep['code'];
    $op['x-guard'] = $ep['guard'];
    $op['x-audience'] = $ep['audience'];
    $op['x-roles'] = $ep['roles'] ?? [];
    $op['x-apps'] = $ep['apps'] ?? [];

    if ($ep['auth']) {
        $op['security'] = [['bearerAuth' => []]];
    } else {
        $op['security'] = [];
    }

    foreach (defaultHeaders($ep) as $key => $value) {
        if (in_array($key, ['Authorization', 'Content-Type'], true)) {
            continue;
        }
        $op['parameters'][] = [
            'name' => $key,
            'in' => 'header',
            'required' => ! in_array($key, ['Accept-Language', 'X-Channel-Id'], true),
            'schema' => ['type' => 'string', 'example' => str_contains($value, '{{') ? 'example' : $value],
        ];
    }

    if (preg_match_all('/\{([^}]+)\}/', $ep['path'], $m)) {
        foreach ($m[1] as $param) {
            $op['parameters'][] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ];
        }
    }

    foreach ($ep['query'] as $k => $v) {
        $op['parameters'][] = [
            'name' => (string) $k,
            'in' => 'query',
            'required' => false,
            'schema' => ['type' => 'string', 'example' => (string) $v],
        ];
    }

    if ($ep['write'] && $ep['body'] !== null) {
        $op['requestBody'] = [
            'required' => true,
            'content' => [
                ($ep['multipart'] ? 'multipart/form-data' : 'application/json') => [
                    'schema' => ['type' => 'object'],
                    'example' => $ep['body'],
                ],
            ],
        ];
    }

    foreach ($ep['errors'] as $status => $code) {
        $op['responses'][(string) $status] = [
            'description' => (string) $code,
            'content' => [
                'application/json' => [
                    'example' => errorEnvelope((string) $code, (string) $code),
                ],
            ],
        ];
    }

    return $op;
}

function buildOpenApi(array $endpoints): array
{
    $paths = [];
    $tags = [];
    foreach ($endpoints as $ep) {
        $p = openapiPath($ep['path']);
        $method = strtolower($ep['method']);
        $paths[$p][$method] = openapiOperation($ep);
        foreach ($ep['roles'] ?? [] as $role) {
            $label = roleLabel($role);
            $tags[$label] = ['name' => $label];
        }
        foreach ($ep['apps'] ?? [] as $app) {
            $tags[$app] = ['name' => $app];
        }
    }

    return [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'منصة التوزيع B2B — APIs حسب التطبيق والدور',
            'description' => "مقسّم: تطبيق التاجر · تطبيق المندوب · واجهة المستودع (أمين المستودع) · لوحة قناة التوريد (مدير القناة / مدير المبيعات / مدير الكتالوج / المحاسب) · لوحة إدارة المنصة / السنترال (مدير المنصة / تشغيل / دعم / مالية / محتوى / تقنية / مدقّق).\n\nEnvelope: success `{ data, meta }`, error `{ error: { code, message, details? } }`. المبالغ أعداد صحيحة بأصغر وحدة. الكتابة تتطلب `X-Idempotency-Key`.",
            'version' => '1.0.0',
        ],
        'servers' => [
            ['url' => 'http://localhost:8000', 'description' => 'Local'],
        ],
        'tags' => array_values($tags),
        'paths' => $paths,
        'components' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                    'bearerFormat' => 'Sanctum',
                ],
            ],
            'schemas' => [
                'SuccessEnvelope' => [
                    'type' => 'object',
                    'required' => ['data', 'meta'],
                    'properties' => [
                        'data' => ['description' => 'Endpoint payload'],
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'server_time' => ['type' => 'string', 'format' => 'date-time'],
                                'sync_cursor' => ['type' => 'string'],
                                'requires_dual_approval' => ['type' => 'boolean'],
                                'page' => ['type' => 'integer'],
                                'per_page' => ['type' => 'integer'],
                                'total' => ['type' => 'integer'],
                            ],
                        ],
                    ],
                ],
                'ErrorEnvelope' => [
                    'type' => 'object',
                    'required' => ['error'],
                    'properties' => [
                        'error' => [
                            'type' => 'object',
                            'required' => ['code', 'message'],
                            'properties' => [
                                'code' => ['type' => 'string'],
                                'message' => ['type' => 'string'],
                                'details' => ['type' => 'object'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

function environment(string $name, string $baseUrl, string $client): array
{
    $values = [
        ['key' => 'baseUrl', 'value' => $baseUrl],
        ['key' => 'token', 'value' => ''],
        ['key' => 'deviceId', 'value' => '11111111-1111-1111-1111-111111111111'],
        ['key' => 'appVersion', 'value' => '1.4.2 (142)'],
        ['key' => 'client', 'value' => $client],
        ['key' => 'channelId', 'value' => '1'],
        ['key' => 'acceptLanguage', 'value' => 'ar'],
        ['key' => 'otp_id', 'value' => ''],
        ['key' => 'challenge_token', 'value' => ''],
        ['key' => 'receipt_no', 'value' => ''],
        ['key' => 'phone', 'value' => '+963933000000'],
    ];

    return [
        'id' => 'env-'.substr(sha1($name), 0, 12),
        'name' => $name,
        'values' => array_map(fn ($v) => $v + ['enabled' => true, 'type' => 'default'], $values),
        '_postman_variable_scope' => 'environment',
    ];
}

// ---------------------------------------------------------------------------
$endpoints = [];
foreach (glob(__DIR__.'/catalog/*.php') as $file) {
    $chunk = require $file;
    if (! is_array($chunk)) {
        throw new RuntimeException("Catalog file must return array: {$file}");
    }
    $endpoints = array_merge($endpoints, $chunk);
}

$endpoints = array_map('annotateEndpoint', $endpoints);

usort($endpoints, function (array $a, array $b): int {
    return [$a['folder'], $a['code'], $a['method']] <=> [$b['folder'], $b['code'], $b['method']];
});

$postmanEndpoints = expandForPostman($endpoints);
usort($postmanEndpoints, function (array $a, array $b): int {
    return [$a['folder'], $a['code'], $a['method']] <=> [$b['folder'], $b['code'], $b['method']];
});

$outDir = __DIR__;
$envDir = $outDir.'/environments';
if (! is_dir($envDir) && ! mkdir($envDir, 0777, true) && ! is_dir($envDir)) {
    throw new RuntimeException('Cannot create environments dir');
}

$catalog = [
    'info' => [
        'title' => 'B2B Distribution Platform — API Catalog',
        'source' => 'DOC-11B v1.0 + DOC-07 AD-FSD + DOC-08 IAM + DOC-12A/12B/12E extras',
        'version' => '1.0.0',
        'base_path' => '/api/v1',
        'envelope' => [
            'success' => '{ data, meta.server_time, ... }',
            'error' => '{ error: { code, message, details? } }',
        ],
        'conventions' => [
            'money' => 'Integer minor currency units (BR-AD-18). 12000 means 12000, not 120.00.',
            'pagination' => '?page=1&per_page=25&sort=-created_at&search=&filter[status]=pending&include=lines,retailer',
            'idempotency' => 'X-Idempotency-Key required on writes (BR-AD-24 / ADR-05). Replay 24h. Same key + different body → 409 idempotency_key_conflict.',
            'guards' => [
                '/api/v1/platform/*' => 'platform (email + password + 2FA)',
                '/api/v1/channel/*' => 'channel (phone + WhatsApp OTP)',
                '/api/v1/warehouse/*' => 'warehouse (registered device + PIN)',
                '/api/v1/app/retailer/*' => 'app (phone + OTP bound to device)',
                '/api/v1/app/rep/*' => 'app (phone + OTP bound to device)',
                '/api/v1/public/*' => 'none',
            ],
            'standard_headers' => [
                'Authorization' => 'Bearer {token} — required except public',
                'Accept' => 'application/json',
                'Accept-Language' => 'ar (default)',
                'X-Device-Id' => 'UUID — required for apps and warehouse',
                'X-App-Version' => '1.4.2 (142) — required for apps; 426 upgrade_required if stale',
                'X-Client' => 'retailer-android | retailer-ios | rep-android | rep-ios | channel-web | warehouse-desktop | platform-web',
                'X-Idempotency-Key' => 'UUID per write',
                'X-Channel-Id' => 'optional, multi-channel users / platform_admin',
            ],
        ],
        'generated_at' => gmdate('c'),
        'endpoint_count' => count($endpoints),
        'surfaces' => [
            'retailer_app' => ['label' => 'تطبيق التاجر', 'role' => 'retailer', 'guard' => 'app', 'client' => 'retailer-android'],
            'rep_app' => ['label' => 'تطبيق المندوب', 'role' => 'rep', 'guard' => 'app', 'client' => 'rep-android'],
            'warehouse' => ['label' => 'واجهة المستودع', 'role' => 'warehouse_keeper', 'guard' => 'warehouse', 'client' => 'warehouse-desktop'],
            'channel_dashboard' => [
                'label' => 'لوحة قناة التوريد',
                'roles' => ['channel_manager', 'sales_manager', 'catalog_manager', 'accountant'],
                'guard' => 'channel',
                'client' => 'channel-web',
            ],
            'platform_admin' => [
                'label' => 'لوحة إدارة المنصة (السنترال)',
                'roles' => ['platform_admin', 'platform_ops', 'platform_support', 'platform_finance', 'platform_content', 'platform_tech', 'platform_auditor'],
                'guard' => 'platform',
                'client' => 'platform-web',
            ],
        ],
    ],
    'endpoints' => array_map(function (array $ep): array {
        $ep['headers'] = defaultHeaders($ep);

        return $ep;
    }, $endpoints),
];

$collection = [
    'info' => [
        'name' => 'منصة التوزيع B2B — حسب التطبيق والدور',
        'description' => "مقسّم حسب سطح العمل والأدوار (DOC-07 / DOC-08):\n\n1. تطبيق التاجر\n2. تطبيق المندوب\n3. واجهة المستودع (أمين المستودع)\n4. لوحة قناة التوريد: مدير القناة / مدير المبيعات / مدير الكتالوج / المحاسب\n5. لوحة إدارة المنصة (السنترال): مدير المنصة / تشغيل / دعم / مالية / محتوى / تقنية / مدقّق\n\nكل طلب فيه الهيدرز والبودي. ابدأ من مجلد الدخول حتى يُحفظ التوكن.",
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        '_exporter_id' => 'b2b-doc-11b',
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [['key' => 'token', 'value' => '{{token}}', 'type' => 'string']],
    ],
    'variable' => [
        ['key' => 'baseUrl', 'value' => 'http://localhost:8000'],
        ['key' => 'token', 'value' => ''],
        ['key' => 'deviceId', 'value' => '11111111-1111-1111-1111-111111111111'],
        ['key' => 'appVersion', 'value' => '1.4.2 (142)'],
        ['key' => 'client', 'value' => 'retailer-android'],
        ['key' => 'channelId', 'value' => '1'],
        ['key' => 'acceptLanguage', 'value' => 'ar'],
        ['key' => 'otp_id', 'value' => ''],
        ['key' => 'challenge_token', 'value' => ''],
        ['key' => 'receipt_no', 'value' => ''],
        ['key' => 'id', 'value' => '1'],
        ['key' => 'code', 'value' => 'ad.iam.view_catalog'],
        ['key' => 'lineId', 'value' => '1'],
        ['key' => 'subOrderId', 'value' => '1'],
        ['key' => 'handoverId', 'value' => '1'],
        ['key' => 'jobId', 'value' => '1'],
        ['key' => 'type', 'value' => 'sales'],
        ['key' => 'key', 'value' => 'offline_mode'],
        ['key' => 'ref', 'value' => 'ch_a1'],
        ['key' => 'retailer_id', 'value' => '1'],
    ],
    'item' => nestFolders($postmanEndpoints),
];

$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

file_put_contents($outDir.'/b2b-api.catalog.json', json_encode($catalog, $flags)."\n");
file_put_contents($outDir.'/b2b-api.postman_collection.json', json_encode($collection, $flags)."\n");
file_put_contents($outDir.'/b2b-api.openapi.json', json_encode(buildOpenApi($endpoints), $flags)."\n");

$envs = [
    'local.postman_environment.json' => environment('B2B Local', 'http://localhost:8000', 'retailer-android'),
    'platform-web.postman_environment.json' => environment('B2B Platform Web', 'http://localhost:8000', 'platform-web'),
    'channel-web.postman_environment.json' => environment('B2B Channel Web', 'http://localhost:8000', 'channel-web'),
    'warehouse-desktop.postman_environment.json' => environment('B2B Warehouse Desktop', 'http://localhost:8000', 'warehouse-desktop'),
    'retailer-android.postman_environment.json' => environment('B2B Retailer Android', 'http://localhost:8000', 'retailer-android'),
    'rep-android.postman_environment.json' => environment('B2B Rep Android', 'http://localhost:8000', 'rep-android'),
];

foreach ($envs as $file => $env) {
    file_put_contents($envDir.'/'.$file, json_encode($env, $flags)."\n");
}

fwrite(STDOUT, 'Catalog '.count($endpoints).' endpoints / Postman '.count($postmanEndpoints)." placements\n");
fwrite(STDOUT, "  {$outDir}/b2b-api.catalog.json\n");
fwrite(STDOUT, "  {$outDir}/b2b-api.postman_collection.json\n");
fwrite(STDOUT, "  {$outDir}/b2b-api.openapi.json\n");
fwrite(STDOUT, '  '.count($envs)." Postman environments\n");
