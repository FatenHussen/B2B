<?php

declare(strict_types=1);

/**
 * Three Postman collections — one per dashboard surface:
 *   docs/DocsLast/platform.postman_collection.json
 *   docs/DocsLast/channel.postman_collection.json
 *   docs/DocsLast/warehouse.postman_collection.json
 *
 * Sources: php artisan route:list + b2b-api.catalog.json.
 * Run: php docs/api/generate-dashboard-postman.php
 */

$api = __DIR__;
$root = dirname($api, 2);
$out = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'DocsLast';

chdir($root);
$dumped = shell_exec('php artisan route:list --json');
if (is_string($dumped) && str_starts_with(ltrim($dumped), '[')) {
    $dumped = preg_replace('/^\xEF\xBB\xBF/', '', $dumped) ?? $dumped;
    file_put_contents($api.DIRECTORY_SEPARATOR.'.live-routes.json', $dumped);
}

$routesJson = preg_replace('/^\xEF\xBB\xBF/', '', (string) file_get_contents($api.DIRECTORY_SEPARATOR.'.live-routes.json'));
$catJson = (string) file_get_contents($api.DIRECTORY_SEPARATOR.'b2b-api.catalog.json');
$routes = json_decode((string) $routesJson, true);
$catRaw = json_decode($catJson, true);
if (! is_array($routes) || ! is_array($catRaw)) {
    fwrite(STDERR, 'Failed to read live routes or catalog: '.json_last_error_msg()."\n");
    exit(1);
}

$norm = static fn (string $m, string $p): string => strtoupper($m).' '.rtrim((string) preg_replace('#\{[^}]+\}#', '{}', $p), '/');

$catalog = [];
foreach ($catRaw['endpoints'] ?? [] as $e) {
    if (! is_array($e) || empty($e['method']) || empty($e['path'])) {
        continue;
    }
    $catalog[$norm((string) $e['method'], (string) $e['path'])] = $e;
}

$exempt = [
    'api/v1/platform/auth/login',
    'api/v1/platform/auth/2fa/verify',
    'api/v1/channel/auth/request-otp',
    'api/v1/channel/auth/verify-otp',
    'api/v1/warehouse/auth/device-login',
];

$manual = [
    'POST /channel/auth/verify-otp' => ['otp_id' => '{{otp_id}}', 'code' => '000000'],
    'POST /channel/auth/request-otp' => ['phone' => '{{phone}}'],
    'PUT /channel' => [
        'name' => 'Demo Channel',
        'legal_name' => 'Demo Channel LLC',
        'tax_number' => '1234567',
        'phone' => '+963911000000',
        'email' => 'ops@demo.sy',
        'settings' => [
            'quiet_hours' => ['from' => '22:00', 'to' => '08:00'],
            'returns_sla_hours' => 24,
            'order_approver' => 'channel',
        ],
    ],
    'POST /channel/sub-orders/assign' => [
        'sub_order_ids' => [9001],
        'rep_id' => 70,
        'mode' => 'manual',
    ],
    'POST /warehouse/auth/device-login' => [
        'device_token' => '{{deviceToken}}',
        'pin' => '{{devicePin}}',
    ],
    'POST /warehouse/sub-orders/assign' => [
        'sub_order_ids' => [9001],
        'rep_id' => 70,
        'mode' => 'manual',
    ],
    'POST /warehouse/stock-shortages' => [
        'product_id' => 880,
        'variant_id' => null,
        'qty_available' => 12,
        'reason' => 'out_of_stock',
    ],
    'POST /warehouse/handovers' => [
        'rep_id' => 70,
        'sub_order_ids' => [9001, 9002],
    ],
    'POST /platform/auth/login' => [
        'email' => '{{email}}',
        'password' => '{{password}}',
        'device_name' => 'Postman',
    ],
];

