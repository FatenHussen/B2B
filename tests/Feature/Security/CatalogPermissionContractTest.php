<?php

declare(strict_types=1);

/**
 * BF-10: catalog permission contract.
 *
 * - Every authenticated catalog endpoint either names a DOC-08 permission or is
 *   marked `guard_only` (auth / app.kind only — deliberate, not forgotten).
 * - No live route may sit ungated while the catalog names a permission for it.
 */
it('marks every null-permission catalog endpoint as guard_only', function () {
    $catalog = json_decode((string) file_get_contents(base_path('docs/api/b2b-api.catalog.json')), true);
    $forgotten = [];

    foreach ($catalog['endpoints'] as $ep) {
        if (! empty($ep['permission'])) {
            continue;
        }
        if (($ep['audience'] ?? '') === 'public') {
            continue;
        }
        if (! empty($ep['guard_only'])) {
            continue;
        }
        $forgotten[] = ($ep['code'] ?? '?').' '.($ep['method'] ?? '').' '.($ep['path'] ?? '');
    }

    expect($forgotten)->toBe([]);
})->group('security');

it('gates every live route whose catalog names a permission', function () {
    $json = (string) file_get_contents(base_path('docs/api/.live-routes.json'));
    $json = preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json;
    // Strip UTF-16 BOM / NUL padding if a Windows redirect polluted the dump.
    if (str_starts_with($json, "\xFF\xFE") || str_contains(substr($json, 0, 20), "\0")) {
        test()->markTestSkipped('docs/api/.live-routes.json is not UTF-8; re-run: php -r "file_put_contents(\'docs/api/.live-routes.json\', shell_exec(\'php artisan route:list --json\'));"');
    }

    $routes = json_decode($json, true);
    expect($routes)->toBeArray();

    $catalog = json_decode((string) file_get_contents(base_path('docs/api/b2b-api.catalog.json')), true);
    $byPath = [];
    $normalise = static fn (string $path): string => preg_replace('/\{[^}]+\}/', '{}', rtrim($path, '/')) ?? $path;

    foreach ($catalog['endpoints'] as $ep) {
        if (empty($ep['permission'])) {
            continue;
        }
        $byPath[strtoupper((string) $ep['method']).' '.$normalise((string) $ep['path'])] = $ep;
    }

    $gateClasses = [
        'Modules\Access\Http\Middleware\PermissionMiddleware',
        'Spatie\Permission\Middleware\PermissionMiddleware',
        'Illuminate\Auth\Middleware\Authorize',
    ];

    $ungatedNamed = [];

    foreach ($routes as $route) {
        $uri = (string) ($route['uri'] ?? '');
        if (! str_starts_with($uri, 'api/v1') || str_contains($uri, 'api/v1/public')) {
            continue;
        }

        $middleware = implode(',', (array) ($route['middleware'] ?? []));
        $hasGate = false;
        foreach ($gateClasses as $class) {
            if (str_contains($middleware, $class)) {
                $hasGate = true;
                break;
            }
        }

        foreach (explode('|', (string) $route['method']) as $verb) {
            if ($verb === 'HEAD') {
                continue;
            }
            if ($hasGate) {
                continue;
            }

            $path = preg_replace('#^api/v1#', '', $uri) ?? $uri;
            $key = $verb.' '.$normalise($path);
            if (isset($byPath[$key])) {
                $ungatedNamed[] = "$verb $uri → {$byPath[$key]['permission']}";
            }
        }
    }

    expect($ungatedNamed)->toBe([]);
})->group('security');
