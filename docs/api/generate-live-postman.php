<?php
/**
 * Build a Postman collection from LIVE routes only (php artisan route:list),
 * enriched with example bodies from the API catalog where the path matches.
 *
 * Structure:  App folder -> role/module sub-folder -> request
 */

$sp   = __DIR__;
$root = dirname(__DIR__, 2);

$routes  = json_decode(file_get_contents($sp . '/.live-routes.json'), true);
$catalog = json_decode(file_get_contents($root . '/docs/api/b2b-api.catalog.json'), true);

// ---- index the catalog by normalised METHOD path
$norm = static fn(string $m, string $p): string
    => strtoupper($m) . ' ' . rtrim(preg_replace('#\{[^}]+\}#', '{}', $p), '/');

$cat = [];
foreach ($catalog['endpoints'] as $e) {
    $cat[$norm($e['method'], $e['path'])] = $e;
}

// ---- idempotency-exempt paths (config/core.php)
$exempt = [
    'api/v1/public/auth/request-otp', 'api/v1/public/auth/verify-otp', 'api/v1/public/auth/resend-otp',
    'api/v1/platform/auth/login', 'api/v1/platform/auth/2fa/verify',
    'api/v1/channel/auth/request-otp', 'api/v1/channel/auth/verify-otp',
    'api/v1/warehouse/auth/device-login',
];