$surfaces = [
    'platform' => [
        'file' => 'platform.postman_collection.json',
        'name' => 'B2B Platform admin (سوبر أدمن)',
        'client' => 'platform-web',
        'env' => 'docs/api/environments/platform-web.postman_environment.json',
        'seed' => 'admin@platform.sy / password — role platform_admin',
        'match' => static function (string $path): bool {
            return $path === '/health'
                || str_starts_with($path, '/platform/')
                || str_starts_with($path, '/admin/channels')
                || str_starts_with($path, '/governorates')
                || str_starts_with($path, '/zones')
                || str_starts_with($path, '/currencies');
        },
        'variables' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
            ['key' => 'token', 'value' => ''],
            ['key' => 'challenge_token', 'value' => ''],
            ['key' => 'client', 'value' => 'platform-web'],
            ['key' => 'acceptLanguage', 'value' => 'ar'],
            ['key' => 'appVersion', 'value' => '1.0.0 (1)'],
            ['key' => 'email', 'value' => 'admin@platform.sy'],
            ['key' => 'password', 'value' => 'password'],
        ],
    ],
    'channel' => [
        'file' => 'channel.postman_collection.json',
        'name' => 'B2B Channel dashboard (قناة التوريد)',
        'client' => 'channel-web',
        'env' => 'docs/api/environments/channel-web.postman_environment.json',
        'seed' => '+963900000001 — OTP local any code / 6 digits when bypass off',
        'match' => static function (string $path): bool {
            return $path === '/health'
                || $path === '/public/refs'
                || $path === '/channel'
                || str_starts_with($path, '/channel/');
        },
        'variables' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
            ['key' => 'token', 'value' => ''],
            ['key' => 'otp_id', 'value' => ''],
            ['key' => 'client', 'value' => 'channel-web'],
            ['key' => 'acceptLanguage', 'value' => 'ar'],
            ['key' => 'appVersion', 'value' => '1.0.0 (1)'],
            ['key' => 'deviceId', 'value' => '11111111-1111-1111-1111-111111111111'],
            ['key' => 'phone', 'value' => '+963900000001'],
        ],
    ],
    'warehouse' => [
        'file' => 'warehouse.postman_collection.json',
        'name' => 'B2B Warehouse floor (المستودع)',
        'client' => 'warehouse-web',
        'env' => 'docs/api/environments/warehouse-desktop.postman_environment.json',
        'seed' => 'php artisan warehouse:register-device … then device_token + PIN',
        'match' => static function (string $path): bool {
            return $path === '/health' || str_starts_with($path, '/warehouse/');
        },
        'variables' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
            ['key' => 'token', 'value' => ''],
            ['key' => 'client', 'value' => 'warehouse-web'],
            ['key' => 'acceptLanguage', 'value' => 'ar'],
            ['key' => 'appVersion', 'value' => '1.0.0 (1)'],
            ['key' => 'deviceId', 'value' => '22222222-2222-2222-2222-222222222222'],
            ['key' => 'deviceToken', 'value' => ''],
            ['key' => 'devicePin', 'value' => '4821'],
        ],
    ],
];

$buckets = ['platform' => [], 'channel' => [], 'warehouse' => []];

foreach ($routes as $rt) {
    $uri = (string) ($rt['uri'] ?? '');
    if (! str_starts_with($uri, 'api/v1/')) {
        continue;
    }
    $path = '/'.substr($uri, 7);
    $mwAll = is_array($rt['middleware'] ?? null) ? $rt['middleware'] : [(string) ($rt['middleware'] ?? '')];
    [$guard, $perm, $role] = meta($mwAll);

    foreach (explode('|', (string) $rt['method']) as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }

        foreach ($surfaces as $key => $cfg) {
            if (! ($cfg['match'])($path)) {
                continue;
            }
            $buckets[$key][] = buildRequest(
                $method,
                $path,
                $uri,
                $guard,
                $perm,
                $role,
                $catalog[$norm($method, $path)] ?? null,
                $exempt,
                $manual,
                $cfg['client'],
            );
        }
    }
}

$generated = date('Y-m-d');
$counts = [];

