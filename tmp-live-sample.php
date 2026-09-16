<?php
$j = json_decode(file_get_contents(__DIR__ . '/docs/api/.live-routes.json'), true);
echo 'count=' . count($j) . PHP_EOL;
for ($i = 0; $i < 3; $i++) {
    print_r($j[$i]);
}
$hits = 0;
foreach ($j as $r) {
    if (str_contains($r['uri'] ?? '', 'channel')) {
        $hits++;
        if ($hits <= 5) {
            echo ($r['method'] ?? '') . ' ' . ($r['uri'] ?? '') . PHP_EOL;
        }
    }
}
echo "channel_hits=$hits\n";
