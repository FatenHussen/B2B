<?php

declare(strict_types=1);

/**
 * Builds the platform-admin live contract:
 *   docs/DocsLast/apps/platform-api.live.json
 *   docs/DocsLast/apps/platform-api.postman.json
 *
 * Sources: php artisan route:list + b2b-api.catalog.json.
 */

$api  = __DIR__;
$root = dirname($api, 2);
$out  = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'DocsLast'.DIRECTORY_SEPARATOR.'apps';

chdir($root);
$dumped = shell_exec('php artisan route:list --json');
if (is_string($dumped) && str_starts_with(ltrim($dumped), '[')) {
    $dumped = preg_replace('/^\xEF\xBB\xBF/', '', $dumped) ?? $dumped;
    file_put_contents($api.DIRECTORY_SEPARATOR.'.live-routes.json', $dumped);
}

$routesJson = file_get_contents($api.DIRECTORY_SEPARATOR.'.live-routes.json');
$routesJson = preg_replace('/^\xEF\xBB\xBF/', '', (string) $routesJson);
$catJson = file_get_contents($api.DIRECTORY_SEPARATOR.'b2b-api.catalog.json');
$routes = json_decode((string) $routesJson, true);
$catRaw = json_decode((string) $catJson, true);
if (! is_array($routes) || ! is_array($catRaw)) {
    fwrite(STDERR, 'Failed to read live routes or catalog: '.json_last_error_msg()."\n");
    exit(1);
}

$catalog = [];
foreach ($catRaw['endpoints'] ?? [] as $ep) {
    if (! is_array($ep) || empty($ep['method']) || empty($ep['path'])) {
        continue;
    }
    $catalog[norm((string) $ep['method'], (string) $ep['path'])] = $ep;
}

$exempt = [
    'api/v1/platform/auth/login',
    'api/v1/platform/auth/2fa/verify',
];

$want = static function (string $path): bool {
    if ($path === '/health') {
        return true;
    }
    if (str_starts_with($path, '/platform/')) {
        return true;
    }
    if (str_starts_with($path, '/admin/channels')) {
        return true;
    }
    if (str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return true;
    }

    return false;
};

$overlays = overlays();
$notes = notes();
$live = [];
$liveKeys = [];

foreach ($routes as $rt) {
    $uri = (string) ($rt['uri'] ?? '');
    if (! str_starts_with($uri, 'api/v1/')) {
        continue;
    }
    $path = '/'.substr($uri, 7);
    if (! $want($path)) {
        continue;
    }
    $mwAll = is_array($rt['middleware'] ?? null) ? $rt['middleware'] : [(string) ($rt['middleware'] ?? '')];
    [$guard, $perm, $role] = meta($mwAll);

    foreach (explode('|', (string) $rt['method']) as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }
        $key = norm($method, $path);
        $alias = norm($method, catalogAlias($path));
        $e = $catalog[$key] ?? $catalog[$alias] ?? null;
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $auth = $guard !== null;
        $stability = stabilityOf($path);

        $query = [];
        if (isset($overlays[$key]['query'])) {
            $query = $overlays[$key]['query'];
        } elseif (is_array($e['query'] ?? null)) {
            foreach ($e['query'] as $qk => $qv) {
                $name = is_array($qv) ? (string) ($qv['name'] ?? '') : (is_string($qk) ? $qk : (string) $qv);
                $example = is_array($qv) ? ($qv['example'] ?? ($qv['value'] ?? '')) : (is_string($qk) ? $qv : '');
                if ($name === '' || $name === 'sort') {
                    continue;
                }
                $query[] = ['name' => $name, 'example' => $example];
            }
        }

        $body = $overlays[$key]['body'] ?? ($e['body'] ?? null);
        $response = $overlays[$key]['response'] ?? ($e['response'] ?? null);

        $item = [
            'live' => true,
            'stability' => $stability,
            'method' => $method,
            'path' => '/api/v1'.$path,
            'will_become' => $stability === 'moving' ? '/api/v1'.catalogAlias($path) : null,
            'catalog_path' => $e['path'] ?? $path,
            'code' => $e['code'] ?? null,
            'name' => $e['name'] ?? ($method.' '.$path),
            'name_ar' => $e['name_ar'] ?? '',
            'permission' => $perm ?? ($e['permission'] ?? null),
            'role' => $role,
            'guard' => $guard ?? 'none',
            'auth' => $auth,
            'write' => $isWrite,
            'idempotency' => $isWrite && ! in_array($uri, $exempt, true),
            'folder' => folder($path),
            'query' => $query,
            'body' => $body,
            'response' => $response,
            'errors' => $e['errors'] ?? [],
            'description' => $e['description'] ?? '',
            'note' => $notes[$key] ?? $notes[norm($method, collapse($path))] ?? null,
        ];
        $live[] = $item;
        $liveKeys[$key] = true;
        $liveKeys[$alias] = true;
    }
}