foreach ($surfaces as $key => $cfg) {
    $tree = [];
    foreach ($buckets[$key] as $req) {
        $folder = $req['_folder'];
        unset($req['_folder']);
        $tree[$folder][] = $req;
    }
    ksort($tree);
    $items = [];
    foreach ($tree as $folder => $reqs) {
        $items[] = ['name' => $folder, 'item' => $reqs];
    }

    $n = count($buckets[$key]);
    $counts[$key] = $n;
    $collection = [
        'info' => [
            'name' => $cfg['name'],
            '_postman_id' => 'b2b-'.$key.'-'.substr(md5($key.$n.$generated), 0, 12),
            'description' => implode("\n", [
                '# '.$cfg['name'],
                '',
                'Generated **'.$generated.'** from `route:list` + catalog. Live routes only.',
                '',
                '| | |',
                '|---|---|',
                '| Requests | **'.$n.'** |',
                '| Environment | `'.$cfg['env'].'` |',
                '| X-Client | `'.$cfg['client'].'` |',
                '| Seed | '.$cfg['seed'].' |',
                '',
                'Import this file **and** the matching environment. `baseUrl` is the host only (no `/api/v1`).',
                'Do not mix tokens across platform / channel / warehouse collections.',
                '',
                'Contract briefs: `docs/DocsLast/'.$key.'.md` · order flow: `docs/DocsLast/order-flow.md`',
                '',
                'Regenerate: `php docs/api/generate-dashboard-postman.php`',
            ]),
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ],
        'variable' => $cfg['variables'],
        'item' => $items,
    ];

    $pathOut = $out.DIRECTORY_SEPARATOR.$cfg['file'];
    file_put_contents(
        $pathOut,
        json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );
    fwrite(STDOUT, $cfg['file'].' → '.$n." requests\n");
}

fwrite(STDOUT, 'done platform='.$counts['platform'].' channel='.$counts['channel'].' warehouse='.$counts['warehouse'].PHP_EOL);

function meta(array $mw): array
{
    $g = $p = $r = null;
    foreach ($mw as $m) {
        $m = (string) $m;
        if (preg_match('#Authenticate:([\w,]+)#', $m, $x)) {
            $g = $x[1];
        } elseif (preg_match('#PermissionMiddleware:(.+)$#', $m, $x)) {
            $p = $x[1];
        } elseif (preg_match('#RoleMiddleware:(.+)$#', $m, $x)) {
            $r = $x[1];
        }
    }

    return [$g, $p, $r];
}

