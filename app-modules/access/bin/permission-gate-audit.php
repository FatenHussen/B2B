<?php

declare(strict_types=1);

/**
 * Permission gate audit — which live routes enforce a permission and which do not.
 *
 *     php artisan route:list --json > routes.json
 *     php app-modules/access/bin/permission-gate-audit.php routes.json
 *
 * This exists because the first pass of this audit was done with a throwaway regex and
 * was wrong. It reported 11 gated routes and 164 ungated, and the conclusion — "94% of
 * the surface has no permission check" — was acted on before it was caught.
 *
 * **The defect: this repository registers gates in two forms.**
 *
 *     ->middleware('can:ad.refs.create')          Illuminate\Auth\Middleware\Authorize
 *     ->middleware('permission:ad.iam.role_create') Spatie\...\PermissionMiddleware
 *
 * The throwaway scan matched only the first and missed 78 routes using the second, which
 * is the form most of the codebase uses. `route:list --json` resolves middleware to
 * fully-qualified class names, so neither `can:` nor `permission:` appears in its output
 * and a regex written against route-file syntax matches almost nothing — silently, with
 * no error, producing a plausible number.
 *
 * Grepping the route files instead is not equivalent: group middleware applies to routes
 * that do not name it, and route middleware does not appear on the group. The booted
 * route table is the only place both are already resolved.
 *
 * Findings and decisions live in docs/api/permission-gate-audit.md; this script is the
 * measurement, so the next pass cannot repeat the same mistake by hand.
 */
const GATE_MIDDLEWARE = [
    'Illuminate\Auth\Middleware\Authorize',                 // ->middleware('can:...')
    'Spatie\Permission\Middleware\PermissionMiddleware',    // ->middleware('permission:...')
];

$routesFile = $argv[1] ?? null;

if ($routesFile === null || ! is_file($routesFile)) {
    fwrite(STDERR, 'usage: php artisan route:list --json > routes.json && php '.basename(__FILE__)." routes.json\n");
    exit(1);
}

$root = dirname(__DIR__, 3);
$routes = json_decode((string) file_get_contents($routesFile), true, 512, JSON_THROW_ON_ERROR);
$catalog = json_decode((string) file_get_contents($root.'/docs/api/b2b-api.catalog.json'), true, 512, JSON_THROW_ON_ERROR)['endpoints'];

$normalise = static fn (string $path): string => preg_replace('/\{[^}]+\}/', '{}', rtrim($path, '/'));

$byPath = [];
foreach ($catalog as $endpoint) {
    $byPath[strtoupper($endpoint['method']).' '.$normalise($endpoint['path'])] = $endpoint;
}

$gated = [];
$ungatedNamed = [];
$ungatedUnnamed = [];

foreach ($routes as $route) {
    if (! str_starts_with($route['uri'], 'api/v1') || str_contains($route['uri'], 'api/v1/public')) {
        continue;
    }

    $middleware = implode(',', (array) ($route['middleware'] ?? []));

    $hasGate = false;
    foreach (GATE_MIDDLEWARE as $class) {
        if (str_contains($middleware, $class)) {
            $hasGate = true;
            break;
        }
    }

    foreach (explode('|', $route['method']) as $verb) {
        if ($verb === 'HEAD') {
            continue;
        }

        if ($hasGate) {
            $gated[] = "$verb {$route['uri']}";

            continue;
        }

        $key = $verb.' '.$normalise(preg_replace('#^api/v1#', '', $route['uri']));
        $endpoint = $byPath[$key] ?? null;

        if ($endpoint !== null && ! empty($endpoint['permission'])) {
            $ungatedNamed[] = sprintf(
                '%-6s %-52s %-12s %-28s %s',
                $verb, $route['uri'], $endpoint['code'], $endpoint['permission'],
                ! empty($endpoint['critical']) ? 'CRITICAL' : '',
            );
        } else {
            $ungatedUnnamed[] = sprintf('%-6s %s', $verb, $route['uri']);
        }
    }
}

$total = count($gated) + count($ungatedNamed) + count($ungatedUnnamed);

printf("total (non-public, per verb) : %d\n", $total);
printf("gated by a permission        : %d (%.0f%%)\n", count($gated), 100 * count($gated) / max($total, 1));
printf("ungated                      : %d (%.0f%%)\n", count($gated) ? $total - count($gated) : 0, 100 * ($total - count($gated)) / max($total, 1));
printf("  with a catalog permission  : %d\n", count($ungatedNamed));
printf("  with none                  : %d\n\n", count($ungatedUnnamed));

$critical = array_values(array_filter($ungatedNamed, static fn (string $l): bool => str_contains($l, 'CRITICAL')));

printf("UNGATED AND CATALOGUE-CRITICAL: %d%s\n\n", count($critical), $critical === [] ? '  <- must stay zero' : '');
foreach ($critical as $line) {
    echo "  $line\n";
}

echo "=== ungated, catalog names a permission ===\n";
foreach ($ungatedNamed as $line) {
    echo "  $line\n";
}

echo "\n=== ungated, catalog names no permission (needs a contract decision) ===\n";
foreach ($ungatedUnnamed as $line) {
    echo "  $line\n";
}
