<?php

declare(strict_types=1);

/**
 * Regenerates docs/status/*.md from two sources of truth and nothing else:
 *
 *   1. the contract — docs/api/b2b-api.catalog.json (built by docs/api/generate.php
 *      from docs/api/catalog/*.php);
 *   2. the code     — `php artisan route:list --json --path=api/v1`.
 *
 * Nothing in these files is typed by hand. Run:
 *
 *   php docs/api/generate.php && php docs/status/generate.php
 *
 * Status per catalog endpoint:
 *   ✅ live      — a route with the same method and path exists
 *   ⚠️ deviates  — a route exists but on another prefix (`/admin/...` for `/platform/...`)
 *                  or gated on a different permission than the catalog names
 *   ❌ missing   — no route
 *
 * Live routes with no catalog entry are listed under "خارج الكتالوج" per surface.
 */

$root = dirname(__DIR__, 2);
$out = __DIR__;

$catalogFile = $root.'/docs/api/b2b-api.catalog.json';
if (! is_file($catalogFile)) {
    fwrite(STDERR, "Run php docs/api/generate.php first.\n");
    exit(1);
}
$catalog = json_decode((string) file_get_contents($catalogFile), true, 512, JSON_THROW_ON_ERROR)['endpoints'];

$routesJson = shell_exec('cd '.escapeshellarg($root).' && php artisan route:list --json --path=api/v1');
$routes = json_decode((string) $routesJson, true, 512, JSON_THROW_ON_ERROR);

$norm = fn (string $m, string $p): string => strtoupper($m).' '.rtrim(preg_replace('#\{[^}]+\}#', '{}', $p), '/');

$live = [];
foreach ($routes as $r) {
    $uri = '/'.ltrim($r['uri'], '/');
    if (! str_starts_with($uri, '/api/v1/')) {
        continue;
    }
    $path = substr($uri, strlen('/api/v1'));
    foreach (explode('|', $r['method']) as $m) {
        if ($m === 'HEAD') {
            continue;
        }
        // `permission:x` resolves to Spatie's PermissionMiddleware, `can:x` to Laravel's
        // Authorize — route:list prints the class, so both spellings are read here
        // (permission-gates-two-middlewares).
        $perm = null;
        foreach ($r['middleware'] as $mw) {
            if (preg_match('#^(?:Spatie\\\\Permission\\\\Middleware\\\\PermissionMiddleware|Illuminate\\\\Auth\\\\Middleware\\\\Authorize|can|permission):([^,]+)#', $mw, $mm)) {
                $perm = $mm[1];
            }
        }
        // `app.kind:retailer|rep` — an app user has exactly one kind, so the kind is the
        // permission set; `rt.*` / `rp.*` codes are implied by it rather than seeded.
        $kind = null;
        foreach ($r['middleware'] as $mw) {
            if (preg_match('#RequireAppKind:(\w+)#', $mw, $mm)) {
                $kind = $mm[1];
            }
        }
        $module = preg_match('#^Modules\\\\([A-Za-z]+)\\\\#', $r['action'], $mm) ? $mm[1] : null;
        $live[$norm($m, $path)] = ['method' => $m, 'path' => $path, 'perm' => $perm, 'kind' => $kind, 'module' => $module];
    }
}

$surfaceOf = function (string $path): string {
    return match (true) {
        str_starts_with($path, '/platform'), str_starts_with($path, '/admin') => 'platform',
        str_starts_with($path, '/channel') => 'channel',
        str_starts_with($path, '/warehouse') => 'warehouse',
        str_starts_with($path, '/app/retailer') => 'retailer',
        str_starts_with($path, '/app/rep') => 'rep',
        str_starts_with($path, '/app') => 'shared',
        str_starts_with($path, '/public') => 'public',
        default => 'other',
    };
};

