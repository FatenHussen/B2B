<?php

declare(strict_types=1);

/**
 * Builds the Flutter-facing live contract for the field-rep app:
 *   docs/DocsLast/flutter-rep.json
 *
 * The Postman collection this once wrote beside it (`apps/rep-api.postman.json`) left
 * the package in 13a1262; `postman()` below stays behind a `--postman` flag and then
 * writes to docs/api/, not to the client folder.
 *
 * Sources: php artisan route:list JSON (refreshed here, as generate-platform-live.php
 * does) + b2b-api.catalog.json. Live overlays come from controllers/actions, not the backlog.
 */

$api  = __DIR__;
$root = dirname($api, 2);
$out  = $root.DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'DocsLast';

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
if (! is_array($routes)) {
    fwrite(STDERR, 'live-routes decode failed: '.json_last_error_msg()
        ." first=".substr((string) $routesJson, 0, 40)
        .' last='.substr((string) $routesJson, -40)
        ."\n");
    exit(1);
}
$catRaw = json_decode((string) $catJson, true);
if (! is_array($routes) || ! is_array($catRaw)) {
    fwrite(STDERR, "Failed to read live routes or catalog from {$api}\n");
    fwrite(STDERR, 'json_err='.json_last_error_msg()
        .' routes_type='.gettype($routes)
        .' cat_type='.gettype($catRaw)
        .' routes_len='.strlen((string) $routesJson)
        .' cat_len='.strlen((string) $catJson)
        ."\n");
    exit(1);
}

$catalog = [];
foreach ($catRaw['endpoints'] ?? $catRaw as $ep) {
    if (! is_array($ep) || empty($ep['method']) || empty($ep['path'])) {
        continue;
    }
    $catalog[norm((string) $ep['method'], (string) $ep['path'])] = $ep;
}

$exempt = [
    'api/v1/public/auth/request-otp',
    'api/v1/public/auth/verify-otp',
    'api/v1/public/auth/resend-otp',
];

$want = static function (string $path): bool {
    if ($path === '/health') {
        return true;
    }
    if (str_starts_with($path, '/public/auth/') || $path === '/public/refs' || $path === '/public/content/intro' || $path === '/public/app-config') {
        return true;
    }
    if ($path === '/app/session' || $path === '/app/auth/logout') {
        return true;
    }
    // The shared /app/* surfaces the rep app calls too (AP-02…AP-06, live since
    // 2026-09-20 per docs/status/06-shared-app.md). They were in forbidden() while
    // they were unbuilt, then dropped from it — which left them in neither list, so
    // the contract said nothing about routes the app is expected to call.
    if (str_starts_with($path, '/app/notifications')
        || $path === '/app/devices/push-token'
        || str_starts_with($path, '/app/sync/')
        || $path === '/app/content/home-blocks'
        || str_starts_with($path, '/app/loyalty')) {
        return true;
    }
    if ($path === '/app/pricing/quote' || str_starts_with($path, '/app/offers')) {
        return true;
    }
    if (str_starts_with($path, '/app/receipts')) {
        return true;
    }
    if (str_starts_with($path, '/app/rep/')) {
        return true;
    }

    return false;
};

$overlays = overlays();
$notes    = notes();