usort($live, static function (array $a, array $b): int {
    $fa = folderRank($a['folder']);
    $fb = folderRank($b['folder']);
    if ($fa !== $fb) {
        return $fa <=> $fb;
    }

    return strcmp($a['path'].$a['method'], $b['path'].$b['method']);
});

$forbidden = [];
foreach ($catRaw['endpoints'] ?? [] as $ep) {
    if (! is_array($ep) || empty($ep['method']) || empty($ep['path'])) {
        continue;
    }
    $p = (string) $ep['path'];
    if (! str_starts_with($p, '/platform/') && $p !== '/public/app-config') {
        continue;
    }
    $key = norm((string) $ep['method'], $p);
    if (isset($liveKeys[$key])) {
        continue;
    }
    $forbidden[] = [
        'method' => strtoupper((string) $ep['method']),
        'path' => '/api/v1'.$p,
        'code' => $ep['code'] ?? null,
        'sprint' => $ep['sprint'] ?? null,
        'name_ar' => $ep['name_ar'] ?? ($ep['name'] ?? ''),
        'reason' => 'Catalogued, no route at this path — do not build, do not mock',
    ];
}

$matched = count(array_filter($live, fn (array $e): bool => $e['code'] !== null));
$moving = count(array_filter($live, fn (array $e): bool => $e['stability'] === 'moving'));