/** Section = the screen group a frontend would build; order is the order of the file. */
$sections = [
    'platform' => [
        ['الدخول والملف الشخصي', '#^/platform/(auth|me)#'],
        ['الصلاحيات والتدقيق (IAM)', '#^/platform/(iam|audit)#'],
        ['المرجعيات', '#^/platform/refs#'],
        ['القنوات', '#^/(platform|admin)/(channels|channel-applications)#'],
        ['الفوترة والباقات', '#^/platform/(plans|subscriptions|platform-invoices|dunning|billing)#'],
        ['الميزات ونسخ التطبيقات', '#^/platform/(features|app-versions)#'],
        ['الإشعارات', '#^/platform/notifications#'],
        ['لوحة القيادة والتقارير', '#^/platform/(dashboard|reports|exports)#'],
        ['الدعم', '#^/platform/support#'],
        ['صحة النظام', '#^/platform/system#'],
        ['المحتوى', '#^/platform/content#'],
        ['الإعدادات والفريق', '#^/platform/(settings|team)#'],
    ],
    'channel' => [
        ['الدخول', '#^/channel/auth#'],
        ['الكتالوج', '#^/channel/(brands|categories|products|catalog)#'],
        ['المندوبون', '#^/channel/(reps|rep-zone-requests|rep-sourced-shops)#'],
        ['التسعير', '#^/channel/(price-lists|pricing|products/\{[^}]+\}/pricing)#'],
        ['العروض', '#^/channel/offers#'],
        ['المخزون', '#^/channel/inventory#'],
        ['الطلبات', '#^/channel/sub-orders#'],
        ['المرتجعات', '#^/channel/return-requests#'],
        ['المالية', '#^/channel/(invoices|payments|finance|retailers)#'],
        ['الإشعارات', '#^/channel/notifications#'],
        ['المحتوى والولاء', '#^/channel/(content|loyalty)#'],
        ['لوحة القيادة والتقارير', '#^/channel/(dashboard|reports)#'],
        ['إعدادات القناة والمناطق', '#^/channel(/zones)?$|^/channel/zones/#'],
    ],
    'warehouse' => [
        ['الدخول', '#^/warehouse/auth#'],
        ['صفوف العمل والتجهيز', '#^/warehouse/(queues|picking-lists)#'],
        ['التغليف', '#^/warehouse/packing#'],
        ['تسليم العهدة', '#^/warehouse/handovers#'],
        ['الاستلام الوارد', '#^/warehouse/receiving#'],
        ['الجرد', '#^/warehouse/stocktakes#'],
        ['المرتجعات', '#^/warehouse/returns#'],
    ],
    'retailer' => [
        ['التسجيل', '#^/app/retailer/register#'],
        ['الرئيسية والكتالوج', '#^/app/retailer/(home|categories|products|brands)#'],
        ['النواقص', '#^/app/retailer/shortages#'],
        ['السلة', '#^/app/retailer/cart#'],
        ['طلباتي والتتبع', '#^/app/retailer/orders#'],
        ['الاستلام والمرتجعات والتقييم', '#^/app/retailer/(receipts|return-requests|reps)#'],
        ['الدفعات والحساب', '#^/app/retailer/(payments|account|debts)#'],
    ],
    'rep' => [
        ['التسجيل والحالة', '#^/app/rep/(register|status)#'],
        ['الرئيسية', '#^/app/rep/home#'],
        ['الكتالوج والعملاء والمناطق', '#^/app/rep/(products|customers|zones)#'],
        ['السلة', '#^/app/rep/cart#'],
        ['قبول الطلبات والمجدولة', '#^/app/rep/(assignments|scheduled-orders)#'],
        ['استلام العهدة من المستودع', '#^/app/rep/warehouse-receipts#'],
        ['التسليم والتتبع والمرتجعات', '#^/app/rep/(deliveries|locations|return-requests)#'],
        ['الدفعات والمحفظة', '#^/app/rep/(payments|wallet|receivables)#'],
    ],
    'shared' => [
        ['الجلسة', '#^/app/(session|auth)#'],
        ['التسعير والعروض', '#^/app/(pricing|offers)#'],
        ['وصل الاستلام', '#^/app/receipts#'],
        ['الإشعارات والأجهزة', '#^/app/(notifications|devices)#'],
        ['المزامنة', '#^/app/sync#'],
        ['المحتوى والولاء', '#^/app/(content|loyalty)#'],
    ],
    'public' => [
        ['التحقق (OTP)', '#^/public/auth#'],
        ['المرجعيات وإعداد التطبيق', '#^/public/(refs|app-config|content)#'],
    ],
    'other' => [
        ['صحة الخدمة', '#^/health#'],
    ],
];

$titles = [
    'platform' => ['01-platform-admin.md', 'لوحة إدارة المنصة (السنترال)', 'guard `platform` · prefix `/api/v1/platform/*`'],
    'channel' => ['02-channel-dashboard.md', 'لوحة قناة التوريد', 'guard `channel` · prefix `/api/v1/channel/*`'],
    'warehouse' => ['03-warehouse.md', 'واجهة المستودع', 'guard `warehouse` · prefix `/api/v1/warehouse/*`'],
    'retailer' => ['04-retailer-app.md', 'تطبيق التاجر', 'guard `app` · prefix `/api/v1/app/retailer/*`'],
    'rep' => ['05-rep-app.md', 'تطبيق المندوب', 'guard `app` · prefix `/api/v1/app/rep/*`'],
    'shared' => ['06-shared-app.md', 'المشترك بين التطبيقين', 'guard `app` · prefix `/api/v1/app/*` (بلا retailer/rep)'],
    'public' => ['07-public.md', 'العام (بلا حارس)', 'prefix `/api/v1/public/*`'],
    'other' => ['08-other.md', 'خارج البادئات', 'routes outside the five prefixes'],
];

