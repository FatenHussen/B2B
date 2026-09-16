<?php

require __DIR__ . '/vendor/autoload.php';

$catalogFile = file_get_contents(__DIR__ . '/app-modules/access/src/Domain/PermissionCatalog.php');
preg_match_all("/'(sc\\.[^']+)' => \\[[^\\]]+'module' => '([^']+)'/s", $catalogFile, $m, PREG_SET_ORDER);
$byModule = [];
foreach ($m as $row) {
    $byModule[$row[2]][] = $row[1];
}

$grants = [
    'channel_manager' => 'all channel system codes',
    'sales_manager' => ['orders', 'reps', 'merchants', 'promotions', 'dashboard', 'pricing'],
    'catalog_manager' => ['catalog', 'pricing', 'offers', 'promotions', 'content'],
    'accountant' => ['finance', 'returns'],
];

preg_match_all("/'sc\\.[^']+'/", $catalogFile, $allSc);
$allSc = array_unique(array_map(fn ($s) => trim($s, "'"), $allSc[0]));
sort($allSc);

function codesForModules(array $byModule, array $modules): array
{
    $out = [];
    foreach ($modules as $mod) {
        foreach ($byModule[$mod] ?? [] as $code) {
            $out[] = $code;
        }
    }

    return array_values(array_unique($out));
}

echo "channel_manager count=" . count($allSc) . "\n\n";
foreach (['sales_manager', 'catalog_manager', 'accountant'] as $role) {
    $mods = $grants[$role];
    $codes = codesForModules($byModule, $mods);
    sort($codes);
    echo "$role (modules: " . implode(', ', $mods) . ") count=" . count($codes) . "\n";
    echo implode("\n", $codes) . "\n\n";
}

// DOC-08 sc codes from doc08.txt
$doc = file_get_contents(__DIR__ . '/docs/api/doc08.txt');
preg_match_all('/^(sc\.[a-z_]+\.[a-z_]+)/m', $doc, $dm);
$docSc = array_unique($dm[1]);
sort($docSc);
echo 'DOC08_sc_unique=' . count($docSc) . "\n";
$notSeeded = array_diff($docSc, $allSc);
echo "DOC08_not_in_PermissionCatalog (" . count($notSeeded) . "):\n";
echo implode("\n", $notSeeded) . "\n";
