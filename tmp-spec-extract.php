<?php

declare(strict_types=1);

$root = __DIR__;

// EP-SC missing routes
$cat = json_decode(file_get_contents($root . '/docs/api/b2b-api.catalog.json'), true);
$live = json_decode(file_get_contents($root . '/docs/api/.live-routes.json'), true);
$missing = [];
$endpoints = $cat['endpoints'] ?? $cat;
foreach ($endpoints as $k => $ep) {
    if (! is_array($ep)) {
        continue;
    }
    $id = $ep['code'] ?? $ep['id'] ?? (is_string($k) ? $k : null);
    if (! is_string($id) || ! str_starts_with($id, 'EP-SC-')) {
        continue;
    }
    $path = $ep['path'] ?? '';
    $method = strtoupper($ep['method'] ?? 'GET');
    $full = $ep['full_path'] ?? '';
    $uri = $full !== '' ? ltrim($full, '/') : 'api/v1' . (str_starts_with($path, '/') ? $path : '/' . $path);
    $uriNorm = preg_replace('#\{[^}]+\}#', '{id}', $uri);
    $found = false;
    foreach ($live as $r) {
        $lu = $r['uri'] ?? '';
        $luNorm = preg_replace('#\{[^}]+\}#', '{id}', $lu);
        $methods = explode('|', strtoupper($r['method'] ?? 'GET'));
        foreach ($methods as $lm) {
            if ($lm === $method && $luNorm === $uriNorm) {
                $found = true;
                break 2;
            }
        }
    }
    if (! $found) {
        $missing[] = ['id' => $id, 'method' => $method, 'path' => $path];
    }
}
echo "=== EP-SC MISSING ===\n";
echo json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo 'MISSING_COUNT=' . count($missing) . "\n\n";

// Postman channel structure
$pm = json_decode(file_get_contents($root . '/docs/api/b2b-api.live.postman_collection.json'), true);
$vars = array_column($pm['variable'] ?? [], 'key');
echo "=== POSTMAN COLLECTION VARS ===\n" . implode(', ', $vars) . "\n\n";

function walkItems(array $items, string $prefix = ''): array
{
    $out = [];
    foreach ($items as $it) {
        $name = $it['name'] ?? '?';
        $path = $prefix === '' ? $name : $prefix . ' / ' . $name;
        if (isset($it['item'])) {
            $out[] = ['kind' => 'folder', 'path' => $path];
            $out = array_merge($out, walkItems($it['item'], $path));
        } elseif (isset($it['request'])) {
            $out[] = ['kind' => 'request', 'path' => $prefix, 'name' => $name];
        }
    }

    return $out;
}

$all = walkItems($pm['item'] ?? []);
$totalReq = count(array_filter($all, fn ($x) => ($x['kind'] ?? '') === 'request'));
echo "TOTAL_REQUESTS=$totalReq\n\n";

function printBranch(array $items, string $needle, string $prefix = ''): void
{
    foreach ($items as $it) {
        $name = $it['name'] ?? '';
        $path = $prefix === '' ? $name : $prefix . ' / ' . $name;
        if (stripos($name, $needle) !== false && isset($it['item'])) {
            echo "=== FOLDER TREE: $path ===\n";
            foreach ($it['item'] as $sub) {
                $sn = $sub['name'] ?? '?';
                $tag = isset($sub['item']) ? '[folder]' : '[request]';
                echo "  $sn $tag\n";
                if (isset($sub['item'])) {
                    foreach ($sub['item'] as $s2) {
                        echo '    ' . ($s2['name'] ?? '?') . (isset($s2['item']) ? ' [folder]' : ' [request]') . "\n";
                    }
                }
            }
            echo "\n";
        }
        if (isset($it['item'])) {
            printBranch($it['item'], $needle, $path);
        }
    }
}

printBranch($pm['item'] ?? [], 'Channel');

// Permission counts
require $root . '/vendor/autoload.php';
// bootstrap not needed - parse PermissionCatalog manually
$catalogFile = file_get_contents($root . '/app-modules/access/src/Domain/PermissionCatalog.php');
preg_match_all("/'sc\\.[^']+'/", $catalogFile, $m);
$scCodes = array_unique($m[0]);
$scCodes = array_map(fn ($s) => trim($s, "'"), $scCodes);
sort($scCodes);
echo "=== SC CODES IN PermissionCatalog ===\n";
echo 'COUNT=' . count($scCodes) . "\n";
echo implode("\n", $scCodes) . "\n\n";

// dual_approval true for sc.*
preg_match_all("/'(sc\\.[^']+)' => \[[^\]]+'dual_approval' => (true|false)/s", $catalogFile, $dm, PREG_SET_ORDER);
$dualSc = [];
foreach ($dm as $row) {
    if ($row[2] === 'true') {
        $dualSc[] = $row[1];
    }
}
echo "=== SC DUAL_APPROVAL TRUE ===\n";
echo implode("\n", $dualSc) . "\n\n";