$live = [];
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
    [$guard] = meta($mwAll);

    foreach (explode('|', (string) $rt['method']) as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }
        $key = norm($method, $path);
        $e   = $catalog[$key] ?? $catalog[norm($method, collapse($path))] ?? null;
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $auth = $guard !== null;

        $query = [];
        if (isset($overlays[$key]['query'])) {
            $query = $overlays[$key]['query'];
        } elseif (isset($overlays[norm($method, collapse($path))]['query'])) {
            $query = $overlays[norm($method, collapse($path))]['query'];
        } elseif (is_array($e['query'] ?? null)) {
            foreach ($e['query'] as $qk => $qv) {
                if (is_array($qv)) {
                    $name = (string) ($qv['name'] ?? '');
                    $example = $qv['example'] ?? ($qv['value'] ?? '');
                } else {
                    $name = is_string($qk) ? $qk : (string) $qv;
                    $example = is_string($qk) ? $qv : '';
                }
                if ($name === '' || $name === 'sort' || str_contains($name, 'offer_only') || str_contains($name, 'available_only')) {
                    continue;
                }
                $query[] = ['name' => $name, 'example' => $example];
            }
        }

        $body = $e['body'] ?? null;
        if ($body instanceof stdClass) {
            $body = new stdClass;
        }

        $response = $e['response'] ?? null;
        if (isset($overlays[$key]['response'])) {
            $response = $overlays[$key]['response'];
        } elseif (isset($overlays[norm($method, collapse($path))]['response'])) {
            $response = $overlays[norm($method, collapse($path))]['response'];
        }

        $item = [
            'live' => true,
            'method' => $method,
            'path' => '/api/v1'.$path,
            'catalog_path' => $e['path'] ?? $path,
            'code' => $e['code'] ?? null,
            'name' => $e['name'] ?? ($method.' '.$path),
            'name_ar' => $e['name_ar'] ?? '',
            'permission' => $e['permission'] ?? null,
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

$forbidden = forbidden();
$matched = count(array_filter($live, fn (array $e): bool => $e['code'] !== null));

$pack = [
    'info' => [
        'title' => 'Field rep app — live API only',
        'generated_at' => date('Y-m-d'),
        'base_path' => '/api/v1',
        'guard' => 'app',
        'kind' => 'rep',
        'x_client' => 'rep-android | rep-ios',
        'seed' => 'demo reps +963932000001 (عمر الشامي) · +963932000002 · +963932000003 — local OTP_BYPASS=true: any code verifies, rep-* clients get all zeros; production is 6 digits',
        'rule' => 'If this file and the catalog disagree, this file wins. Do not call forbidden paths. Do not mock them. App routes do not enforce rp.* permissions.',
        'counts' => [
            'live_endpoints' => count($live),
            'matched_catalog' => $matched,
            'live_without_catalog' => count($live) - $matched,
            'forbidden' => count($forbidden),
        ],
    ],
    'headers' => [
        'Authorization' => 'Bearer {token} — required except GET /health, GET /public/refs, GET /public/content/intro, POST /public/auth/*',
        'Accept' => 'application/json',
        'Accept-Language' => 'ar — server messages may still be English; map error.code in the UI',
        'X-Client' => 'rep-android or rep-ios',
        'X-Device-Id' => 'stable UUID per install — required on /public/* and /app/*',
        'X-App-Version' => 'semver (build), e.g. 1.4.2 (142)',
        'X-Idempotency-Key' => 'UUID per user intent on every write except the three public OTP paths',
        'Content-Type' => 'application/json',
        'X-Channel-Id' => 'do not send — /app/rep sets no tenant',
    ],
    'envelope' => [
        'success' => ['data' => new stdClass, 'meta' => ['server_time' => '...']],
        'list' => ['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1]],
        'error' => ['error' => ['code' => 'not_found', 'message' => '...', 'details' => new stdClass]],
        'money' => 'integer minor units. SYP decimals = 0 so the integer is the display amount. Never /100.',
        'sort' => 'never send sort',
        'not_found' => '404 means missing OR not yours — never render forbidden',
        'permissions' => 'session.permissions lists rp.* by kind. Routes do not check them. Do not hide screens behind can(rp.*).',
    ],
    'forbidden' => $forbidden,
    'gotchas' => [
        'POST /app/rep/cart/lines increments qty on an existing (product, variant). Absolute qty: PATCH /app/rep/cart/lines/{id}. Remove: DELETE /app/rep/cart/lines/{id}.',
        'POST /app/rep/customers returns RepSourcedShop id, not retailer_id. Cart, payments and submit use RetailerProfile id from GET /customers and GET /zones/{id}/shops.',
        'Complete delivery mints receipt_no (24h). Collect with that number; a second POST /app/receipts/reserve is only for collections without a completion.',
        'max_cash_hold 0 means no cap. cash_cap_exceeded is 403, not 423.',
        'GET /app/rep/zones lists assigned coverage. POST /app/rep/zones still requests an extra zone.',
        'GET /public/refs is live (governorates, zones, activity types — never channels). Bind Flutter multi-select on zones (group by governorate_id); governorate dropdown is a filter only. GET /public/content/intro is the first-run splash (same row as PUT /platform/content/intro). GET /public/app-config is live (force-update / feature flags / maintenance). supply_channel_id still has no directory.',
        'GET /app/rep/home is the morning snapshot (EP-RP-002). Bind the six task badges from data.tasks. Do not call GET /deliveries from Home — that list materialises delivery rows. loyalty is the EP-APP-110 wallet snapshot (points 0 / bronze when empty). unread_notifications mirrors EP-CM-060 meta.unread_count. Guest has no token: 401; skip-register is local chrome with zeros.',
        'Shared app surfaces are live: GET /app/notifications (+ read-all / clear / push-token), GET|POST /app/sync/*, GET /app/content/home-blocks, GET|POST /app/loyalty. Compose home from these — do not mock them.',
    ],
    'endpoints' => $live,
];

file_put_contents(
    $out.'/flutter-rep.json',
    json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

if (in_array('--postman', $argv ?? [], true)) {
    $postman = postman($live, $exempt);
    file_put_contents(
        $api.'/b2b-rep.live.postman_collection.json',
        json_encode($postman, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
    );
}

echo 'live='.count($live).' matched='.$matched.' forbidden='.count($forbidden).PHP_EOL;

function norm(string $method, string $path): string
{
    return strtoupper($method).' '.collapse($path);
}

function collapse(string $path): string
{
    $path = '/'.ltrim($path, '/');

    return (string) preg_replace('#\{[^}]+\}#', '{}', $path);
}

function meta(array $mw): array
{
    $g = $p = $r = null;
    foreach ($mw as $m) {
        if (preg_match('#Authenticate:([\w,]+)#', (string) $m, $x)) {
            $g = $x[1];
        } elseif (preg_match('#PermissionMiddleware:(.+)$#', (string) $m, $x)) {
            $p = $x[1];
        } elseif (preg_match('#RoleMiddleware:(.+)$#', (string) $m, $x)) {
            $r = $x[1];
        }
    }

    return [$g, $p, $r];
}

function folder(string $path): string
{
    if ($path === '/health') {
        return '00. Health';
    }
    if (str_starts_with($path, '/public/auth') || $path === '/public/refs' || $path === '/public/content/intro' || $path === '/public/app-config' || $path === '/app/rep/register') {
        return '01. Auth & registration';
    }
    if ($path === '/app/session' || $path === '/app/auth/logout' || $path === '/app/rep/status' || $path === '/app/rep/home' || $path === '/app/rep/profile') {
        return '02. Session & duty';
    }
    if (str_starts_with($path, '/app/rep/customers') || str_starts_with($path, '/app/rep/zones')) {
        return '03. Field — zones & customers';
    }
    if ($path === '/app/rep/products' || str_starts_with($path, '/app/rep/products/') || str_starts_with($path, '/app/offers') || $path === '/app/pricing/quote') {
        return '04. Catalog, offers & quote';
    }
    if (str_starts_with($path, '/app/rep/cart')) {
        return '05. Cart';
    }
    if ($path === '/app/rep/orders') {
        return '05. Cart';
    }
    if (str_starts_with($path, '/app/rep/assignments') || str_starts_with($path, '/app/rep/scheduled-orders')) {
        return '06. Assignments';
    }
    if (str_starts_with($path, '/app/rep/warehouse-receipts')) {
        return '07. Warehouse pickup';
    }
    if (str_starts_with($path, '/app/rep/deliveries') || str_starts_with($path, '/app/rep/locations') || str_starts_with($path, '/app/rep/return-requests')) {
        return '08. Delivery & returns';
    }
    if (str_starts_with($path, '/app/rep/payments') || str_starts_with($path, '/app/rep/wallet') || str_starts_with($path, '/app/rep/receivables') || str_starts_with($path, '/app/receipts')) {
        return '09. Wallet & cash';
    }
    if (str_starts_with($path, '/app/notifications') || $path === '/app/devices/push-token') {
        return '10. Notifications & push';
    }
    if (str_starts_with($path, '/app/sync/')) {
        return '11. Sync';
    }
    if ($path === '/app/content/home-blocks' || str_starts_with($path, '/app/loyalty')) {
        return '12. Home content & loyalty';
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
        'GET /public/content/intro' => [
            'response' => [
                'enabled' => true,
                'text' => 'مرحباً بك في شبكة التوزيع',
                'media_type' => 'video',
                'media_id' => 'media_intro_default',
                'duration' => 8,
                'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
            ],
        ],
        'GET /public/refs' => [
            'response' => [
                'governorates' => [
                    ['id' => 1, 'name' => 'دمشق', 'order' => 1, 'status' => 'active'],
                    ['id' => 2, 'name' => 'ريف دمشق', 'order' => 2, 'status' => 'active'],
                ],
                'zones' => [
                    ['id' => 12, 'name' => 'المزة', 'governorate_id' => 1, 'district' => 'المزة', 'order' => 1, 'status' => 'active'],
                    ['id' => 13, 'name' => 'المالكي', 'governorate_id' => 1, 'district' => 'المالكي', 'order' => 2, 'status' => 'active'],
                    ['id' => 21, 'name' => 'جرمانا', 'governorate_id' => 2, 'district' => 'جرمانا', 'order' => 1, 'status' => 'active'],
                ],
                'activity_types' => [
                    ['id' => 3, 'name' => 'بقالة', 'icon' => 'grocery', 'order' => 1, 'status' => 'active', 'suggested_category_ids' => [10]],
                    ['id' => 2, 'name' => 'سوبر ماركت', 'icon' => 'cart', 'order' => 2, 'status' => 'active', 'suggested_category_ids' => [10]],
                ],
                'root_categories' => [
                    ['id' => 10, 'name' => 'مواد غذائية', 'icon' => null, 'image' => null, 'order' => 1, 'status' => 'active'],
                ],
                'sale_units' => [
                    ['id' => 3, 'name' => 'قطعة', 'abbr' => 'pcs', 'default_factor' => 1, 'status' => 'active'],
                ],
                'equipments' => [
                    ['id' => 1, 'name' => 'ثلاجة عرض', 'icon' => null, 'order' => 1, 'status' => 'active'],
                ],
                'sync_cursor' => 'c_20260919100000',
            ],
        ],
        'GET /app/rep/products' => [
            'query' => [
                ['name' => 'page', 'example' => '1'],
                ['name' => 'per_page', 'example' => '25'],
                ['name' => 'filter[search]', 'example' => 'زيت'],
                ['name' => 'filter[category_id]', 'example' => '340'],
                ['name' => 'filter[brand_id]', 'example' => '12'],
                ['name' => 'filter[channel_id]', 'example' => '1'],
                ['name' => 'barcode', 'example' => ''],
                ['name' => 'zone', 'example' => '12'],
            ],
            'response' => [[
                'id' => 880,
                'name' => 'زيت دوار الشمس 1 لتر',
                'image' => null,
                'brand' => ['id' => 12, 'name' => 'نور'],
                'channel' => ['id' => 1, 'name' => 'شركة النور'],
                'price' => ['type' => 'tiered', 'value' => 12000, 'label' => 'السعر حسب الكمية'],
                'availability' => 'in_stock',
                'variants' => [['id' => 1, 'label' => 'حبة', 'barcode' => null]],
            ]],
        ],
        'GET /app/rep/products/{}' => [
            'response' => [
                'id' => 880,
                'name' => 'زيت دوار الشمس 1 لتر',
                'image' => null,
                'brand' => ['id' => 12, 'name' => 'نور'],
                'channel' => ['id' => 1, 'name' => 'شركة النور'],
                'price' => ['type' => 'tiered', 'value' => 12000, 'label' => 'السعر حسب الكمية'],
                'availability' => 'in_stock',
                'variants' => [['id' => 1, 'label' => 'حبة', 'barcode' => null]],
                'images' => [],
                'long_description' => null,
            ],
        ],
        'GET /app/rep/customers' => [
            'query' => [
                ['name' => 'page', 'example' => '1'],
                ['name' => 'per_page', 'example' => '25'],
                ['name' => 'filter[search]', 'example' => 'النور'],
            ],
            'response' => [[
                'id' => 481,
                'shop_name' => 'بقالية النور',
                'logo' => null,
                'zone_id' => 12,
                'zone' => 'المزة',
                'address' => 'المزة فيلات شرقية',
                'phone' => '+963931000002',
                'lat' => 33.51,
                'lng' => 36.27,
                'is_open' => true,
                'is_active' => true,
                'last_order_at' => null,
            ]],
        ],
        'GET /app/rep/customers/{}' => [
            'response' => [
                'id' => 481,
                'shop_name' => 'بقالية النور',
                'logo' => null,
                'zone_id' => 12,
                'zone' => 'المزة',
                'address' => 'المزة فيلات شرقية',
                'phone' => '+963931000002',
                'lat' => 33.51,
                'lng' => 36.27,
                'is_open' => true,
                'is_active' => true,
                'last_order_at' => null,
                'owner_name' => 'أبو سامر',
                'activity_type_id' => 3,
                'categories' => [1],
                'equipments' => [],
            ],
        ],
        'GET /app/rep/zones' => [
            'response' => [[
                'id' => 12,
                'name' => 'المزة',
                'governorate_id' => 1,
                'shops_count' => 4,
            ]],
        ],
        'GET /app/rep/orders' => [
            'response' => [[
                'id' => 9001,
                'sub_order_no' => 'SO-9001',
                'invoice_no' => null,
                'created_at' => '2026-03-01T10:00:00+03:00',
                'status' => 'pending',
                'shop' => 'بقالية النور',
                'zone' => 'المزة',
                'channel' => 'شركة النور',
                'total' => 47040,
            ]],
        ],
        'GET /app/rep/zones/{}/shops' => [
            'query' => [
                ['name' => 'page', 'example' => '1'],
                ['name' => 'per_page', 'example' => '25'],
                ['name' => 'search', 'example' => 'النور'],
            ],
            'response' => [[
                'id' => 481,
                'shop_name' => 'بقالية النور',
                'logo' => null,
                'zone_id' => 12,
                'zone' => 'المزة',
                'address' => 'المزة فيلات شرقية',
                'phone' => '+963931000002',
                'lat' => 33.51,
                'lng' => 36.27,
                'is_open' => true,
                'is_active' => true,
                'last_order_at' => null,
            ]],
        ],
        'GET /app/session' => [
            'response' => [
                'user' => [
                    'id' => 70,
                    'name' => 'أحمد العلي',
                    'user_type' => 'rep',
                    'profile_completed' => true,
                    'avatar' => null,
                ],
                'permissions' => [
                    'rp.delivery.accept',
                    'rp.delivery.deliver',
                    'rp.delivery.postpone',
                    'rp.delivery.return_request',
                    'rp.payment.collect',
                    'rp.payment.withdraw',
                    'rp.wallet.view',
                    'rp.warehouse.receive',
                ],
                'feature_flags' => [
                    'offline_orders' => false,
                    'loyalty' => false,
                ],
                'sync_cursor' => '',
                'server_time' => '2026-03-01T09:12:44+03:00',
                'requires_legal_accept' => false,
                'legal' => [
                    'privacy_version' => '2026-03',
                    'terms_version' => '2026-01',
                ],
                'commercial_limits' => [
                    'max_discount_percent' => 5,
                    'max_cash_hold' => 0,
                ],
                'duty' => [
                    'on_duty' => false,
                    'tracking_enabled' => false,
                ],
            ],
        ],
        'GET /app/rep/home' => [
            'response' => [
                'greeting' => ['name' => 'أحمد العلي', 'avatar' => null],
                'server_time' => '2026-03-01T09:12:44+03:00',
                'on_duty' => true,
                'tracking_enabled' => true,
                'tasks' => [
                    'orders_today' => 0,
                    'deliveries_pending' => 3,
                    'collected_today' => 48000,
                    'assignments' => 2,
                    'scheduled' => 1,
                    'warehouse_receipts' => 2,
                ],
                'loyalty' => ['points' => 0, 'tier' => 'bronze', 'next_tier' => ['name' => 'silver', 'remaining' => 1000]],
                'unread_notifications' => 0,
            ],
        ],
        'GET /app/rep/cart' => [
            'response' => [
                'sections' => [[
                    'retailer' => ['id' => 481, 'shop_name' => 'بقالية النور', 'zone_id' => 12],
                    'channel' => ['id' => 1, 'name' => 'شركة النور'],
                    'created_at' => '2026-03-01T10:00:00+03:00',
                    'lines' => [[
                        'id' => 11,
                        'product_id' => 880,
                        'name' => 'زيت دوار الشمس 1 لتر',
                        'qty' => 4,
                        'unit_price' => 12000,
                        'line_total' => 48000,
                    ]],
                    'total' => 48000,
                    'discount' => 0,
                ]],
            ],
        ],
        'POST /app/rep/cart/lines' => [
            'response' => [
                'sections' => [[
                    'retailer' => ['id' => 481, 'shop_name' => 'بقالية النور', 'zone_id' => 12],
                    'channel' => ['id' => 1, 'name' => 'شركة النور'],
                    'created_at' => '2026-03-01T10:00:00+03:00',
                    'lines' => [[
                        'id' => 11,
                        'product_id' => 880,
                        'name' => 'زيت دوار الشمس 1 لتر',
                        'qty' => 4,
                        'unit_price' => 12000,
                        'line_total' => 48000,
                    ]],
                    'total' => 48000,
                    'discount' => 0,
                ]],
            ],
        ],
        'GET /app/rep/warehouse-receipts' => [
            'response' => [
                'date' => '2026-03-01',
                'rep_name' => 'أحمد العلي',
                'count' => 1,
                'orders' => [[
                    'sub_order_id' => 9001,
                    'order_no' => 'SO-9001',
                    'shop' => 'بقالية النور',
                    'zone' => 'المزة',
                    'handover_id' => 44,
                ]],
            ],
        ],
        'GET /app/rep/deliveries' => [
            'response' => [
                'zones' => [[
                    'name' => 'المزة',
                    'total' => 4,
                    'delivered' => 1,
                    'cards' => [[
                        'id' => 9001,
                        'shop' => 'بقالية النور',
                        'zone' => 'المزة',
                        'channel' => 'شركة النور',
                        'invoice_no' => 'INV-501',
                        'ordered_at' => '2026-03-01T09:10:00+03:00',
                        'status' => 'on_the_way',
                        'border_color' => 'green',
                    ]],
                ]],
            ],
        ],
        'GET /app/rep/deliveries/{}' => [
            'response' => [
                'lines' => [[
                    'id' => 1,
                    'image' => null,
                    'name' => 'زيت دوار الشمس 1 لتر',
                    'brand' => 'نور',
                    'variant' => null,
                    'qty' => 4,
                    'qty_delivered' => 0,
                    'price' => 12000,
                    'status' => 'pending',
                ]],
                'invoice_total' => 48000,
            ],
        ],
        'POST /app/rep/deliveries/{}/complete' => [
            'response' => [
                'invoice' => ['no' => 'INV-501', 'total' => 48000],
                'receipt_no' => 'RCPT-10041',
                'ask_payment' => true,
            ],
        ],
    ];
}

function notes(): array
{
    return [
        'GET /app/session' => 'Rep-only extras: commercial_limits and duty {on_duty, tracking_enabled}. user.avatar is always null. max_cash_hold 0 = no cap. permissions are kind-based, not Spatie grants; routes do not check them.',
        'GET /app/rep/home' => 'Morning snapshot. Bind the six task badges here — do not call GET /deliveries from Home (that list materialises rows). loyalty is the EP-APP-110 snapshot (points/tier; bronze/0 when empty). unread_notifications is meta.unread_count of EP-CM-060. collected_today is integer minor units. 401 without a bearer; guest browse is local.',
        'GET /public/content/intro' => 'No auth. Same singleton PUT /platform/content/intro writes. Vacant store: enabled false, text/media null, duration 0 — that is correct, do not fake a video. Returning token skips this screen. media_id is opaque, not a URL (http → play; else assets/intro/{id}; else logo+text). Ignore targeting on first run. Do not call /platform or /channel intro (wrong_guard).',
        'GET /public/refs' => 'Flat arrays, not nested. Flutter dropdowns: governorates = single-select FILTER (do not POST). zones = multi-select, value=id, label=name, group by governorate_id, POST as zone_ids:[12,13] (min 1). activity_types = single-select → activity_type_id. Hide status!=active. Channels are never here.',
        'POST /app/rep/register' => 'Requires the registration-ability token from verify-otp. Response token replaces it (ability *). zones[].name is null — resolve from GET /public/refs. supply_channel_id has no directory; it comes from the channel team or an invite.',
        'GET /app/rep/products' => 'Paginated. Allowed filters: filter[category_id], filter[brand_id], filter[channel_id], filter[search], barcode, zone (for price). Do not send sort, filter[offer_only], filter[available_only]. Card includes brand, image (often null), variants[].',
        'GET /app/rep/products/{}' => 'Detail plus images[] and long_description. 404 outside the rep channel.',
        'GET /app/rep/zones/{}/shops' => 'Paginated. Search is top-level `search`, not filter[search]. Same shop card as GET /customers. is_open is hardcoded true. last_order_at is always null. 403 zone_not_covered if the zone is not assigned.',
        'GET /app/rep/customers' => 'Paginated. Search is filter[search]. id is RetailerProfile id — this is retailer_id everywhere else. Card includes zone name, phone, address, lat/lng, logo=null.',
        'GET /app/rep/customers/{}' => 'Full shop card plus owner_name, activity_type_id, categories, equipments. 404 if not sourced and not an active shop in coverage.',
        'GET /app/rep/zones' => 'Assigned coverage: id, name, governorate_id, shops_count. Governorate label from GET /public/refs.',
        'GET /app/rep/orders' => 'Sub-orders for this rep_id. invoice_no null until issued.',
        'POST /app/rep/customers' => 'client_op_id required; replay returns the same sourced-shop row. Returned id is NOT retailer_id — reload GET /customers. Optional address, category_ids, equipment_ids.',
        'POST /app/rep/cart/lines' => 'Increments qty if the (product, variant) exists. No PATCH/DELETE.',
        'GET /app/rep/cart' => 'Grouped by shop. Lines include name and line_total. submit uses retailer.id.',
        'POST /app/rep/cart/sections/{}/submit' => 'Path param is retailer_id. 403 discount_cap_exceeded if discount_percent > session.commercial_limits.max_discount_percent. note is persisted.',
        'GET /app/rep/assignments' => 'Unpaginated array. invoice_no is always null. id is sub_order id.',
        'GET /app/rep/scheduled-orders' => 'Unpaginated. Query `date` (Y-m-d). shop_logo always null. id is sub_order id.',
        'GET /app/rep/warehouse-receipts' => 'Use handover_id in the confirm path, not sub_order_id. Query `date` optional.',
        'POST /app/rep/warehouse-receipts/{}/confirm' => 'temp_code size 4. Wrong handover id → 409 illegal_transition (not 404). Replay of a confirmed handover returns the success payload.',
        'GET /app/rep/deliveries' => 'Unpaginated. Delivered cards from other days are omitted. delivered count is today\'s delivered cards in that zone.',
        'POST /app/rep/deliveries/{}/complete' => '409 illegal_transition (no_handover) unless warehouse confirm ran. Mints receipt_no. ask_payment true — open collect.',
        'POST /app/rep/deliveries/{}/postpone' => 'Persists scheduled_at + reason. Card moves to GET /scheduled-orders.',
        'POST /app/rep/locations/ping' => '422 off_duty if not on duty. 429 rate_limited if last ping < 30s. Batch in pings[].',
        'POST /app/receipts/reserve' => 'Empty body {}. Use when collecting without a completion receipt. 24h expiry.',
        'POST /app/rep/payments' => 'amount integer. 409 duplicate_receipt_no. 403 cash_cap_exceeded. 422 receipt_not_reserved / receipt_expired. client_op_id replay returns the same payment.',
        'GET /app/offers' => 'Paginated. Empty list is valid when no offer matches the rep zone/activity/channel. company stays hidden.',
        'POST /app/pricing/quote' => 'Reps skip retailer visibility; still send the shop zone_id. Never send unit_price.',
    ];
}

function forbidden(): array
{
    return [
        ['method' => 'GET', 'path' => '/api/v1/app/rep/return-requests', 'code' => null, 'reason' => 'Create only — no list or detail'],
        ['method' => 'GET', 'path' => '/api/v1/app/rep/discount-cap', 'code' => null, 'reason' => 'Cap is session.commercial_limits.max_discount_percent — no dedicated GET'],
        ['method' => 'GET', 'path' => '/api/v1/platform/content/intro', 'code' => 'EP-AD-141A', 'reason' => 'Platform admin GET — 403 wrong_guard. The app reads GET /public/content/intro (EP-PB-011)'],
        ['method' => 'PUT', 'path' => '/api/v1/platform/content/intro', 'code' => 'EP-AD-141B', 'reason' => 'Platform admin write — never call from the app'],
        ['method' => 'GET', 'path' => '/api/v1/channel/content/intro', 'code' => 'EP-SC-100A', 'reason' => 'Channel dashboard — 403 wrong_guard on the app'],
        ['method' => 'PUT', 'path' => '/api/v1/channel/content/intro', 'code' => 'EP-SC-100B', 'reason' => 'Channel dashboard write — never call from the app'],
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
            ['key' => 'X-App-Version', 'value' => '{{appVersion}}'],
            ['key' => 'X-Device-Id', 'value' => '{{deviceId}}'],
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
            $query[] = [
                'key' => $q['name'],
                'value' => (string) $q['example'],
                'disabled' => true,
            ];
        }

        $desc = [];
        $desc[] = '**'.($e['code'] ?? 'no EP-ID').'** · live';
        $desc[] = '';
        $desc[] = '| | |';
        $desc[] = '|---|---|';
        $desc[] = '| Guard | `'.$e['guard'].'` |';
        $desc[] = '| Permission in catalog | `'.($e['permission'] ?? '—').'` — not enforced on /app/rep |';
        $desc[] = '| Idempotency | '.($isWrite ? ($e['idempotency'] ? 'required' : '**exempt**') : 'n/a').' |';
        if ($e['name_ar']) {
            $desc[] = '| Name | '.$e['name_ar'].' |';
        }
        if ($e['note']) {
            $desc[] = '';
            $desc[] = '🟡 '.$e['note'];
        }
        if ($e['description']) {
            $desc[] = '';
            $desc[] = $e['description'];
        }
        if ($e['response'] !== null) {
            $desc[] = '';
            $desc[] = '**Example `data`:**';
            $desc[] = '```json';
            $desc[] = json_encode($e['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $desc[] = '```';
        }

        $req = [
            'name' => $e['method'].' '.$e['catalog_path'],
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

        $path = $e['catalog_path'];
        $capture = null;
        if (str_ends_with($path, '/auth/verify-otp')) {
            $capture = <<<'JS'
const j = pm.response.json();
const d = j.data || {};
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (d.token) { store.set('token', d.token); console.log('token saved'); }
if (d.otp_id) { store.set('otp_id', d.otp_id); }
JS;
        } elseif (str_ends_with($path, '/auth/request-otp')) {
            $capture = <<<'JS'
const j = pm.response.json();
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (j.data && j.data.otp_id) { store.set('otp_id', j.data.otp_id); console.log('otp_id saved'); }
JS;
        } elseif (str_ends_with($path, '/rep/register')) {
            $capture = <<<'JS'
const j = pm.response.json();
const d = j.data || {};
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (d.token) { store.set('token', d.token); console.log('registration token replaced'); }
JS;
        } elseif (str_ends_with($path, '/deliveries/{id}/complete') || str_contains($path, '/receipts/reserve')) {
            $capture = <<<'JS'
const j = pm.response.json();
const d = j.data || {};
const store = pm.environment.name ? pm.environment : pm.collectionVariables;
if (d.receipt_no) { store.set('receipt_no', d.receipt_no); console.log('receipt_no saved'); }
JS;
        }
        if ($capture !== null) {
            $req['event'] = [[
                'listen' => 'test',
                'script' => ['type' => 'text/javascript', 'exec' => explode("\n", $capture)],
            ]];
        }

        $tree[$e['folder']][] = $req;
    }

    $items = [];
    foreach ($tree as $folder => $reqs) {
        $items[] = [
            'name' => $folder,
            'item' => $reqs,
        ];
    }

    return [
        'info' => [
            'name' => 'B2B Field rep — live only',
            'description' => "# Field rep app — live routes only\n\nGenerated 2026-09-16 from `php artisan route:list`. Nothing here 404s because it was never built.\n\nImport `docs/api/environments/rep-android.postman_environment.json`.\n`baseUrl` is the **host only** (`http://127.0.0.1:8000`). Each request carries `api/v1` in its path.\n\nOTP on staging is `000000`. Local OTP is in `storage/logs/laravel.log` as `[OTP]`.\n\n⚠️ Postman's `{{"."\$guid}}` regenerates per send. The Flutter client must reuse **one key per user intent**.",
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
        ],
        'variable' => [
            ['key' => 'baseUrl', 'value' => 'http://127.0.0.1:8000'],
            ['key' => 'token', 'value' => ''],
            ['key' => 'otp_id', 'value' => ''],
            ['key' => 'receipt_no', 'value' => ''],
        ],
        'item' => $items,
    ];
}
