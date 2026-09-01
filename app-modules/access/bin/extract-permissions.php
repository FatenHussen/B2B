<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$json = json_decode((string) file_get_contents($root.'/docs/api/b2b-api.catalog.json'), true);
$endpoints = $json['endpoints'] ?? [];
$permissions = [];

foreach ($endpoints as $node) {
    $code = $node['permission'] ?? null;
    if (! is_string($code) || $code === '') {
        continue;
    }
    $audience = $node['audience'] ?? 'platform';
    $system = match ($audience) {
        'platform' => 'platform',
        'channel' => 'channel',
        'warehouse' => 'warehouse',
        'retailer', 'rep', 'app' => 'app',
        default => 'platform',
    };
    $parts = explode('.', $code);
    $module = $parts[1] ?? 'general';
    $crit = (bool) ($node['critical'] ?? false);
    $dual = (bool) ($node['dual_approval'] ?? false);
    $nameAr = (string) ($node['name_ar'] ?? $code);

    if (! isset($permissions[$code])) {
        $permissions[$code] = [
            'code' => $code,
            'name_ar' => $nameAr !== '' ? $nameAr : $code,
            'system' => $system,
            'module' => $module,
            'severity' => $crit ? 'critical' : 'standard',
            'dual_approval' => $dual,
            'delegatable' => ! $crit,
        ];
    } else {
        if ($crit) {
            $permissions[$code]['severity'] = 'critical';
            $permissions[$code]['delegatable'] = false;
        }
        $method = strtoupper((string) ($node['method'] ?? 'GET'));
        if ($dual && $method !== 'GET') {
            $permissions[$code]['dual_approval'] = true;
        }
    }
}

$permissions['ad.billing.manage'] ??= [
    'code' => 'ad.billing.manage',
    'name_ar' => 'حدود الفوترة',
    'system' => 'platform',
    'module' => 'billing',
    'severity' => 'standard',
    'dual_approval' => false,
    'delegatable' => true,
];
$permissions['ad.channels.archive'] ??= [
    'code' => 'ad.channels.archive',
    'name_ar' => 'أرشفة قناة',
    'system' => 'platform',
    'module' => 'channels',
    'severity' => 'critical',
    'dual_approval' => false,
    'delegatable' => false,
];

ksort($permissions);

$body = '';
foreach ($permissions as $p) {
    $body .= sprintf(
        "        '%s' => ['name_ar' => %s, 'system' => '%s', 'module' => '%s', 'severity' => '%s', 'dual_approval' => %s, 'delegatable' => %s],\n",
        $p['code'],
        var_export($p['name_ar'], true),
        $p['system'],
        $p['module'],
        $p['severity'],
        $p['dual_approval'] ? 'true' : 'false',
        $p['delegatable'] ? 'true' : 'false',
    );
}

$class = <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\Access\\Domain;

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
{$body}    ];

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
    public static function get(string \$code): ?array
    {
        return self::ITEMS[\$code] ?? null;
    }

    public static function exists(string \$code): bool
    {
        return isset(self::ITEMS[\$code]);
    }

    /**
     * @return list<string>
     */
    public static function codesForSystem(string \$system): array
    {
        \$out = [];
        foreach (self::ITEMS as \$code => \$row) {
            if (\$row['system'] === \$system) {
                \$out[] = \$code;
            }
        }

        return \$out;
    }

    /**
     * @return list<string>
     */
    public static function codesForModules(string \$system, array \$modules): array
    {
        \$out = [];
        foreach (self::ITEMS as \$code => \$row) {
            if (\$row['system'] === \$system && in_array(\$row['module'], \$modules, true)) {
                \$out[] = \$code;
            }
        }

        return \$out;
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
            'catalog_manager' => self::codesForModules('channel', ['catalog', 'pricing', 'promotions', 'content']),
            'accountant' => self::codesForModules('channel', ['finance', 'returns']),
            'warehouse_keeper' => self::codesForSystem('warehouse'),
            'retailer' => self::codesStartingWith('rt.'),
            'rep' => self::codesStartingWith('rp.'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function codesStartingWith(string \$prefix): array
    {
        \$out = [];
        foreach (self::ITEMS as \$code => \$_) {
            if (str_starts_with(\$code, \$prefix)) {
                \$out[] = \$code;
            }
        }

        return \$out;
    }

    public static function guardForSystem(string \$system): string
    {
        return match (\$system) {
            'platform' => 'platform',
            'channel' => 'channel',
            'warehouse' => 'warehouse',
            'app' => 'app',
            default => 'platform',
        };
    }
}

PHP;

file_put_contents($root.'/app-modules/access/src/Domain/PermissionCatalog.php', $class);
echo count($permissions)." wrote PermissionCatalog.php\n";