/** Which app folder + role sub-folder a path belongs to. */
function place(string $path, string $mw): array
{
    // ---------- Retailer app
    if (str_starts_with($path, '/app/retailer/register')) return ['01. Retailer app', '00. Auth & registration'];
    if (str_starts_with($path, '/app/retailer/home') || str_starts_with($path, '/app/retailer/categories')
        || str_starts_with($path, '/app/retailer/products') || str_starts_with($path, '/app/retailer/brands')
        || str_starts_with($path, '/app/retailer/shortages')) return ['01. Retailer app', '01. Catalog'];
    if (str_starts_with($path, '/app/retailer/cart')) return ['01. Retailer app', '02. Cart'];
    if (str_starts_with($path, '/app/retailer/orders')) return ['01. Retailer app', '03. Orders'];
    if (str_starts_with($path, '/app/retailer/receipts') || str_starts_with($path, '/app/retailer/reps'))
        return ['01. Retailer app', '04. Receiving & rating'];
    if (str_starts_with($path, '/app/retailer/return-requests')) return ['01. Retailer app', '05. Returns'];
    if (str_starts_with($path, '/app/retailer/')) return ['01. Retailer app', '01. Catalog'];

    // ---------- Rep app
    if (str_starts_with($path, '/app/rep/register')) return ['02. Field rep app', '00. Auth & registration'];
    if (str_starts_with($path, '/app/rep/status') || str_starts_with($path, '/app/rep/zones')
        || str_starts_with($path, '/app/rep/customers')) return ['02. Field rep app', '01. Duty, zones & customers'];
    if (str_starts_with($path, '/app/rep/products')) return ['02. Field rep app', '02. Catalog'];
    if (str_starts_with($path, '/app/rep/cart')) return ['02. Field rep app', '03. Cart'];
    if (str_starts_with($path, '/app/rep/assignments') || str_starts_with($path, '/app/rep/scheduled-orders'))
        return ['02. Field rep app', '04. Assignments'];
    if (str_starts_with($path, '/app/rep/warehouse-receipts')) return ['02. Field rep app', '05. Warehouse pickup'];
    if (str_starts_with($path, '/app/rep/deliveries') || str_starts_with($path, '/app/rep/locations'))
        return ['02. Field rep app', '06. Delivery'];
    if (str_starts_with($path, '/app/rep/return-requests')) return ['02. Field rep app', '07. Returns'];
    if (str_starts_with($path, '/app/rep/')) return ['02. Field rep app', '02. Catalog'];

    // ---------- Shared app surface (both mobile apps)
    if (str_starts_with($path, '/app/auth') || $path === '/app/session')
        return ['00. Shared — public & app', '02. App session'];
    if (str_starts_with($path, '/app/pricing')) return ['00. Shared — public & app', '03. Pricing'];
    if (str_starts_with($path, '/app/offers'))  return ['00. Shared — public & app', '04. Offers'];
    if (str_starts_with($path, '/public/'))     return ['00. Shared — public & app', '01. Public OTP'];
    if ($path === '/health')                    return ['00. Shared — public & app', '00. Health'];

    // ---------- Channel dashboard
    if (str_starts_with($path, '/channel/auth')) return ['03. Channel dashboard', '00. Auth'];
    if ($path === '/channel') return ['03. Channel dashboard', '01. Channel settings'];
    if (str_starts_with($path, '/channel/zones')) return ['03. Channel dashboard', '02. Zone coverage'];
    if (str_starts_with($path, '/channel/products') || str_starts_with($path, '/channel/brands')
        || str_starts_with($path, '/channel/categories') || str_starts_with($path, '/channel/catalog'))
        return ['03. Channel dashboard', '03. Catalog'];
    if (str_starts_with($path, '/channel/price-lists') || str_starts_with($path, '/channel/pricing')
        || str_starts_with($path, '/channel/reps')) return ['03. Channel dashboard', '04. Pricing'];
    if (str_starts_with($path, '/channel/offers')) return ['03. Channel dashboard', '05. Offers'];
    if (str_starts_with($path, '/channel/inventory')) return ['03. Channel dashboard', '06. Inventory'];
    if (str_starts_with($path, '/channel/sub-orders')) return ['03. Channel dashboard', '07. Sub-orders'];
    if (str_starts_with($path, '/channel/return-requests')) return ['03. Channel dashboard', '08. Returns'];
    if (str_starts_with($path, '/channel/')) return ['03. Channel dashboard', '03. Catalog'];

    // ---------- Warehouse dashboard
    if (str_starts_with($path, '/warehouse/auth')) return ['04. Warehouse dashboard', '00. Device login'];
    if (str_starts_with($path, '/warehouse/queues')) return ['04. Warehouse dashboard', '01. Queues'];
    if (str_starts_with($path, '/warehouse/picking-lists')) return ['04. Warehouse dashboard', '02. Picking'];
    if (str_starts_with($path, '/warehouse/packing')) return ['04. Warehouse dashboard', '03. Packing'];
    if (str_starts_with($path, '/warehouse/handovers')) return ['04. Warehouse dashboard', '04. Handover'];
    if (str_starts_with($path, '/warehouse/receiving')) return ['04. Warehouse dashboard', '05. Receiving'];
    if (str_starts_with($path, '/warehouse/stocktakes')) return ['04. Warehouse dashboard', '06. Stocktake'];
    if (str_starts_with($path, '/warehouse/returns')) return ['04. Warehouse dashboard', '07. Return sorting'];
    if (str_starts_with($path, '/warehouse/')) return ['04. Warehouse dashboard', '01. Queues'];

    // ---------- Platform admin
    if (str_starts_with($path, '/platform/auth')) return ['05. Platform admin', '00. Auth'];
    if (str_starts_with($path, '/platform/me')) return ['05. Platform admin', '01. My account'];
    if (str_starts_with($path, '/platform/iam')) return ['05. Platform admin', '02. IAM'];
    if (str_starts_with($path, '/platform/audit')) return ['05. Platform admin', '03. Audit'];
    if (str_starts_with($path, '/admin/channels')) return ['05. Platform admin', '04. Channels (MOVING path)'];
    if (str_starts_with($path, '/platform/')) return ['05. Platform admin', '02. IAM'];

    // ---------- Reference routes at the v1 root (moving)
    if (str_starts_with($path, '/governorates') || str_starts_with($path, '/zones'))
        return ['06. Reference (MOVING paths)', '01. Governorates & zones'];
    if (str_starts_with($path, '/currencies'))
        return ['06. Reference (MOVING paths)', '02. Currencies'];

    return ['99. Unclassified', 'misc'];
}

/** Guard + permission + role from the middleware list. */
function meta(array $mw): array
{
    $g = $p = $r = null;
    foreach ($mw as $m) {
        if (preg_match('#Authenticate:([\w,]+)#', $m, $x)) $g = $x[1];
        elseif (preg_match('#PermissionMiddleware:(.+)$#', $m, $x)) $p = $x[1];
        elseif (preg_match('#RoleMiddleware:(.+)$#', $m, $x)) $r = $x[1];
        elseif (preg_match('#Authorize:(.+)$#', $m, $x)) $p = $p ? $p . ',' . $x[1] : $x[1];
    }
    return [$g, $p, $r];
}