$pack = [
    'info' => [
        'title' => 'Platform admin (السنترال) — live API only',
        'generated_at' => '2026-09-17',
        'base_path' => '/api/v1',
        'guard' => 'platform',
        'x_client' => 'platform-web',
        'port' => 3000,
        'seed' => 'admin@platform.sy / password — role platform_admin, 2FA off',
        'not' => 'This is NOT the channel dashboard (guard channel, port 3001).',
        'rule' => 'If this file and the catalog disagree, this file wins. Call only live:true. Never call forbidden. Never mock missing GMV/billing/refs-at-contract-path. Keep every moving base path behind ONE constant.',
        'counts' => [
            'live_endpoints' => count($live),
            'matched_catalog' => $matched,
            'live_without_catalog' => count($live) - $matched,
            'moving' => $moving,
            'forbidden' => count($forbidden),
        ],
    ],
    'headers' => [
        'Authorization' => 'Bearer {token} — required except POST /platform/auth/login, POST /platform/auth/2fa/verify, GET /health',
        'Accept' => 'application/json',
        'Accept-Language' => 'ar — server messages may still be English; map error.code',
        'X-Client' => 'platform-web — sent for logs; server ignores it',
        'X-Idempotency-Key' => 'UUID per user intent on every write except login and 2fa/verify',
        'Content-Type' => 'application/json',
        'X-Channel-Id' => 'optional, honoured only for platform_admin when switching tenant context — do not send unless that flow exists',
        'X-Device-Id' => 'not required on this guard',
    ],
    'envelope' => [
        'success' => ['data' => new stdClass, 'meta' => ['server_time' => '...']],
        'list' => ['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1]],
        'error' => ['error' => ['code' => 'not_found', 'message' => '...', 'permission' => 'ad.iam.role_create', 'details' => new stdClass]],
        'money' => 'integer minor units. SYP decimals = 0. Never /100. Do not invent GMV.',
        'sort' => 'roles list allows sort=id|name|created_at. Other lists: never send sort unless this file names it.',
        'not_found' => '404 means missing OR not yours — never render forbidden',
        'two_gates' => 'permissions[] gate IAM/audit. /admin/channels index+delete also require role platform_admin. Gate::before grants platform_admin every permission.',
        'moving' => 'stability=moving lives at a temporary path. One constant per base: CHANNELS_BASE=/admin/channels, REFS_GOV=/governorates, REFS_ZONES=/zones, REFS_FX=/currencies.',
    ],
    'forbidden' => $forbidden,
    'gotchas' => [
        'Login expires_at is nominal — Sanctum expiration is null. Trust 401.',
        '2FA challenge_token lives in React state only, 10 minutes, 5 attempts.',
        'PUT /platform/me is a full object (name required). PUT /admin/channels/{id} is partial but reason is required. status is prohibited on channel create/update.',
        'Channel index and DELETE are gated by role platform_admin, not a permission — 403 has no error.permission.',
        'GET /admin/channels/{id} returns nested channel.allowed_next — drive transition buttons from that list.',
        'POST /admin/channels returns 201 {id, status:provisioning, provisioning_job_id}. Status starts provisioning. Do not send status.',
        'POST /admin/channels/{id}/transition and retry-provisioning ARE live on the moving prefix. Limits/usage/coverage/users/warehouses/features/applications/bulk-plan are not.',
        'DELETE /admin/channels/{id} is 204 empty body, soft delete, no restore.',
        'created_at on a channel is UTC; other timestamps are Asia/Damascus.',
        'Replay requires_password_confirm with the SAME idempotency key and body.',
        'POST /me/api-tokens puts the current password in password_confirmation and checks it inline — not the 15-min window.',
        '2fa/enable qr_svg is an empty placeholder — render QR from secret (otpauth URI). Recovery codes appear once on confirm.',
        'POST /iam/assignments partial success is still 200 — render rejected. DELETE /iam/assignments needs a body.',
        'New roles land as draft. Creator cannot approve (403 sod_violation).',
        'approval-requests decide: read executed, not status. Review items use keep|revoke.',
        'GET /iam/reviews/{id} is unpaginated. simulate tenant layer is hardcoded pass.',
        'audit/export body key is filters (plural); the list uses filter. No job-status route.',
        '/governorates /zones /currencies are live at the v1 root on a multi-guard list. Contract path is /platform/refs/*. Do not build admin screens on them without one constant. Shape is thinner than SP-03.',
        'GET /platform/dashboard is 404. Never fake GMV.',
        'GET /platform/content/intro is the platform default. Empty store is enabled:false. PUT returns {enabled} only — re-GET for the form. Channel intro is /channel/content/intro on the other dashboard.',
    ],
    'endpoints' => $live,
];

file_put_contents(
    $out.'/platform-api.live.json',
    json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

$postman = postman($live, $exempt);
file_put_contents(
    $out.'/platform-api.postman.json',
    json_encode($postman, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

echo 'live='.count($live).' matched='.$matched.' moving='.$moving.' forbidden='.count($forbidden).PHP_EOL;

function norm(string $method, string $path): string
{
    return strtoupper($method).' '.collapse($path);
}

function collapse(string $path): string
{
    $path = '/'.ltrim($path, '/');

    return (string) preg_replace('#\{[^}]+\}#', '{}', $path);
}

function catalogAlias(string $path): string
{
    $path = preg_replace('#^/admin/channels#', '/platform/channels', $path) ?? $path;
    $path = preg_replace('#^/governorates#', '/platform/refs/governorates', $path) ?? $path;
    $path = preg_replace('#^/zones#', '/platform/refs/zones', $path) ?? $path;
    $path = preg_replace('#^/currencies#', '/platform/refs/currencies', $path) ?? $path;

    return $path;
}

function stabilityOf(string $path): string
{
    if (str_starts_with($path, '/admin/') || str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return 'moving';
    }

    return 'stable';
}

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
        } elseif (preg_match('#Authorize:(.+)$#', $m, $x)) {
            $p = $p ? $p.','.$x[1] : $x[1];
        }
    }

    return [$g, $p, $r];
}

function folder(string $path): string
{
    if ($path === '/health') {
        return '00. Health';
    }
    if (str_starts_with($path, '/platform/auth')) {
        return '01. Auth';
    }
    if (str_starts_with($path, '/platform/me')) {
        return '02. My account';
    }
    if (str_starts_with($path, '/platform/iam')) {
        return '03. IAM';
    }
    if (str_starts_with($path, '/platform/audit')) {
        return '04. Audit';
    }
    if (str_starts_with($path, '/admin/channels')) {
        return '05. Channels (MOVING)';
    }
    if (str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return '06. Refs at v1 root (MOVING — do not wire screens yet)';
    }
    if (str_starts_with($path, '/platform/content')) {
        return '07. Content';
    }

    return '99. Other';
}

function folderRank(string $folder): int
{
    $n = (int) substr($folder, 0, 2);

    return $n === 0 && ! str_starts_with($folder, '00') ? 99 : $n;
}

function overlays(): array
{
    return [
        'POST /platform/auth/login' => [
            'body' => ['email' => 'admin@platform.sy', 'password' => 'password'],
            'response' => [
                'token' => '1|xxxx',
                'user' => [
                    'id' => 1,
                    'name' => 'Platform Admin',
                    'email' => 'admin@platform.sy',
                    'roles' => ['platform_admin'],
                    'permissions' => ['ad.iam.view_catalog'],
                ],
                'expires_at' => '2026-09-18T12:00:00+03:00',
            ],
        ],
        'POST /admin/channels' => [
            'body' => [
                'name' => 'شركة الشام',
                'slug' => 'al-sham',
                'legal_form' => 'llc',
                'cr_number' => 'C12345',
                'documents' => [],
                'governorate_ids' => [1],
                'zone_ids' => [12],
                'activity_type_ids' => [3],
                'logo' => null,
                'internal_note' => 'شراكة تجريبية',
                'plan_id' => 1,
                'billing_cycle' => 'yearly',
                'trial_days' => 14,
                'limits' => [
                    'users' => 25,
                    'warehouses' => 2,
                    'reps' => 20,
                    'skus' => 5000,
                    'storage_mb' => 2048,
                ],
                'custom_discount' => 0,
                'manager' => [
                    'name' => 'محمد علي',
                    'phone' => '+963944000000',
                    'email' => 'manager@alsham.sy',
                    'invite_via' => 'whatsapp',
                ],
            ],
            'response' => ['id' => 2, 'status' => 'provisioning', 'provisioning_job_id' => '…'],
        ],
        'PUT /admin/channels/{}' => [
            'body' => [
                'name' => 'شركة النور للتوزيع',
                'legal_form' => 'llc',
                'cr_number' => 'C12345',
                'activity_type_ids' => [3],
                'internal_note' => 'تحديث السجل',
                'reason' => 'تصحيح الاسم التجاري',
            ],
        ],
        'POST /admin/channels/{}/transition' => [
            'body' => ['to_status' => 'suspended', 'reason' => 'تأخر سداد فاتورة المنصة'],
            'response' => ['status' => 'suspended', 'allowed_next' => ['active', 'archived']],
        ],
        'POST /admin/channels/{}/retry-provisioning' => [
            'body' => new stdClass,
            'response' => ['job_id' => '…'],
        ],
        'POST /governorates' => [
            'body' => ['name_ar' => 'ريف دمشق', 'name_en' => 'Rif Dimashq', 'code' => 'RDI', 'reason' => 'إضافة محافظة'],
        ],
        'PATCH /governorates/{}/status' => [
            'body' => ['status' => 'disabled', 'reason' => 'دمج إداري'],
        ],
        'POST /platform/audit/export' => [
            'body' => ['filters' => ['date_from' => '2026-09-01', 'date_to' => '2026-09-17']],
            'response' => ['job_id' => '01h…'],
        ],
    ];
}

function notes(): array
{
    return [
        'POST /platform/auth/login' => 'Seeded admin has no 2FA → token. With 2FA: {requires_2fa, challenge_token}. Wrong credentials = generic 401. expires_at is nominal.',
        'POST /platform/auth/2fa/verify' => 'Body {challenge_token, code}. Challenge 10 min / 5 attempts. Idempotency exempt.',
        'GET /platform/auth/me' => 'Identical to GET /platform/me.',
        'POST /platform/auth/confirm-password' => 'Opens a 15-minute window used only by PUT /platform/me/password. Replay the original write with the same key.',
        'PUT /platform/me' => 'Full object — name required even for a one-field edit. Phone is Syrian, normalised server-side.',
        'PUT /platform/me/password' => 'Needs confirm-password window. Replay same idempotency key.',
        'POST /platform/me/2fa/enable' => 'secret is otpauth:// URI. qr_svg is an empty <svg/>. 2FA is not on until confirm.',
        'POST /platform/me/2fa/confirm' => '8 recovery codes once. Wrong code 401 otp_invalid, 2FA stays off.',
        'GET /platform/me/2fa/recovery-codes' => 'Returns codes_remaining only — never the codes again.',
        'POST /platform/me/api-tokens' => 'Current password in password_confirmation. Checked inline, not the 15-min window. Token plaintext once.',
        'GET /platform/iam/permissions' => '133 seeded codes (DOC-08 has 170). Paginated. filter[system] platform|channel|warehouse|app.',
        'GET /platform/iam/permissions/{}/holders' => 'users[].name is "#id". Capped at 50. Not a list envelope.',
        'POST /platform/iam/roles' => '201 status=draft. Grants nothing until a second admin approves.',
        'POST /platform/iam/roles/{}/approve' => 'Creator cannot approve → 403 sod_violation. Never retry.',
        'PUT /platform/iam/roles/{}/permissions' => 'Full replacement. Gated on ad.iam.role_create.',
        'POST /platform/iam/assignments' => 'Max 50 ids. 200 with assigned[] and rejected[].',
        'DELETE /platform/iam/assignments' => 'DELETE with body {user_id, role_id, reason}.',
        'POST /platform/iam/simulate' => 'tenant layer is hardcoded pass. allowed = guard && (permission || temp-grant) && !sod.',
        'POST /platform/iam/temp-grants' => 'reason ≥ 20 chars. duration_minutes 1–240. Requester cannot approve.',
        'POST /platform/iam/approval-requests/{}/decide' => 'Read executed, not status.',
        'GET /platform/iam/reviews/{}' => 'Unpaginated. Item vocabulary keep|revoke.',
        'POST /platform/audit/export' => 'Body key filters (plural). No status poll. Horizon required.',
        'GET /admin/channels' => 'MOVING. Role platform_admin. page/per_page only — no search. created_at UTC.',
        'POST /admin/channels' => 'MOVING. Catalog body (not name+slug only). status prohibited. Lands in provisioning.',
        'GET /admin/channels/{}' => 'Nested detail with allowed_next, plan, limits, genuine KPI zeros, manager null until BE-T07.',
        'PUT /admin/channels/{}' => 'Partial + required reason. status prohibited — use transition.',
        'DELETE /admin/channels/{}' => '204 no body. Role platform_admin. Soft delete, no restore.',
        'POST /admin/channels/{}/retry-provisioning' => 'LIVE on moving prefix. Returns {job_id}. Safe to repeat. No poll route.',
        'POST /admin/channels/{}/transition' => 'LIVE on moving prefix. Body {to_status, reason}. 422 unknown state, 409 illegal_transition. Buttons from allowed_next.',
        'GET /governorates' => 'MOVING → /platform/refs/governorates. Multi-guard. Do not build an admin screen without one constant.',
        'GET /zones' => 'MOVING → /platform/refs/zones. Same warning.',
        'GET /currencies' => 'MOVING → /platform/refs/currencies. decimals feeds money formatting for every client.',
        'GET /platform/content/intro' => 'Platform default. Vacant store is enabled:false with empty targeting. PUT response is {enabled} only — re-GET to populate the form.',
        'PUT /platform/content/intro' => 'Same body as channel intro. Response is {enabled} only. media_type image|video. duration ≥ 0.',
    ];
}

function postman(array $live, array $exempt): array
{
    $tree = [];
    foreach ($live as $e) {
        $uri = ltrim((string) $e['path'], '/');
        $isWrite = (bool) $e['write'];
        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Accept-Language', 'value' => '{{acceptLanguage}}'],
            ['key' => 'X-Client', 'value' => '{{client}}'],
        ];
        if ($e['auth']) {
            $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}'];
        }
        if ($isWrite) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            if ($e['idempotency']) {
                $headers[] = ['key' => 'X-Idempotency-Key', 'value' => '{{$guid}}'];
            }
        }
        $body = null;
        if ($isWrite) {
            $raw = $e['body'] === null ? new stdClass : $e['body'];
            $body = [
                'mode' => 'raw',
                'raw' => json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        }
        $urlPath = preg_replace_callback('#\{([^}]+)\}#', static fn (array $m): string => '{{'.trim($m[1], '?').'}}', $uri);
        $query = [];
        foreach ($e['query'] as $q) {
            $query[] = ['key' => $q['name'], 'value' => (string) $q['example'], 'disabled' => true];
        }
        $desc = [];
        $desc[] = '**'.($e['code'] ?? 'no EP-ID').'** · '.$e['stability'];
        if ($e['will_become']) {
            $desc[] = 'Will become `'.$e['will_become'].'`';
        }
        $desc[] = '';
        $desc[] = '| Guard | `'.$e['guard'].'` |';
        $desc[] = '| Permission | `'.($e['permission'] ?? '—').'` |';
        $desc[] = '| Role | `'.($e['role'] ?? '—').'` |';
        if ($e['note']) {
            $desc[] = '';
            $desc[] = '🟡 '.$e['note'];
        }
        $req = [
            'name' => $e['method'].' '.($e['stability'] === 'moving' ? $e['path'] : $e['catalog_path']),
            'request' => [
                'method' => $e['method'],
                'header' => $headers,
                'url' => [
                    'raw' => '{{baseUrl}}/'.$urlPath,
                    'host' => ['{{baseUrl}}'],
                    'path' => explode('/', $urlPath),
                ] + ($query !== [] ? ['query' => $query] : []),
                'description' => implode("\n", $desc),
            ] + ($body ? ['body' => $body] : []),
        ];
        if (str_ends_with((string) $e['catalog_path'], '/auth/login') || str_contains((string) $e['path'], '/auth/2fa/verify')) {
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
                    ],
                ],
            ]];
        }
        $tree[$e['folder']][] = $req;
    }
    $items = [];
    foreach ($tree as $folder => $reqs) {
        $items[] = ['name' => $folder, 'item' => $reqs];
    }

    return [
        'info' => [
            'name' => 'B2B Platform admin — live only',
            'description' => "# Platform admin — live routes only\n\nGenerated 2026-09-17 from route:list.\nImport docs/api/environments/platform-web.postman_environment.json.\nbaseUrl is the host only. Seed: admin@platform.sy / password.\nThis is NOT the channel dashboard.",
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ],
        'variable' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
            ['key' => 'token', 'value' => ''],
            ['key' => 'challenge_token', 'value' => ''],
        ],
        'item' => $items,
    ];
}