function folderOf(string $path): string
{
    if ($path === '/health') {
        return '00. Health';
    }
    if ($path === '/public/refs') {
        return '00. Public refs';
    }
    if (str_starts_with($path, '/platform/auth') || str_starts_with($path, '/channel/auth') || str_starts_with($path, '/warehouse/auth')) {
        return '00. Auth';
    }
    if (str_starts_with($path, '/platform/me')) {
        return '01. My account';
    }
    if (str_starts_with($path, '/platform/iam') || str_starts_with($path, '/platform/audit')) {
        return '02. IAM & audit';
    }
    if (str_starts_with($path, '/platform/refs') || str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return '03. References';
    }
    if (str_starts_with($path, '/platform/channels') || str_starts_with($path, '/admin/channels') || str_starts_with($path, '/platform/channel-applications')) {
        return '04. Channels';
    }
    if (str_starts_with($path, '/platform/plans') || str_starts_with($path, '/platform/billing') || str_starts_with($path, '/platform/subscriptions') || str_starts_with($path, '/platform/platform-invoices')) {
        return '05. Billing';
    }
    if (str_starts_with($path, '/platform/dashboard') || str_starts_with($path, '/platform/reports') || str_starts_with($path, '/platform/exports')) {
        return '06. Dashboard & reports';
    }
    if (str_starts_with($path, '/platform/support')) {
        return '07. Support';
    }
    if (str_starts_with($path, '/platform/notifications')) {
        return '08. Notifications';
    }
    if (str_starts_with($path, '/platform/content') || str_starts_with($path, '/platform/app-versions') || str_starts_with($path, '/public/app-config')) {
        return '09. Content & apps';
    }
    if (str_starts_with($path, '/platform/features') || str_starts_with($path, '/platform/feature-flags')) {
        return '10. Features';
    }
    if (str_starts_with($path, '/platform/settings') || str_starts_with($path, '/platform/system') || str_starts_with($path, '/platform/health') || str_starts_with($path, '/platform/jobs') || str_starts_with($path, '/platform/team')) {
        return '11. Settings · system · team';
    }
    if (str_starts_with($path, '/platform/')) {
        return '12. Other platform';
    }

    if ($path === '/channel') {
        return '01. Channel settings';
    }
    if (str_starts_with($path, '/channel/zones') || str_starts_with($path, '/channel/delivery-calendar')) {
        return '02. Zones & calendar';
    }
    if (str_starts_with($path, '/channel/brands') || str_starts_with($path, '/channel/categories') || str_starts_with($path, '/channel/products') || str_starts_with($path, '/channel/catalog')) {
        return '03. Catalog';
    }
    if (str_starts_with($path, '/channel/price') || str_starts_with($path, '/channel/pricing')) {
        return '04. Pricing';
    }
    if (str_starts_with($path, '/channel/offers')) {
        return '05. Offers';
    }
    if (str_starts_with($path, '/channel/inventory') || str_starts_with($path, '/channel/warehouses')) {
        return '06. Inventory';
    }
    if (str_starts_with($path, '/channel/sub-orders')) {
        return '07. Sub-orders (order-flow)';
    }
    if (str_starts_with($path, '/channel/return-requests')) {
        return '08. Returns';
    }
    if (str_starts_with($path, '/channel/reps') || str_starts_with($path, '/channel/rep-')) {
        return '09. Reps';
    }
    if (str_starts_with($path, '/channel/retailers') || str_starts_with($path, '/channel/credit-approvals') || str_starts_with($path, '/channel/groups')) {
        return '10. Retailers & credit';
    }
    if (str_starts_with($path, '/channel/invoices') || str_starts_with($path, '/channel/payments') || str_starts_with($path, '/channel/finance') || str_starts_with($path, '/channel/aging')) {
        return '11. Finance';
    }
    if (str_starts_with($path, '/channel/notifications')) {
        return '12. Notifications';
    }
    if (str_starts_with($path, '/channel/content') || str_starts_with($path, '/channel/loyalty') || str_starts_with($path, '/channel/media')) {
        return '13. Content & loyalty';
    }
    if (str_starts_with($path, '/channel/dashboard') || str_starts_with($path, '/channel/reports') || str_starts_with($path, '/channel/jobs') || str_starts_with($path, '/channel/users')) {
        return '14. Dashboard · reports · users';
    }
    if (str_starts_with($path, '/channel/')) {
        return '15. Other channel';
    }

    if (str_starts_with($path, '/warehouse/sub-orders') || str_starts_with($path, '/warehouse/stock-shortages')) {
        return '01. Inbound orders (order-flow 2026-11-03)';
    }
    if (str_starts_with($path, '/warehouse/queues')) {
        return '02. Queues';
    }
    if (str_starts_with($path, '/warehouse/picking')) {
        return '03. Picking';
    }
    if (str_starts_with($path, '/warehouse/packing')) {
        return '04. Packing';
    }
    if (str_starts_with($path, '/warehouse/handovers')) {
        return '05. Handover';
    }
    if (str_starts_with($path, '/warehouse/receiving') || str_starts_with($path, '/warehouse/stock-lots')) {
        return '06. Receiving & lots';
    }
    if (str_starts_with($path, '/warehouse/stocktakes')) {
        return '07. Stocktake';
    }
    if (str_starts_with($path, '/warehouse/returns')) {
        return '08. Returns sort';
    }
    if (str_starts_with($path, '/warehouse/sync')) {
        return '09. Offline sync';
    }
    if (str_starts_with($path, '/warehouse/reports')) {
        return '10. Reports';
    }
    if (str_starts_with($path, '/warehouse/')) {
        return '11. Other warehouse';
    }

    return '99. Other';
}