$sectionOf = function (string $surface, string $path) use ($sections): string {
    foreach ($sections[$surface] ?? [] as [$name, $re]) {
        if (preg_match($re, $path)) {
            return $name;
        }
    }

    return 'أخرى';
};

// ---- classify every catalog endpoint
$rows = [];
$catKeys = [];
foreach ($catalog as $e) {
    $key = $norm($e['method'], $e['path']);
    $catKeys[$key] = true;
    $sf = $surfaceOf($e['path']);
    $l = $live[$key] ?? null;
    $status = '❌';
    $note = '';
    if ($l !== null) {
        $status = '✅';
        $catPerm = $e['permission'];
        if ($catPerm !== null && $l['perm'] !== null && $l['perm'] !== $catPerm) {
            $status = '⚠️';
            $note = 'gate `'.$l['perm'].'` ≠ catalog `'.$catPerm.'`';
        } elseif ($catPerm !== null && $l['perm'] === null && $l['kind'] !== null && preg_match('#^r[tp]\.#', $catPerm)) {
            $note = 'gated by `app.kind:'.$l['kind'].'` — the kind implies `'.$catPerm.'`';
        } elseif ($catPerm !== null && $l['perm'] === null) {
            $status = '⚠️';
            $note = 'no permission middleware; catalog names `'.$catPerm.'`';
        }
    } elseif (str_starts_with($e['path'], '/platform/')) {
        $alt = '/admin/'.substr($e['path'], strlen('/platform/'));
        $altKey = $norm($e['method'], $alt);
        if (isset($live[$altKey])) {
            $l = $live[$altKey];
            $status = '⚠️';
            $note = 'served on `'.$alt.'` — catalog path is `'.$e['path'].'`';
            $catKeys[$altKey] = true; // do not list it again as extra
        }
    }
    $rows[$sf][] = [
        'section' => $sectionOf($sf, $e['path']),
        'code' => $e['code'],
        'sprint' => $e['sprint'],
        'method' => $e['method'],
        'path' => $e['path'],
        'perm' => $e['permission'],
        'module' => $l['module'] ?? null,
        'status' => $status,
        'note' => $note,
        'name_ar' => $e['name_ar'] ?? '',
    ];
}

$extras = [];
foreach ($live as $key => $l) {
    if (isset($catKeys[$key])) {
        continue;
    }
    $extras[$surfaceOf($l['path'])][] = $l;
}

$md = fn (string $s): string => str_replace('|', '\|', $s);

$generatedAt = date('Y-m-d');
$totals = [];

foreach ($titles as $sf => [$file, $title, $sub]) {
    $list = $rows[$sf] ?? [];
    $n = count($list);
    $ok = count(array_filter($list, fn ($r) => $r['status'] === '✅'));
    $dev = count(array_filter($list, fn ($r) => $r['status'] === '⚠️'));
    $miss = $n - $ok - $dev;
    $ex = count($extras[$sf] ?? []);
    $totals[$sf] = compact('title', 'file', 'n', 'ok', 'dev', 'miss', 'ex');

    if ($n === 0 && $ex === 0) {
        continue;
    }

    $lines = [];
    $lines[] = "# {$title} — حالة الواجهات";
    $lines[] = '';
    $lines[] = "{$sub} · مولَّد آلياً في {$generatedAt} من الكتالوج و`route:list` — لا يُحرَّر يدوياً (`php docs/status/generate.php`).";
    $lines[] = '';
    $lines[] = "| الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |";
    $lines[] = '|---|---|---|---|---|';
    $lines[] = "| {$n} | {$ok} | {$dev} | {$miss} | {$ex} |";
    $lines[] = '';

    // group by section preserving configured order
    $order = array_map(fn ($s) => $s[0], $sections[$sf] ?? []);
    $order[] = 'أخرى';
    $bySection = [];
    foreach ($list as $r) {
        $bySection[$r['section']][] = $r;
    }
    foreach ($order as $sec) {
        if (! isset($bySection[$sec])) {
            continue;
        }
        $items = $bySection[$sec];
        usort($items, fn ($a, $b) => [$a['sprint'], $a['code']] <=> [$b['sprint'], $b['code']]);
        $sOk = count(array_filter($items, fn ($r) => $r['status'] === '✅'));
        $lines[] = "## {$sec} — {$sOk}/".count($items);
        $lines[] = '';
        $lines[] = '| الحالة | EP | السبرنت | الطريقة | المسار | الصلاحية | الوحدة | ملاحظة |';
        $lines[] = '|---|---|---|---|---|---|---|---|';
        foreach ($items as $r) {
            $lines[] = sprintf(
                '| %s | %s | %s | `%s` | `%s` | %s | %s | %s |',
                $r['status'],
                $r['code'],
                $r['sprint'],
                $r['method'],
                $r['path'],
                $r['perm'] ? '`'.$r['perm'].'`' : '—',
                $r['module'] ?? '—',
                $md($r['note'] !== '' ? $r['note'] : $r['name_ar']),
            );
        }
        $lines[] = '';
    }

    if ($ex > 0) {
        $lines[] = '## حيّ خارج الكتالوج';
        $lines[] = '';
        $lines[] = 'مسارات تخدمها الشيفرة ولا يذكرها الكتالوج. إما تُضاف إلى `docs/api/catalog/` أو تُزال — لا ثالث.';
        $lines[] = '';
        $lines[] = '| الطريقة | المسار | الصلاحية | الوحدة |';
        $lines[] = '|---|---|---|---|';
        $items = $extras[$sf];
        usort($items, fn ($a, $b) => [$a['path'], $a['method']] <=> [$b['path'], $b['method']]);
        foreach ($items as $l) {
            $lines[] = sprintf('| `%s` | `%s` | %s | %s |', $l['method'], $l['path'], $l['perm'] ? '`'.$l['perm'].'`' : '—', $l['module'] ?? '—');
        }
        $lines[] = '';
    }

    file_put_contents($out.'/'.$file, implode("\n", $lines)."\n");
}