$tree = [];
$count = 0;

foreach ($routes as $rt) {
    if (! str_starts_with($rt['uri'], 'api/v1/')) continue;

    $uri  = $rt['uri'];                 // api/v1/...
    $path = substr($uri, 6);            // /...
    $mwAll = is_array($rt['middleware']) ? $rt['middleware'] : [$rt['middleware']];
    [$guard, $perm, $role] = meta($mwAll);

    foreach (explode('|', $rt['method']) as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) continue;

        $e = $cat[$norm($method, $path)] ?? null;

        // ---------- headers
        $headers = [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Accept-Language', 'value' => '{{acceptLanguage}}'],
            ['key' => 'X-Client', 'value' => '{{client}}'],
            ['key' => 'X-App-Version', 'value' => '{{appVersion}}'],
        ];
        if ($guard !== null) {
            $headers[] = ['key' => 'Authorization', 'value' => 'Bearer {{token}}'];
        }
        if (str_starts_with($path, '/app/') || str_starts_with($path, '/public/') || str_starts_with($path, '/warehouse/')) {
            $headers[] = ['key' => 'X-Device-Id', 'value' => '{{deviceId}}'];
        }
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if ($isWrite) {
            $headers[] = ['key' => 'Content-Type', 'value' => 'application/json'];
            if (! in_array($uri, $exempt, true)) {
                $headers[] = ['key' => 'X-Idempotency-Key', 'value' => '{{$guid}}'];
            }
        }

        // Bodies for live routes the catalog does not describe — read from the FormRequests.
        $manual = [
            'POST /channel/zones' => [
                'zone_id' => 1, 'delivery_days' => ['sun', 'mon', 'tue'],
                'delivery_fee' => '12.50', 'min_order_value' => '0.00',
            ],
            'PUT /channel' => [
                'name' => 'Demo Channel', 'legal_name' => 'Demo Channel LLC',
                'tax_number' => '1234567', 'phone' => '+963911000000',
                'email' => 'ops@demo.sy', 'settings' => new stdClass,
            ],
            // EP-AD-051 (BE-T04), on the moving prefix — the catalog body, verbatim.
            // `status` is prohibited (column default `provisioning`); manager + zones ride
            // on the provision-job payload until BE-T05 materialises them.
            'POST /admin/channels' => [
                'name' => 'شركة الشام',
                'slug' => 'al-sham',
                'legal_form' => 'llc',
                'cr_number' => 'C12345',
                'documents' => [],
                'governorate_ids' => [1],
                'zone_ids' => [12, 13],
                'activity_type_ids' => [3, 4],
                'logo' => null,
                'internal_note' => 'شراكة تجريبية',
                'plan_id' => 2,
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
            // No `status` on update since BE-T01: the field is prohibited (422) and a
            // channel changes status only through the transition route below.
            'PUT /admin/channels/{}' => ['name' => 'Acme Distribution (renamed)'],
            // EP-AD-054 (BE-T13), on the moving prefix — the catalog body, verbatim.
            'POST /admin/channels/{}/transition' => ['to_status' => 'suspended', 'reason' => 'تأخر سداد فاتورة المنصة'],
            'POST /governorates' => ['name_ar' => 'ريف دمشق', 'name_en' => 'Rif Dimashq', 'code' => 'RDI'],
            'PUT /governorates/{}' => ['name_ar' => 'ريف دمشق', 'name_en' => 'Rif Dimashq'],
            'PATCH /governorates/{}/status' => ['status' => 'disabled', 'reason' => 'Merged into another governorate'],
            'POST /zones' => [
                'governorate_id' => 1, 'name' => 'المزة', 'district' => 'المزة 86',
                'polygon' => null, 'order' => 1,
            ],
            'PUT /zones/{}' => ['name' => 'المزة', 'district' => 'المزة 86', 'order' => 2, 'reason' => 'Boundary correction'],
            'PATCH /zones/{}/status' => ['status' => 'disabled', 'reason' => 'No coverage this quarter'],
            'POST /currencies' => [
                'iso' => 'EUR', 'name' => 'Euro', 'symbol' => '€',
                'decimals' => 2, 'is_display_currency' => false,
            ],
            'PUT /currencies/{}' => [
                'name' => 'Euro', 'symbol' => '€', 'decimals' => 2,
                'is_display_currency' => false, 'reason' => 'Symbol corrected',
            ],
            'PATCH /currencies/{}/status' => ['status' => 'disabled', 'reason' => 'No longer quoted'],
            // A DELETE that carries a body — fetch/axios must be configured to send it.
            'DELETE /platform/iam/assignments' => ['user_id' => 9, 'role_id' => 3, 'reason' => 'Left the team'],
        ];

        // ---------- body (from the catalog when it matches)
        $body = null;
        $mk = $norm($method, $path);
        if ($isWrite && isset($manual[$mk])) {
            $body = [
                'mode' => 'raw',
                'raw'  => json_encode($manual[$mk], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        } elseif ($isWrite && $e !== null && ! empty($e['body'])) {
            $body = [
                'mode' => 'raw',
                'raw'  => json_encode($e['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'options' => ['raw' => ['language' => 'json']],
            ];
        } elseif ($isWrite && $method !== 'DELETE') {
            // No FormRequest and no catalog body: this endpoint takes an empty object.
            $body = ['mode' => 'raw', 'raw' => "{}", 'options' => ['raw' => ['language' => 'json']]];
        }

        // ---------- url with {{var}} for path params
        $urlPath = preg_replace_callback('#\{([^}]+)\}#', fn($m) => '{{' . trim($m[1], '?') . '}}', $uri);
        $segments = explode('/', $urlPath);

        $query = [];
        if ($e !== null && ! empty($e['query']) && is_array($e['query'])) {
            foreach ($e['query'] as $q) {
                $k = is_array($q) ? ($q['name'] ?? null) : $q;
                if (is_string($k) && $k !== '') $query[] = ['key' => $k, 'value' => '', 'disabled' => true];
            }
        }

        // ---------- stability
        $stability = $e !== null ? 'stable' : 'live, not in catalog';
        if (str_starts_with($path, '/admin/channels')) $stability = 'MOVING -> /platform/channels';
        if (str_starts_with($path, '/governorates'))   $stability = 'MOVING -> /platform/refs/governorates';
        if (str_starts_with($path, '/zones'))          $stability = 'MOVING -> /platform/refs/zones';
    if (str_starts_with($path, '/currencies'))     $stability = 'MOVING -> /platform/refs/currencies';

        $desc = [];
        $desc[] = '**' . ($e['code'] ?? 'no EP-ID') . '** · Stability: `' . $stability . '`';
        $desc[] = '';
        $desc[] = '| | |';
        $desc[] = '|---|---|';
        $desc[] = '| Guard | `' . ($guard ?? 'none') . '` |';
        if ($perm) $desc[] = '| Permission | `' . $perm . '` |';
        if ($role) $desc[] = '| Role | `' . $role . '` |';
        $desc[] = '| Idempotency | ' . ($isWrite ? (in_array($uri, $exempt, true) ? '**exempt** — do NOT send the key' : 'required') : 'n/a (read)') . ' |';
        if ($e !== null && ! empty($e['name_ar'])) $desc[] = '| Name | ' . $e['name_ar'] . ' |';
        if ($e !== null && ! empty($e['description'])) { $desc[] = ''; $desc[] = $e['description']; }
        if ($e !== null && ! empty($e['response'])) {
            $desc[] = '';
            $desc[] = '**Example response `data`:**';
            $desc[] = '```json';
            $desc[] = json_encode($e['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $desc[] = '```';
        }
        if ($e !== null && ! empty($e['errors'])) {
            $desc[] = '';
            $desc[] = '**Errors:** ' . implode(' · ', array_map(fn($k, $v) => "`$k` $v", array_keys($e['errors']), $e['errors']));
        }

        $name = $e['name'] ?? ($method . ' ' . $path);

        $req = [
            'name' => $method . ' ' . $path,
            'request' => [
                'method' => $method,
                'header' => $headers,
                'url' => [
                    'raw'  => '{{baseUrl}}/' . $urlPath . ($query ? '?' : ''),
                    'host' => ['{{baseUrl}}'],
                    'path' => $segments,
                ] + ($query ? ['query' => $query] : []),
                'description' => implode("\n", $desc),
            ] + ($body ? ['body' => $body] : []),
            'response' => [],
        ];

        // auto-capture tokens on the login calls. Saved to the active environment, or to
        // the collection when none is selected — with "No environment" pm.environment.set
        // silently drops the value and verify-otp then replays a stale otp_id.
        $capture = null;
        if (str_ends_with($path, '/auth/verify-otp') || str_ends_with($path, '/auth/login')
            || str_ends_with($path, '/auth/device-login') || str_ends_with($path, '/2fa/verify')) {
            $capture = <<<'JS'
const j = pm.response.json();
const d = j.data || {};
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (d.token) { store.set('token', d.token); console.log('token saved'); }
if (d.otp_id) { store.set('otp_id', d.otp_id); }
if (d.challenge_token) { store.set('challenge_token', d.challenge_token); }
JS;
        } elseif (str_ends_with($path, '/auth/request-otp')) {
            $capture = <<<'JS'
const j = pm.response.json();
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (j.data && j.data.otp_id) { store.set('otp_id', j.data.otp_id); console.log('otp_id saved'); }
JS;
        }
        if ($capture !== null) {
            $req['event'] = [[
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => explode("\n", $capture)],
            ]];
        }

        [$app, $sub] = place($path, implode(',', $mwAll));
        $tree[$app][$sub][] = $req;
        $count++;
    }
}

// Shown on EVERY folder that contains a write. Nobody reads the collection description,
// so the idempotency warning has to live where the requests are.
$GUID_WARNING = <<<'MD'
### ⚠️ `X-Idempotency-Key` in this folder is `{{$guid}}` — do not copy that into a client

Postman regenerates `{{$guid}}` on **every send**. That is correct here: each send is a
fresh test.

It is **wrong in a real client**. The frontend package requires **one key per user
intent** — generated when the user commits (taps Confirm), reused verbatim on every retry
of that intent, and replaced only when the user starts over.

A key minted per request turns one confirmed order into N orders on a bad connection —
the exact failure the header exists to prevent.

| | Postman (here) | Your client |
|---|---|---|
| New key when | every send | the user starts a new intent |
| On retry | new key | **same key** |
| Result of a retry | a second write | a replayed response |

See §2.5 of your app's file in `docs/DocsLast/`.
MD;

// ---- sort and assemble
ksort($tree);
$items = [];
foreach ($tree as $app => $subs) {
    ksort($subs);
    $subItems = [];
    foreach ($subs as $sub => $reqs) {
        usort($reqs, fn($a, $b) => strcmp($a['name'], $b['name']));

        $hasWrite = false;
        foreach ($reqs as $r) {
            if (in_array($r['request']['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $hasWrite = true;
                break;
            }
        }

        $folder = ['name' => $sub . ' (' . count($reqs) . ')', 'item' => $reqs];
        if ($hasWrite) {
            $folder['description'] = $GUID_WARNING;
        }
        $subItems[] = $folder;
    }
    $n = array_sum(array_map('count', $subs));
    $items[] = ['name' => $app . ' — ' . $n, 'item' => $subItems];
}

// ---- READ ME FIRST folder, pinned to the top
$readme = <<<'MD'
# Start here

**183 requests, every one of them live.** This collection is generated from
`php artisan route:list`, not from the API catalog — so nothing here returns 404 because
it was never built.

## 1. Set up (two minutes)

1. Import an environment from `docs/api/environments/` and select it.
2. `baseUrl` is the **host only** (`http://127.0.0.1:8000`) — each request carries
   `api/v1` in its own path.
3. Send **`GET /health`** in this folder. You should get `data.status = "ok"`.
4. Go to your app's folder and run its login request. **The token is captured
   automatically** into `{{token}}`; every other request already sends it.

## 2. Which folder is mine?

| You build | Folder | Login request |
|---|---|---|
| Retailer app | `01. Retailer app` | `00. Shared` → `01. Public OTP` |
| Field rep app | `02. Field rep app` | `00. Shared` → `01. Public OTP` |
| Channel dashboard | `03. Channel dashboard` | its `00. Auth` |
| Warehouse dashboard | `04. Warehouse dashboard` | its `00. Device login` |
| Platform admin | `05. Platform admin` | its `00. Auth` |

Inside each app folder the sub-folders are the roles/modules of that app.

**OTP is switched off during development** (`OTP_BYPASS=true` on the API): send any
6-character `code` — `000000` — to `verify-otp`. No cooldown, no rate limit. When it is
switched back on, codes are not sent anywhere locally — read them from the log:

```bash
tail -f storage/logs/laravel.log | grep OTP
```

`request-otp` saves `{{otp_id}}` for you, so `verify-otp` works without copying. Every
`otp_id` is single-use and lives 5 minutes — `otp_expired` means request a new one.

## 3. What `MOVING` means

Every request description names a stability:

| Value | Meaning | What to do |
|---|---|---|
| `stable` | registered at the path the catalog specifies | build on it |
| `MOVING -> …` | registered, but at a temporary path — **it will move** | build, but keep the base path behind **one constant** |
| `live, not in catalog` | callable; the catalog has not caught up | use it, expect the shape to be confirmed |

**21 requests are `MOVING`** and are grouped in folders labelled `(MOVING path)` so you can
see them at a glance:

| Live now | Will become |
|---|---|
| `/admin/channels…` | `/platform/channels…` |
| `/governorates…` | `/platform/refs/governorates…` |
| `/zones…` | `/platform/refs/zones…` |
| `/currencies…` | `/platform/refs/currencies…` |

Anyone who scatters a `MOVING` path through feature code redoes that work when it moves.

## 4. ⚠️ The idempotency key here is not what your client should do

Every write folder repeats this, because it is the single easiest thing to copy wrongly:
`{{$guid}}` regenerates on **every send**. Your client must instead use **one key per user
intent**, reused on every retry. See §2.5 of your app's file in `docs/DocsLast/`.

## 5. Regenerating after a merge

```bash
php artisan route:list --json > docs/api/.live-routes.json
php docs/api/generate-live-postman.php
```

The generator reads `route:list` for the routes and the catalog only for example bodies and
EP-IDs. A newly built endpoint appears automatically; one that was never built never
appears.

## 6. What is deliberately absent

180 catalogued endpoints have no route: all money (SP-13), all offline sync (SP-14),
content and loyalty (SP-15), and every platform reference screen (SP-03). They are not in
this collection because they do not exist. See `docs/DocsLast/` for what that blocks.
MD;

array_unshift($items, [
    'name' => '📖 READ ME FIRST',
    'description' => $readme,
    'item' => [[
        'name' => 'GET /health — check the API is up',
        'request' => [
            'method' => 'GET',
            'header' => [['key' => 'Accept', 'value' => 'application/json']],
            'url' => [
                'raw'  => '{{baseUrl}}/api/v1/health',
                'host' => ['{{baseUrl}}'],
                'path' => ['api', 'v1', 'health'],
            ],
            'description' => "Unguarded reachability probe. Expect `data.status = \"ok\"`.\n\n"
                . "If this fails, nothing else in the collection will work — check that\n"
                . "`php artisan serve` is running and that `baseUrl` points at it.",
        ],
        'response' => [],
        'event' => [[
            'listen' => 'test',
            'script' => ['type' => 'text/javascript', 'exec' => [
                "pm.test('API is up', () => pm.response.to.have.status(200));",
                "pm.test('envelope looks right', () => {",
                "  const j = pm.response.json();",
                "  pm.expect(j).to.have.property('data');",
                "  pm.expect(j.meta).to.have.property('server_time');",
                "});",
            ]],
        ]],
    ]],
]);

$collection = [
    'info' => [
        'name' => 'B2B API — live routes only (' . $count . ')',
        '_postman_id' => '8f3c1d20-b2b0-4a51-9e77-live' . substr(md5((string) $count), 0, 8),
        'description' => file_get_contents(__DIR__ . '/live-collection.description.md'),
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'item' => $items,
    'variable' => [
        ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
    ],
];

file_put_contents(
    $root . '/docs/api/b2b-api.live.postman_collection.json',
    json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

echo "requests: $count\n";
foreach ($tree as $app => $subs) {
    echo "  $app — " . array_sum(array_map('count', $subs)) . "\n";
    foreach ($subs as $s => $r) echo "      $s: " . count($r) . "\n";
}