function buildRequest(
    string $method,
    string $path,
    string $uri,
    ?string $guard,
    ?string $perm,
    ?string $role,
    ?array $e,
    array $exempt,
    array $manual,
    string $client,
): array {
    $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    $headers = [
        ['key' => 'Accept', 'value' => 'application/json'],
        ['key' => 'Accept-Language', 'value' => '{{acceptLanguage}}'],
        ['key' => 'X-Client', 'value' => '{{client}}'],
        ['key' => 'X-App-Version', 'value' => '{{appVersion}}'],
    ];
    if ($guard !== null) {
        $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}'];
    }
    if (str_starts_with($path, '/channel/') || str_starts_with($path, '/warehouse/') || $path === '/channel') {
        $headers[] = ['key' => 'X-Device-Id', 'value' => '{{deviceId}}'];
    }
    if ($isWrite) {
        $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
        if (! in_array($uri, $exempt, true)) {
            $headers[] = ['key' => 'X-Idempotency-Key', 'value' => '{{$guid}}'];
        }
    }

    $mk = strtoupper($method).' '.rtrim((string) preg_replace('#\{[^}]+\}#', '{}', $path), '/');
    $body = null;
    if ($isWrite && isset($manual[$mk])) {
        $raw = $manual[$mk];
        $body = [
            'mode' => 'raw',
            'raw' => json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'options' => ['raw' => ['language' => 'json']],
        ];
    } elseif ($isWrite && $e !== null && ! empty($e['body'])) {
        $body = [
            'mode' => 'raw',
            'raw' => json_encode($e['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'options' => ['raw' => ['language' => 'json']],
        ];
    } elseif ($isWrite && $method !== 'DELETE') {
        $body = ['mode' => 'raw', 'raw' => '{}', 'options' => ['raw' => ['language' => 'json']]];
    }

    $urlPath = preg_replace_callback('#\{([^}]+)\}#', static fn (array $m): string => '{{'.trim($m[1], '?').'}}', $uri) ?? $uri;
    $query = [];
    if ($e !== null && ! empty($e['query']) && is_array($e['query'])) {
        foreach ($e['query'] as $qk => $q) {
            $name = '';
            $example = '';
            if (is_array($q)) {
                $name = (string) ($q['name'] ?? '');
                $example = isset($q['example']) && is_scalar($q['example']) ? (string) $q['example'] : '';
            } elseif (is_string($qk)) {
                $name = $qk;
                $example = is_scalar($q) ? (string) $q : '';
            }
            if ($name !== '' && $name !== 'sort') {
                $query[] = ['key' => $name, 'value' => $example, 'disabled' => $example === ''];
            }
        }
    }

    $desc = [];
    $desc[] = '**'.($e['code'] ?? 'no EP-ID').'**';
    $desc[] = '';
    $desc[] = '| | |';
    $desc[] = '|---|---|';
    $desc[] = '| Guard | `'.($guard ?? 'none').'` |';
    if ($perm) {
        $desc[] = '| Permission | `'.$perm.'` |';
    }
    if ($role) {
        $desc[] = '| Role | `'.$role.'` |';
    }
    $desc[] = '| Idempotency | '.($isWrite ? (in_array($uri, $exempt, true) ? '**exempt**' : 'required') : 'n/a').' |';
    $desc[] = '| X-Client | `'.$client.'` |';
    if ($e !== null && ! empty($e['name_ar'])) {
        $desc[] = '| Name | '.$e['name_ar'].' |';
    }
    if ($e !== null && ! empty($e['description'])) {
        $desc[] = '';
        $desc[] = (string) $e['description'];
    }

    $req = [
        '_folder' => folderOf($path),
        'name' => $method.' '.$path,
        'request' => [
            'method' => $method,
            'header' => $headers,
            'url' => [
                'raw' => '{{baseUrl}}/'.$urlPath,
                'host' => ['{{baseUrl}}'],
                'path' => explode('/', $urlPath),
            ] + ($query !== [] ? ['query' => $query] : []),
            'description' => implode("\n", $desc),
        ] + ($body ? ['body' => $body] : []),
        'response' => [],
    ];

    $captureToken = str_contains($path, '/auth/login')
        || str_contains($path, '/auth/verify-otp')
        || str_contains($path, '/auth/device-login')
        || str_contains($path, '/auth/2fa/verify');

    if ($captureToken) {
        $req['event'] = [[
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    'const j = pm.response.json();',
                    'const d = j.data || {};',
                    'const store = pm.environment.name ? pm.environment : pm.collectionVariables;',
                    "if (d.token) { store.set('token', d.token); }",
                    "if (d.challenge_token) { store.set('challenge_token', d.challenge_token); }",
                    "if (d.otp_id) { store.set('otp_id', String(d.otp_id)); }",
                ],
            ],
        ]];
    }

    return $req;
}