// ---- overview
$o = [];
$o[] = '# حالة الواجهات — نظرة عامة';
$o[] = '';
$o[] = "مولَّد آلياً في {$generatedAt}. المصدران: `docs/api/catalog/*.php` (العقد) و`php artisan route:list` (الشيفرة). أعد التوليد بعد كل تغيير في المسارات:";
$o[] = '';
$o[] = '```bash';
$o[] = 'php docs/api/generate.php && php docs/status/generate.php';
$o[] = '```';
$o[] = '';
$o[] = '| السطح | الملف | الكتالوج | ✅ حيّ | ⚠️ منحرف | ❌ ناقص | حيّ خارج الكتالوج |';
$o[] = '|---|---|---|---|---|---|---|';
$sumN = $sumOk = $sumDev = $sumMiss = $sumEx = 0;
foreach ($totals as $sf => $t) {
    if ($t['n'] === 0 && $t['ex'] === 0) {
        continue;
    }
    $o[] = sprintf('| %s | [%s](%s) | %d | %d | %d | %d | %d |', $t['title'], $t['file'], $t['file'], $t['n'], $t['ok'], $t['dev'], $t['miss'], $t['ex']);
    $sumN += $t['n'];
    $sumOk += $t['ok'];
    $sumDev += $t['dev'];
    $sumMiss += $t['miss'];
    $sumEx += $t['ex'];
}
$o[] = sprintf('| **المجموع** | | **%d** | **%d** | **%d** | **%d** | **%d** |', $sumN, $sumOk, $sumDev, $sumMiss, $sumEx);
$o[] = '';
$o[] = '## معنى الرموز';
$o[] = '';
$o[] = '- ✅ **حيّ**: مسار بنفس الطريقة والمسار موجود في الشيفرة.';
$o[] = '- ⚠️ **منحرف**: المسار موجود لكن على بادئة أخرى (`/admin/` بدل `/platform/`) أو خلف صلاحية غير التي يسمّيها الكتالوج. الكتالوج هو مصدر المسار؛ الانحراف يُصلَح في الشيفرة.';
$o[] = '- ❌ **ناقص**: لا مسار. العمل المتبقي مرتّب في `docs/plan/`.';
$o[] = '- **حيّ خارج الكتالوج**: تخدمه الشيفرة ولا يذكره الكتالوج — يُضاف إلى الكتالوج بـ `b`/`r`/`e` أو يُزال.';
$o[] = '';
$o[] = '## ما هو مكتمل وما هو ناقص — بجملة لكل سطح';
$o[] = '';
foreach ($totals as $sf => $t) {
    if ($t['n'] === 0) {
        continue;
    }
    $state = $t['miss'] === 0 && $t['dev'] === 0 ? 'مكتمل بالكامل' : ($t['ok'] === 0 ? 'لم يُبدأ' : 'جزئي');
    $o[] = sprintf('- **%s** — %s: %d/%d حيّ%s%s.', $t['title'], $state, $t['ok'], $t['n'], $t['dev'] ? ", {$t['dev']} منحرف" : '', $t['miss'] ? ", {$t['miss']} ناقص" : '');
}
$o[] = '';
file_put_contents($out.'/00-overview.md', implode("\n", $o)."\n");

fwrite(STDOUT, sprintf("status: %d catalog endpoints — %d live, %d deviating, %d missing, %d live outside the catalog\n", $sumN, $sumOk, $sumDev, $sumMiss, $sumEx));
