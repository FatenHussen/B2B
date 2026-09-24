<?php

declare(strict_types=1);

/**
 * Builds the channel-dashboard live contract:
 *   docs/DocsLast/channel.json
 *
 * Sources: php artisan route:list + b2b-api.catalog.json.
 * Overlays come from controllers / FormRequests (2026-09-19), not the backlog.
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
$catRaw = json_decode((string) $catJson, true);
if (! is_array($routes) || ! is_array($catRaw)) {
    fwrite(STDERR, 'Failed to read live routes or catalog: '.json_last_error_msg()."\n");
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
    'api/v1/channel/auth/request-otp',
    'api/v1/channel/auth/verify-otp',
];

$want = static function (string $path): bool {
    if ($path === '/health' || $path === '/public/refs') {
        return true;
    }
    if (str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return true;
    }

    return $path === '/channel' || str_starts_with($path, '/channel/');
};

$overlays = overlays();
$notes = notes();
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
    [$guard, $mwPerm] = meta($mwAll);

    foreach (explode('|', (string) $rt['method']) as $method) {
        if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
            continue;
        }
        $key = norm($method, $path);
        $collapsed = norm($method, collapse($path));
        $e = $catalog[$key] ?? $catalog[$collapsed] ?? null;
        $ov = $overlays[$key] ?? $overlays[$collapsed] ?? [];
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $auth = $guard !== null && $path !== '/health' && $path !== '/public/refs' && ! str_starts_with($path, '/channel/auth/');

        $query = [];
        if (isset($ov['query'])) {
            $query = $ov['query'];
        } elseif (is_array($e['query'] ?? null)) {
            foreach ($e['query'] as $qk => $qv) {
                if (is_array($qv)) {
                    $name = (string) ($qv['name'] ?? '');
                    $example = $qv['example'] ?? ($qv['value'] ?? '');
                } else {
                    $name = is_string($qk) ? $qk : (string) $qv;
                    $example = is_string($qk) ? $qv : '';
                }
                if ($name === '' || $name === 'sort') {
                    continue;
                }
                $query[] = ['name' => $name, 'example' => $example];
            }
        }

        $body = $ov['body'] ?? $e['body'] ?? null;
        if ($body instanceof stdClass) {
            $body = new stdClass;
        }

        $response = $ov['response'] ?? $e['response'] ?? null;

        $live[] = [
            'live' => true,
            'method' => $method,
            'path' => '/api/v1'.$path,
            'catalog_path' => $e['path'] ?? $path,
            'code' => $e['code'] ?? ($ov['code'] ?? null),
            'name' => $e['name'] ?? ($ov['name'] ?? ($method.' '.$path)),
            'name_ar' => $e['name_ar'] ?? ($ov['name_ar'] ?? ''),
            'permission' => $ov['permission'] ?? $e['permission'] ?? $mwPerm,
            'guard' => $auth ? ($guard ?? 'channel') : 'none',
            'auth' => $auth,
            'write' => $isWrite,
            'idempotency' => $isWrite && ! in_array($uri, $exempt, true),
            'folder' => folder($path),
            'query' => $query,
            'body' => $body,
            'response' => $response,
            'errors' => $ov['errors'] ?? $e['errors'] ?? [],
            'description' => $e['description'] ?? '',
            'note' => $notes[$key] ?? $notes[$collapsed] ?? null,
        ];
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
        'title' => 'Channel dashboard — live API only',
        'generated_at' => date('Y-m-d'),
        'base_path' => '/api/v1',
        'guard' => 'channel',
        'x_client' => 'channel-web',
        'port' => 3001,
        'seed' => '+963900000001 — role channel_manager on demo-channel (id 1). OTP_BYPASS=true only under local|testing. Staging/production ignore the flag. Without bypass, random 6 digits (not all zeros — zeros are rep-* only).',
        'not' => 'This is NOT the platform admin (guard platform, port 3000) and NOT the warehouse / Flutter apps.',
        'rule' => 'If this file and the catalog disagree, this file wins. Call only live:true. Never call forbidden. Never mock missing retailer/warehouse/product-show lists. Gate screens with permissions[] from verify-otp, not role names.',
        'counts' => [
            'live_endpoints' => count($live),
            'matched_catalog' => $matched,
            'live_without_catalog' => count($live) - $matched,
            'forbidden' => count($forbidden),
        ],
    ],
    'headers' => [
        'Authorization' => 'Bearer {token} — required except GET /health, GET /public/refs, POST /channel/auth/*',
        'Accept' => 'application/json',
        'Accept-Language' => 'ignored — SetAcceptLanguage forces locale en; map error.code in the UI',
        'X-Client' => 'channel-web',
        'X-Device-Id' => 'stable UUID — rate-limit key on OTP request',
        'X-App-Version' => 'semver (build), e.g. 1.0.0 (1)',
        'X-Idempotency-Key' => 'UUID per user intent on every write except the two channel OTP paths',
        'Content-Type' => 'application/json — except POST /channel/catalog/import (multipart/form-data)',
        'X-Channel-Id' => 'do not send — honoured only for platform_admin. Channel tenant comes from membership (defaultChannelId).',
    ],
    'envelope' => [
        'success' => ['data' => new stdClass, 'meta' => ['server_time' => '...']],
        'list' => ['data' => [], 'meta' => ['page' => 1, 'per_page' => 25, 'total' => 0, 'last_page' => 1]],
        'error' => ['error' => ['code' => 'not_found', 'message' => '...', 'permission' => 'sc.orders.confirm', 'details' => new stdClass]],
        'money' => 'integer minor units everywhere except GET/POST /channel/zones delivery_fee and min_order_value (decimal strings, 2 places). SYP decimals = 0. Never /100.',
        'sort' => 'never send sort — lists use defaultSort; Spatie rejects unknown sorts',
        'not_found' => '404 means missing OR not yours — never render forbidden',
        'permissions' => 'Hide nav items with can(code) from verify-otp.permissions. Do not branch on role name (channel_manager / sales_manager / …).',
        'dual_approval' => 'adjust / credit-note / void may return {approval_request_id} with meta.requires_dual_approval. A second user resubmits the same body plus approval_request_id + approval_reason. Same user → 403 sod_violation.',
    ],
    'forbidden' => $forbidden,
    'gotchas' => [
        'No POST /channel/auth/logout. Discard the token locally. There is no GET /channel/me — the session is the verify-otp payload (token, channels[], permissions[]).',
        'X-Channel-Id is ignored for channel users. channels[] from login is display-only — no tenant switch API on this guard.',
        'OTP_BYPASS is honoured only when APP_ENV is local or testing. Staging and production ignore it. Without bypass, channel codes are random 6 digits, not 000000. X-Client channel-web does not get the rep all-zero shortcut.',
        'GET /channel/products list rows are only id, sku, name_ar, status. GET /channel/products/{id} returns the full editor body (pricing may be null — use PUT /products/{id}/pricing). variants[].stock is available qty on the channel default warehouse (0 when none).',
        'GET /channel/categories/tree nodes include media_id (editable) and activity_type_ids; image remains a resolved URL.',
        'POST /channel/sub-orders/assign mode auto|bulk_zone is real: first on-duty rep covering the zone (or rep_id on bulk_zone). No candidate → 422.',
        'Pricing lists stop-at-first-match (retailer ← group ← zone); they do not stack adjustments.',
        'Retailer credit on_exceed=manual_approval queues GET/POST /channel/credit-approvals (EP-SC-165/165A); confirm returns 423 with approval_id until approved then consumed.',
        'Banner impressions increment when GET /app/content/home-blocks serves a Banner row. Clicks via POST /app/content/banners/{id}/click. stats.ctr is integer basis points at scale 10^4.',
        'GET /channel/offers list rows are only id, name, type, status. GET/PUT /channel/offers/{id} and activate exist. conversion_rate is scale 10^4 from unique retailer views; net_margin is linked_sales minus product cost_price.',
        'GET /channel/invoices list rows are only id, no, total, status. GET /channel/invoices/{id} returns lines[] for credit-note line_id.',
        'GET /channel/retailers lists shops in coverage zones (retailer_profile id). GET /channel/warehouses is the inventory picker.',
        'No inventing retailer 360 approval, IAM, or fake media ids — use POST /channel/media/upload.',
        'POST /channel/catalog/import is multipart: file + type=products + optional dry_run. JSON body is 422.',
        'GET/POST /channel/zones delivery_fee and min_order_value are decimal strings ("12.50"), not int money. Unique exception on this guard.',
        'DELETE /channel/zones/{id} is 204 empty body.',
        'PUT /channel/content/intro returns {enabled} only — re-GET for the form.',
        'Banner stats.ctr is integer basis points at scale 10^4 (840 clicks / 12000 impressions = 700), never a 0.07 float.',
        'GET /channel/dashboard KPIs are 0 / empty until DailySnapshot has a row. Never fabricate GMV. meta.snapshot_date may be null. Run `php artisan reports:daily-snapshots` (scheduled 00:05 Asia/Damascus) for demos.',
        'GET /channel/reports/{type} prefers a DailySnapshot whose snapshot_date is inside date_from/date_to; otherwise the latest. Unknown type still 200 with empty rows. Allowed types: sales|products|retailers|reps|zones|inventory|finance|offers|operations.',
        'GET /channel/finance/aging: buckets are channel totals; groups[] splits by zone_id or rep_id when group_by is set.',
        'POST /channel/inventory/adjust, credit-note and void may return approval_request_id instead of the mutation. Replay from a second user. channel_manager is exempt from SOD-01; still do not hide the payment button.',
        'POST /channel/payments is SOD-01 vs sc.orders.confirm → 403 sod_violation unless the user is channel_manager.',
        'Rep id everywhere (assign, settle, wallet, discount-cap, filter[rep_id]) is AppUser id from GET /channel/reps, not RepProfile id.',
        'filter[waiting_over_minutes] on sub-orders is a custom query (not Spatie AllowedFilter) and still works as filter[waiting_over_minutes].',
        'GET /public/refs (no auth) is the picker for activity_types, sale_units, root_categories, equipments. Shared GET /governorates|/zones|/currencies need a channel Bearer. Do not call /platform/refs/* (wrong_guard).',
        'Category POST parent_id is required ≥1. Tree roots are platform root_categories (level 1); channel categories hang under them.',
        'Unpaginated arrays (no page in meta): category tree, coverage zones, warehouses, sliders, loyalty rules, notification templates, dashboard, aging, report, margins, settings, intro, offer performance, banner stats, wallet.',
    ],
    'endpoints' => $live,
];

file_put_contents(
    $out.'/channel.json',
    json_encode($pack, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
);

echo 'live='.count($live).' matched='.$matched.' extra='.(count($live) - $matched).' forbidden='.count($forbidden).PHP_EOL;

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
    $g = $p = null;
    foreach ($mw as $m) {
        $m = (string) $m;
        if (preg_match('#Authenticate:([\w,]+)#', $m, $x)) {
            $g = $x[1];
        } elseif (preg_match('#PermissionMiddleware:(.+)$#', $m, $x)) {
            $p = $x[1];
        } elseif (preg_match('#Authorize:(.+)$#', $m, $x)) {
            $p = $p ? $p.','.$x[1] : $x[1];
        }
    }

    return [$g, $p];
}

function folder(string $path): string
{
    if ($path === '/health') {
        return '00. Health';
    }
    if ($path === '/public/refs' || str_starts_with($path, '/governorates') || str_starts_with($path, '/zones') || str_starts_with($path, '/currencies')) {
        return '01. Shared refs';
    }
    if (str_starts_with($path, '/channel/auth')) {
        return '02. Auth';
    }
    if ($path === '/channel' || $path === '/channel/') {
        return '03. Settings';
    }
    if (str_starts_with($path, '/channel/dashboard') || str_starts_with($path, '/channel/reports')) {
        return '04. Dashboard & reports';
    }
    if (str_contains($path, '/pricing') || str_starts_with($path, '/channel/price-lists')) {
        return '06. Pricing';
    }
    if (str_starts_with($path, '/channel/brands') || str_starts_with($path, '/channel/categories') || str_starts_with($path, '/channel/products') || str_starts_with($path, '/channel/catalog')) {
        return '05. Catalog';
    }
    if (str_starts_with($path, '/channel/offers')) {
        return '07. Offers';
    }
    if (str_starts_with($path, '/channel/inventory')) {
        return '08. Inventory';
    }
    if (str_starts_with($path, '/channel/sub-orders')) {
        return '09. Orders';
    }
    if (str_starts_with($path, '/channel/return-requests')) {
        return '10. Returns';
    }
    if (str_starts_with($path, '/channel/reps') || str_starts_with($path, '/channel/rep-')) {
        return '11. Reps';
    }
    if (str_starts_with($path, '/channel/invoices') || str_starts_with($path, '/channel/payments') || str_starts_with($path, '/channel/finance') || str_starts_with($path, '/channel/retailers')) {
        return '12. Finance';
    }
    if (str_starts_with($path, '/channel/zones')) {
        return '13. Coverage zones';
    }
    if (str_starts_with($path, '/channel/notifications')) {
        return '14. Notifications';
    }
    if (str_starts_with($path, '/channel/content')) {
        return '15. Content';
    }
    if (str_starts_with($path, '/channel/loyalty')) {
        return '16. Loyalty';
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
    $productBody = [
        'name_ar' => 'زيت دوار الشمس 1 لتر',
        'name_en' => 'Sunflower Oil 1L',
        'sku' => 'OIL-SUN-1L',
        'brand_id' => 12,
        'category_id' => 340,
        'model_no' => 'SUN-1000',
        'barcode' => '6291000000000',
        'status' => 'active',
        'short_description' => 'زيت نباتي للطبخ',
        'long_description' => '<p>زيت دوار الشمس</p>',
        'specs' => [['key' => 'الحجم', 'value' => '1 لتر']],
        'media' => ['images' => ['media_id_1'], 'primary' => 'media_id_1', 'video' => null],
        'sale_unit_id' => 3,
        'unit_factors' => [['from_unit_id' => 3, 'to_unit_id' => 5, 'factor' => 12]],
        'min_order_qty' => 1,
        'order_multiple' => 1,
        'weight_gram' => 950,
        'pricing' => [
            'type' => 'tiered',
            'base_price' => 12000,
            'currency_id' => 1,
            'tiers' => [
                ['from' => 1, 'to' => 4, 'price' => 12000],
                ['from' => 5, 'to' => 9, 'price' => 11500],
                ['from' => 10, 'to' => null, 'price' => 11000],
            ],
        ],
        'inventory' => ['tracked' => true, 'reorder_point' => 50, 'allow_backorder' => false],
        'availability' => [
            'zone_ids' => [12, 13],
            'activity_type_ids' => [3, 4],
            'retailer_group_ids' => [],
            'lead_time_days' => 2,
        ],
        'marketing' => ['tags' => ['new'], 'sliders' => ['best_selling'], 'priority' => 10],
    ];

    return [
        'GET /health' => [
            'name' => 'Health',
            'name_ar' => 'صحة الخدمة',
            'response' => [
                'status' => 'ok',
                'app' => 'B2B Platform',
                'env' => 'local',
                'checks' => ['database' => 'ok', 'cache' => 'ok', 'queue' => 'ok'],
            ],
        ],
        'GET /public/refs' => [
            'name' => 'Public refs snapshot',
            'name_ar' => 'مراجع عامة (أنواع نشاط، وحدات، فئات جذر)',
            'query' => [['name' => 'since', 'example' => 'c_20260919084100']],
            'response' => [
                'governorates' => [['id' => 1, 'name' => 'دمشق', 'order' => 1, 'status' => 'active']],
                'zones' => [['id' => 12, 'name' => 'المزة', 'governorate_id' => 1, 'district' => 'المزة', 'order' => 3, 'status' => 'active']],
                'activity_types' => [['id' => 2, 'name' => 'سوبر ماركت', 'icon' => 'cart', 'order' => 1, 'status' => 'active', 'suggested_category_ids' => [1, 3]]],
                'root_categories' => [['id' => 1, 'name' => 'مواد غذائية', 'icon' => null, 'image' => null, 'order' => 1, 'status' => 'active']],
                'sale_units' => [['id' => 1, 'name' => 'قطعة', 'abbr' => 'ق', 'default_factor' => 1, 'status' => 'active']],
                'equipments' => [['id' => 1, 'name' => 'براد عرض', 'icon' => null, 'order' => 1, 'status' => 'active']],
                'sync_cursor' => 'c_20260919084100',
            ],
        ],
        'GET /governorates' => [
            'response' => [[
                'id' => 1,
                'name' => 'دمشق',
                'name_ar' => 'دمشق',
                'name_en' => 'Damascus',
                'code' => 'DAM',
                'status' => 'active',
                'order' => 1,
                'zones_count' => 12,
            ]],
        ],
        'GET /zones' => [
            'response' => [[
                'id' => 12,
                'governorate_id' => 1,
                'name' => 'المزة',
                'district' => 'المزة',
                'polygon' => null,
                'order' => 3,
                'status' => 'active',
            ]],
        ],
        'GET /currencies' => [
            'response' => [[
                'id' => 1,
                'iso' => 'SYP',
                'name' => 'الليرة السورية',
                'symbol' => 'ل.س',
                'decimals' => 0,
                'is_display_currency' => true,
                'status' => 'active',
            ]],
        ],
        'GET /channel' => [
            'code' => null,
            'name' => 'Own channel settings',
            'name_ar' => 'إعدادات القناة',
            'permission' => 'sc.settings.view',
            'response' => [
                'id' => 1,
                'name' => 'Demo Channel',
                'slug' => 'demo-channel',
                'legal_name' => 'Demo Channel LLC',
                'tax_number' => null,
                'phone' => '+963911000000',
                'email' => null,
                'status' => 'active',
                'allowed_next' => ['suspended', 'archived'],
                'settings' => [],
                'created_at' => '2026-09-01T00:00:00+00:00',
            ],
        ],
        'PUT /channel' => [
            'code' => null,
            'name' => 'Update own channel',
            'name_ar' => 'تحديث إعدادات القناة',
            'permission' => 'sc.settings.update',
            'body' => [
                'name' => 'قناة الشام',
                'legal_name' => 'شركة الشام للتوزيع',
                'tax_number' => '123456789',
                'phone' => '+963911000000',
                'email' => 'ops@channel.sy',
                'settings' => ['locale' => 'ar'],
            ],
            'response' => [
                'id' => 1,
                'name' => 'قناة الشام',
                'slug' => 'demo-channel',
                'status' => 'active',
                'allowed_next' => ['suspended', 'archived'],
                'settings' => ['locale' => 'ar'],
            ],
        ],
        'GET /channel/zones' => [
            'code' => null,
            'name' => 'Channel coverage',
            'name_ar' => 'تغطية المناطق',
            'permission' => 'sc.zones.view',
            'response' => [[
                'id' => 1,
                'zone_id' => 12,
                'zone_name' => 'المزة',
                'delivery_days' => ['sun', 'tue', 'thu'],
                'delivery_fee' => '0.00',
                'min_order_value' => '50000.00',
            ]],
        ],
        'POST /channel/zones' => [
            'code' => null,
            'name' => 'Add coverage row',
            'name_ar' => 'إضافة تغطية منطقة',
            'permission' => 'sc.zones.manage',
            'body' => [
                'zone_id' => 12,
                'delivery_days' => ['sun', 'tue', 'thu'],
                'delivery_fee' => '0.00',
                'min_order_value' => '50000.00',
            ],
            'response' => [
                'id' => 1,
                'zone_id' => 12,
                'zone_name' => 'المزة',
                'delivery_days' => ['sun', 'tue', 'thu'],
                'delivery_fee' => '0.00',
                'min_order_value' => '50000.00',
            ],
        ],
        'DELETE /channel/zones/{}' => [
            'code' => null,
            'name' => 'Remove coverage row',
            'name_ar' => 'حذف تغطية منطقة',
            'permission' => 'sc.zones.manage',
            'body' => new stdClass,
            'response' => null,
        ],
        'GET /channel/brands' => [
            'response' => [['id' => 12, 'name_ar' => 'نور', 'name_en' => 'Nour', 'status' => 'active', 'order' => 1]],
        ],
        'GET /channel/products' => [
            'response' => [['id' => 880, 'sku' => 'OIL-SUN-1L', 'name_ar' => 'زيت دوار الشمس 1 لتر', 'status' => 'active']],
        ],
        'POST /channel/products' => [
            'body' => $productBody,
            'response' => ['id' => 880, 'sku' => 'OIL-SUN-1L'],
        ],
        'PUT /channel/products/{}' => [
            'body' => $productBody,
            'response' => ['id' => 880, 'sku' => 'OIL-SUN-1L'],
        ],
        'GET /channel/offers' => [
            'response' => [['id' => 3, 'name' => 'اشترِ 12 واحصل على 1', 'type' => 'buy_x_get_y', 'status' => 'active']],
        ],
        'GET /channel/offers/{}/performance' => [
            'response' => [
                'applied_count' => 40,
                'linked_sales' => 480000,
                'discount_given' => 12000,
                'net_margin' => 0,
                'retailers_count' => 18,
                'by_zone' => [['zone_id' => 12, 'applied_count' => 22]],
                'conversion_rate' => 0,
            ],
        ],
        'GET /channel/price-lists' => [
            'response' => [['id' => 4, 'name' => 'قائمة المزة', 'type' => 'zone', 'status' => 'active']],
        ],
        'GET /channel/invoices' => [
            'response' => [['id' => 501, 'no' => 'INV-501', 'total' => 48000, 'status' => 'open']],
        ],
        'GET /channel/sub-orders' => [
            'response' => [['id' => 9001, 'sub_order_no' => 'SO-9001', 'status' => 'pending', 'zone_id' => 12, 'total' => 48000]],
        ],
        'GET /channel/sub-orders/{}' => [
            'response' => [
                'header' => ['sub_order_no' => 'SO-9001', 'status' => 'pending', 'shop' => 'بقالية النور'],
                'lines' => [['id' => 1, 'product_id' => 880, 'qty' => 4, 'unit_price' => 12000]],
                'financials' => ['subtotal' => 48000, 'discount' => 0, 'total' => 48000],
                'note' => null,
                'timeline' => [['stage' => 'pending', 'at' => '2026-09-19T09:10:00+03:00']],
                'allowed_actions' => ['confirm', 'reject', 'edit_lines'],
            ],
        ],
        'GET /channel/dashboard' => [
            'response' => [
                'kpis' => [
                    'sales' => 0,
                    'orders_by_status' => [],
                    'cash_collected' => 0,
                    'receivables' => ['total' => 0, 'overdue' => 0],
                    'retailers' => ['active' => 0, 'registered' => 0, 'new' => 0],
                    'avg_order_value' => 0,
                    'avg_confirm_time' => 0,
                    'avg_delivery_time' => 0,
                    'fill_rate' => 0,
                ],
                'alerts' => [],
                'charts' => [
                    'daily_sales' => [],
                    'by_zone' => [],
                    'top_products' => [],
                    'top_retailers' => [],
                    'rep_performance' => [],
                    'heatmap' => [],
                ],
            ],
        ],
        'GET /channel/reports/{}' => [
            'response' => ['type' => 'sales', 'rows' => [], 'totals' => []],
        ],
        'GET /channel/reports/margins' => [
            'response' => ['by_product' => [], 'by_zone' => []],
        ],
        'GET /channel/content/banners/{}/stats' => [
            'response' => ['impressions' => 12000, 'clicks' => 840, 'ctr' => 700],
        ],
        'PUT /channel/content/intro' => [
            'response' => ['enabled' => true],
        ],
        'GET /channel/content/intro' => [
            'response' => [
                'enabled' => false,
                'text' => null,
                'media_type' => null,
                'media_id' => null,
                'duration' => 0,
                'targeting' => ['activity_type_ids' => [], 'zone_ids' => []],
            ],
        ],
        'GET /channel/content/sliders' => [
            'response' => [['id' => 4, 'name' => 'الأكثر مبيعاً', 'source' => 'algorithm', 'algorithm' => 'best_selling']],
        ],
        'GET /channel/loyalty/rules' => [
            'response' => ['retailer_rules' => [], 'rep_rules' => [], 'tiers' => []],
        ],
        'POST /channel/inventory/adjust' => [
            'response' => ['movement_id' => 7001, 'available' => 417],
        ],
        'GET /channel/finance/aging' => [
            'response' => [
                'group_by' => 'zone',
                'buckets' => ['0_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0],
            ],
        ],
        'GET /channel/reps/{}/wallet' => [
            'response' => [
                'net_balance' => 250000,
                'stats' => ['invoices_delivered' => 12, 'collected_total' => 1750000, 'receivables' => 48000],
                'today' => ['invoices' => 2, 'collected' => 90000, 'receivables' => 12000],
            ],
        ],
        'POST /channel/catalog/import' => [
            'body' => ['_multipart' => true, 'file' => '(xlsx)', 'type' => 'products', 'dry_run' => true],
        ],
        'POST /channel/auth/request-otp' => [
            'body' => ['phone' => '+963900000001'],
            'response' => ['otp_id' => 'otp_ab12cd'],
        ],
        'POST /channel/auth/verify-otp' => [
            'body' => ['otp_id' => 'otp_ab12cd', 'code' => '000000'],
            'response' => [
                'token' => '20|channel_xxxxx',
                'channels' => [['id' => 1, 'name' => 'Demo Channel']],
                'permissions' => ['sc.dashboard.view', 'sc.orders.view', 'sc.catalog.view'],
            ],
        ],
    ];
}

function notes(): array
{
    return [
        'GET /public/refs' => 'No auth. Use for activity_types, sale_units, root_categories, equipments pickers. Channels are never in this payload (REQ-IN-06). Merge by id; drop status!=active.',
        'GET /governorates' => 'Needs a channel (or any) Bearer. Paginated. search is top-level. filter[status] works. Do not call /platform/refs/governorates from this app (wrong_guard).',
        'GET /zones' => 'Needs Bearer. Paginated. Shared read — not the channel coverage table (that is GET /channel/zones).',
        'GET /currencies' => 'Needs Bearer. decimals governs display; amounts stay int. is_base is never returned.',
        'GET /channel' => 'EP-SC-130A. SupplyChannelResource. allowed_next is for platform transitions — do not render those buttons here; this guard cannot POST /platform/channels/{id}/transition.',
        'PUT /channel' => 'EP-SC-130B. Partial. name/legal_name/tax_number/phone/email/settings. Cannot change status or slug.',
        'GET /channel/zones' => 'EP-SC-131A. Unpaginated coverage rows. delivery_fee and min_order_value are decimal strings. Path param on DELETE is the coverage row id (channel_zone), not zone_id.',
        'POST /channel/zones' => 'EP-SC-131B. Upsert by zone_id. delivery_days ∈ sun…sat. Optional delivery_windows: {day, start H:i, end H:i}. Fees are decimal:0,2 not int money.',
        'GET /channel/zones/map' => 'EP-SC-163. Coverage rows with zone.polygon and delivery_windows. Unpaginated.',
        'DELETE /channel/zones/{}' => 'EP-SC-131C. 204 No Content. Foreign coverage id → 404.',
        'GET /channel/products' => 'List shape is thin: id, sku, name_ar, status. Filters: filter[brand_id|category_id|status|zone_id|search]. No GET show.',
        'PUT /channel/products/{}' => 'Same FormRequest as POST (name_ar + sku required). media_id strings — no upload.',
        'POST /channel/catalog/import' => 'multipart/form-data: file, type=products, optional dry_run. Not JSON.',
        'GET /channel/offers/{}/performance' => 'conversion_rate is scale 10^4 (unique viewers). net_margin = linked_sales − cost_price×qty when cost is set.',
        'GET /channel/content/banners/{}/stats' => 'ctr is integer basis points × 10^4. 0 when impressions=0. Catalog float 0.07 is wrong.',
        'PUT /channel/content/intro' => 'Response is {enabled} only. Re-GET to refill the form. Empty store: enabled false.',
        'GET /channel/dashboard' => 'Zeros / empty arrays until reporting writes DailySnapshot. meta.snapshot_date may be null. Never invent KPIs.',
        'GET /channel/reports/{}' => 'Path type ∈ sales|products|retailers|reps|zones|inventory|finance|offers|operations. date_from/date_to select a DailySnapshot in range (else latest). Unknown type → empty rows 200.',
        'GET /channel/finance/aging' => 'buckets = channel totals. groups[] splits by zone_id (via retailer) or rep_id.',
        'GET /channel/retailers' => 'Paginated shops in coverage zones. id is retailer_profile id for credit/payments.',
        'GET /channel/warehouses' => 'Unpaginated picker. id for inventory adjust/transfer.',
        'GET /channel/products/{}' => 'Full editor body. pricing may be null until PUT /products/{id}/pricing.',
        'GET /channel/invoices/{}' => 'Includes lines[].id for credit-note. Foreign → 404.',
        'GET /channel/finance/aging' => 'group_by=zone|rep is echoed; buckets are NOT grouped — global remaining on open invoices.',
        'POST /channel/inventory/adjust' => 'May return {approval_request_id} + meta.requires_dual_approval instead of movement_id. qty_delta is signed int.',
        'POST /channel/payments' => 'SOD-01: 403 sod_violation if the same user also has sc.orders.confirm, unless channel_manager. method ∈ cash|bank|card. amount int.',
        'POST /channel/invoices/{}/credit-note' => 'Dual-approval eligible. lines[].line_id has no invoice-detail API — do not build a line picker from a missing show.',
        'GET /channel/reps' => 'id is AppUser id. filter[status]=pending_review|active|rejected|disabled. Same id for assign / settle / discount-cap / wallet.',
        'PUT /channel/reps/{}/discount-cap' => 'max_discount_percent 0–100 int. max_cash_hold int minor units, 0 = no cap.',
        'POST /channel/auth/request-otp' => 'Body is {phone} only. X-Device-Id is the rate-limit key. Exempt from idempotency.',
        'POST /channel/auth/verify-otp' => 'Body {otp_id, code as string}. No device_id. Unknown phone / no membership → 403 insufficient_permission. Stay on the login screen.',
        'GET /channel/sub-orders/{}' => 'Drive confirm/reject/edit buttons from allowed_actions, not from a hardcoded status map.',
        'POST /channel/sub-orders/bulk-confirm' => 'Max 50 ids. Partial success is 200 with confirmed[] and failed[].',
        'POST /channel/catalog/export' => 'JSON envelope, not a file download.',
    ];
}

function forbidden(): array
{
    return [
        ['method' => 'GET', 'path' => '/api/v1/channel/offers/{id}', 'reason' => 'No offer show / PUT. List + POST + PATCH stop + GET performance.'],
        ['method' => '*', 'path' => '/api/v1/channel/iam/*', 'reason' => 'Channel IAM is not seeded. Users and roles are platform-only.'],
        ['method' => 'POST', 'path' => '/api/v1/channel/auth/logout', 'reason' => 'No logout route. Clear the token locally.'],
        ['method' => 'GET', 'path' => '/api/v1/channel/me', 'reason' => 'No me/session. verify-otp is the session.'],
        ['method' => 'POST', 'path' => '/api/v1/channel/media', 'reason' => 'Use POST /channel/media/upload (multipart file + type).'],
        ['method' => '*', 'path' => '/api/v1/platform/*', 'reason' => 'Wrong guard — 403 wrong_guard.'],
        ['method' => '*', 'path' => '/api/v1/app/*', 'reason' => 'Wrong guard. Never call retailer or rep routes from this dashboard.'],
        ['method' => 'GET', 'path' => '/api/v1/channel/activity-types', 'reason' => 'Use GET /public/refs.activity_types.'],
        ['method' => 'PATCH', 'path' => '/api/v1/channel/content/banners/{id}', 'reason' => 'Create + stats only. No update/delete.'],
        ['method' => 'PATCH', 'path' => '/api/v1/channel/content/sliders/{id}', 'reason' => 'Create + list only. No update/delete.'],
        ['method' => 'PATCH', 'path' => '/api/v1/channel/loyalty/rewards/{id}', 'reason' => 'List + create only.'],
        ['method' => 'GET', 'path' => '/api/v1/channel/reports/{type}/job/{id}', 'reason' => 'Export returns job_id. No job-status poll route.'],
    ];
}
